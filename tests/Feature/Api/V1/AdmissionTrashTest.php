<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Soft-delete lifecycle for admission applications: delete (single + bulk),
 * trash listing, restore (single + bulk), and permanent delete (single + bulk),
 * plus the permission + branch-isolation boundaries.
 */
class AdmissionTrashTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['code' => 'JA']);
        $this->class = SchoolClass::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    private function makeApplication(array $overrides = [], ?Branch $branch = null): AdmissionApplication
    {
        $branch ??= $this->branch;

        return AdmissionApplication::factory()->create(array_merge([
            'branch_id' => $branch->id,
            'desired_class_id' => $this->class->id,
        ], $overrides));
    }

    public function test_destroy_soft_deletes_and_removes_from_the_live_queue(): void
    {
        $application = $this->makeApplication();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->deleteJson("/api/v1/admissions/{$application->public_id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted($application);

        // Gone from the live queue, present in the trash with deleted_at set.
        $this->withToken($token)->getJson('/api/v1/admissions')
            ->assertOk()->assertJsonCount(0, 'data');

        $trash = $this->withToken($token)->getJson('/api/v1/admissions/trash')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $application->public_id);

        $this->assertNotNull($trash->json('data.0.deleted_at'));
    }

    public function test_bulk_delete_trashes_many_and_skips_foreign_branch_ids(): void
    {
        $a = $this->makeApplication();
        $b = $this->makeApplication();
        $foreignBranch = Branch::factory()->create(['code' => 'XX']);
        $foreignClass = SchoolClass::factory()->create(['branch_id' => $foreignBranch->id]);
        $foreign = $this->makeApplication(['desired_class_id' => $foreignClass->id], $foreignBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/admissions/bulk-delete', [
                'ids' => [$a->public_id, $b->public_id, $foreign->public_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertSoftDeleted($a);
        $this->assertSoftDeleted($b);
        $this->assertNotSoftDeleted($foreign);
    }

    public function test_restore_brings_an_application_back_to_the_live_queue(): void
    {
        $application = $this->makeApplication(['status' => AdmissionStatus::Approved]);
        $application->delete();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$application->public_id}/restore")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotSoftDeleted($application);

        $this->withToken($token)->getJson('/api/v1/admissions')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson('/api/v1/admissions/trash')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_bulk_restore_restores_many_trashed_applications(): void
    {
        $a = $this->makeApplication();
        $b = $this->makeApplication();
        $a->delete();
        $b->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/admissions/bulk-restore', [
                'ids' => [$a->public_id, $b->public_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.restored', 2);

        $this->assertNotSoftDeleted($a);
        $this->assertNotSoftDeleted($b);
    }

    public function test_force_delete_permanently_removes_a_trashed_application_and_its_media(): void
    {
        $application = $this->makeApplication();
        $application->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photo');
        $mediaId = $application->getFirstMedia('photo')->id;
        $application->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->deleteJson("/api/v1/admissions/{$application->public_id}/force")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('admission_applications', ['id' => $application->id]);
        $this->assertDatabaseMissing('media', ['id' => $mediaId]);
    }

    public function test_force_delete_requires_application_to_be_trashed_first(): void
    {
        $application = $this->makeApplication();

        $this->withToken($this->tokenForRole('admin'))
            ->deleteJson("/api/v1/admissions/{$application->public_id}/force")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Application must be in trash before permanent deletion.');

        $this->assertNotSoftDeleted($application);
        $this->assertDatabaseHas('admission_applications', ['id' => $application->id]);
    }

    public function test_force_delete_rejects_application_linked_to_a_student(): void
    {
        $application = $this->makeApplication(['status' => AdmissionStatus::Approved]);
        Student::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => User::factory()->create(['branch_id' => $this->branch->id])->id,
            'application_id' => $application->id,
        ]);
        $application->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->deleteJson("/api/v1/admissions/{$application->public_id}/force")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Application has an admitted student and cannot be permanently deleted.');

        $this->assertSoftDeleted($application);
        $this->assertDatabaseHas('admission_applications', ['id' => $application->id]);
    }

    public function test_bulk_force_delete_permanently_removes_many_trashed(): void
    {
        $a = $this->makeApplication();
        $b = $this->makeApplication();
        $a->delete();
        $b->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/admissions/bulk-force-delete', [
                'ids' => [$a->public_id, $b->public_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseMissing('admission_applications', ['id' => $a->id]);
        $this->assertDatabaseMissing('admission_applications', ['id' => $b->id]);
    }

    public function test_bulk_force_delete_rejects_linked_applications_without_partial_deletes(): void
    {
        $deletable = $this->makeApplication();
        $linked = $this->makeApplication(['status' => AdmissionStatus::Approved]);
        Student::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => User::factory()->create(['branch_id' => $this->branch->id])->id,
            'application_id' => $linked->id,
        ]);
        $deletable->delete();
        $linked->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/admissions/bulk-force-delete', [
                'ids' => [$deletable->public_id, $linked->public_id],
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Application has an admitted student and cannot be permanently deleted.');

        $this->assertSoftDeleted($deletable);
        $this->assertSoftDeleted($linked);
        $this->assertDatabaseHas('admission_applications', ['id' => $deletable->id]);
        $this->assertDatabaseHas('admission_applications', ['id' => $linked->id]);
    }

    public function test_trash_actions_require_the_admission_delete_permission(): void
    {
        $application = $this->makeApplication();
        // The teacher role holds no admission.delete permission.
        $token = $this->tokenForRole('teacher');

        $this->withToken($token)
            ->deleteJson("/api/v1/admissions/{$application->public_id}")
            ->assertForbidden();

        $this->withToken($token)->getJson('/api/v1/admissions/trash')->assertForbidden();
        $this->withToken($token)
            ->postJson('/api/v1/admissions/bulk-delete', ['ids' => [$application->public_id]])
            ->assertForbidden();

        $this->assertNotSoftDeleted($application);
    }

    public function test_out_of_branch_application_cannot_be_deleted(): void
    {
        $foreignBranch = Branch::factory()->create(['code' => 'XX']);
        $foreignClass = SchoolClass::factory()->create(['branch_id' => $foreignBranch->id]);
        $foreign = $this->makeApplication(['desired_class_id' => $foreignClass->id], $foreignBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->deleteJson("/api/v1/admissions/{$foreign->public_id}")
            ->assertNotFound();

        $this->assertNotSoftDeleted($foreign);
    }
}
