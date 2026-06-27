<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendCredentials;
use App\Mail\CredentialsMail;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ParentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create();
    }

    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    private function makeStudent(array $overrides = [], ?Branch $branch = null, array $enrollment = []): Student
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('student');

        $student = Student::factory()->create(array_merge([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ], $overrides));

        if ($enrollment !== []) {
            Enrollment::factory()->create(array_merge([
                'student_id' => $student->id,
                'session_id' => $this->session->id,
            ], $enrollment));
        }

        return $student;
    }

    public function test_create_parent_with_links_in_one_transaction_queues_credentials(): void
    {
        Queue::fake();

        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $section = Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);
        $student = $this->makeStudent(
            ['name_en' => 'Rahima Khatun'],
            enrollment: ['class_id' => $class->id, 'section_id' => $section->id, 'roll_no' => 12],
        );

        $response = $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'email' => null,
                'relation' => 'father',
                'student_ids' => [$student->id],
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Abdul Karim')
            ->assertJsonPath('data.relation', 'father')
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.id', $student->public_id)
            ->assertJsonPath('data.students.0.name_en', 'Rahima Khatun')
            ->assertJsonPath('data.students.0.class', 'Class 7')
            ->assertJsonPath('data.students.0.section', 'A');

        $parentPublicId = $response->json('data.id');
        $parentId = ParentProfile::where('public_id', $parentPublicId)->value('id');

        $this->assertDatabaseHas('parents', ['public_id' => $parentPublicId, 'phone' => '01811111111', 'relation' => 'father']);
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parentId, 'student_id' => $student->id]);

        $user = User::where('phone', '01811111111')->firstOrFail();
        $this->assertTrue($user->hasRole('parent'));

        Queue::assertPushed(SendCredentials::class, fn ($job) => $job->user->id === $user->id && $job->role === 'Parent');
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $student = $this->makeStudent();
        User::factory()->create(['branch_id' => $this->branch->id, 'phone' => '01811111111']);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'relation' => 'father',
                'student_ids' => [$student->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_invalid_relation_is_rejected(): void
    {
        $student = $this->makeStudent();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'relation' => 'uncle',
                'student_ids' => [$student->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('relation');
    }

    public function test_create_with_foreign_branch_student_is_rejected(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreign = $this->makeStudent(branch: $otherBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents', [
                'name' => 'Abdul Karim',
                'phone' => '01811111111',
                'relation' => 'father',
                'student_ids' => [$foreign->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_ids');
    }

    public function test_link_unlink_with_conflict_and_not_found_semantics(): void
    {
        $parent = $this->makeParent();
        $linked = $this->makeStudent();
        $parent->students()->attach($linked->id);

        $other = $this->makeStudent();
        $token = $this->tokenForRole('admin');

        // duplicate link → 409
        $this->withToken($token)
            ->postJson("/api/v1/parents/{$parent->public_id}/students", ['student_id' => $linked->id])
            ->assertStatus(409);

        // fresh link → ok
        $this->withToken($token)
            ->postJson("/api/v1/parents/{$parent->public_id}/students", ['student_id' => $other->id])
            ->assertOk();
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => $other->id]);

        // unlink a non-linked (but valid, never linked) student → 404
        $never = $this->makeStudent();
        $this->withToken($token)
            ->deleteJson("/api/v1/parents/{$parent->public_id}/students/{$never->public_id}")
            ->assertStatus(404);

        // unlink a linked student → ok
        $this->withToken($token)
            ->deleteJson("/api/v1/parents/{$parent->public_id}/students/{$linked->public_id}")
            ->assertOk();
        $this->assertDatabaseMissing('parent_student', ['parent_id' => $parent->id, 'student_id' => $linked->id]);
    }

    public function test_link_foreign_branch_student_is_rejected(): void
    {
        $parent = $this->makeParent();
        $otherBranch = Branch::factory()->create();
        $foreign = $this->makeStudent(branch: $otherBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/parents/{$parent->public_id}/students", ['student_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('student_id');
    }

    public function test_me_students_returns_exactly_linked_for_parent_role(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $section = Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);

        $linked = $this->makeStudent(
            ['name_en' => 'Rahima Khatun'],
            enrollment: ['class_id' => $class->id, 'section_id' => $section->id, 'roll_no' => 12],
        );
        $this->makeStudent(['name_en' => 'Unrelated Child']);

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id]);
        $parentUser->assignRole('parent');
        $parent = ParentProfile::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $parentUser->id,
        ]);
        $parent->students()->attach($linked->id);

        $token = $parentUser->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/me/students')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $linked->public_id)
            ->assertJsonPath('data.0.name_en', 'Rahima Khatun')
            ->assertJsonPath('data.0.class', 'Class 7')
            ->assertJsonPath('data.0.section', 'A');
    }

    public function test_me_students_forbidden_for_non_parent_role(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->getJson('/api/v1/me/students')
            ->assertStatus(403);
    }

    public function test_resend_credentials_queues_mail_for_parent(): void
    {
        Mail::fake();
        $parent = $this->makeParent();
        $parent->user->forceFill(['email' => 'guardian@example.test', 'phone' => '01822222222'])->save();
        $oldHash = $parent->user->password;

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/parents/{$parent->public_id}/resend-credentials")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'New credentials are being sent to the parent.')
            ->assertJsonPath('data', null);

        $this->assertNotSame($oldHash, $parent->user->fresh()->password);

        Mail::assertSent(CredentialsMail::class, function (CredentialsMail $mail): bool {
            return $mail->hasTo('guardian@example.test')
                && $mail->role === 'Parent'
                && $mail->email === 'guardian@example.test'
                && $mail->phone === '01822222222';
        });
    }

    public function test_resend_credentials_422_when_parent_has_no_email(): void
    {
        Mail::fake();
        $parent = $this->makeParent();
        $parent->user->forceFill(['email' => null])->save();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/parents/{$parent->public_id}/resend-credentials")
            ->assertStatus(422);

        Mail::assertNothingSent();
    }

    public function test_resend_credentials_requires_parent_manage_permission(): void
    {
        $parent = $this->makeParent();

        $this->withToken($this->tokenForRole('teacher'))
            ->postJson("/api/v1/parents/{$parent->public_id}/resend-credentials")
            ->assertStatus(403);
    }

    public function test_delete_moves_parent_to_trash_and_disables_login(): void
    {
        $parent = $this->makeParent();
        $parent->update(['name' => 'Trash Guardian']);
        $student = $this->makeStudent();
        $parent->students()->attach($student->id);

        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->deleteJson("/api/v1/parents/{$parent->public_id}")
            ->assertOk()
            ->assertJsonPath('message', 'Parent moved to trash.');

        $this->assertSoftDeleted('parents', ['id' => $parent->id]);
        $this->assertDatabaseHas('users', ['id' => $parent->user_id, 'is_active' => false]);
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => $student->id]);

        $this->withToken($token)
            ->getJson('/api/v1/parents?search=Trash%20Guardian')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $trash = $this->withToken($token)
            ->getJson('/api/v1/parents/trash?search=Trash%20Guardian')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $parent->public_id);

        $this->assertNotNull($trash->json('data.0.deleted_at'));
    }

    public function test_bulk_delete_trashes_many_and_skips_foreign_branch_ids(): void
    {
        $a = $this->makeParent();
        $b = $this->makeParent();
        $foreign = $this->makeParent(Branch::factory()->create());

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents/bulk-delete', [
                'ids' => [$a->public_id, $b->public_id, $foreign->public_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertSoftDeleted('parents', ['id' => $a->id]);
        $this->assertSoftDeleted('parents', ['id' => $b->id]);
        $this->assertNotSoftDeleted('parents', ['id' => $foreign->id]);
    }

    public function test_restore_brings_parent_back_from_trash(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        $parent->students()->attach($student->id);
        $parent->delete();
        $parent->user->update(['is_active' => false]);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/parents/{$parent->public_id}/restore")
            ->assertOk()
            ->assertJsonPath('message', 'Parent restored.');

        $this->assertNotSoftDeleted('parents', ['id' => $parent->id]);
        $this->assertDatabaseHas('users', ['id' => $parent->user_id, 'is_active' => true]);
        $this->assertDatabaseHas('parent_student', ['parent_id' => $parent->id, 'student_id' => $student->id]);
    }

    public function test_bulk_restore_restores_many_trashed_parents(): void
    {
        $a = $this->makeParent();
        $b = $this->makeParent();
        $a->delete();
        $b->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents/bulk-restore', [
                'ids' => [$a->public_id, $b->public_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.restored', 2);

        $this->assertNotSoftDeleted('parents', ['id' => $a->id]);
        $this->assertNotSoftDeleted('parents', ['id' => $b->id]);
    }

    public function test_force_delete_permanently_removes_trashed_parent_login_and_links(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        $parent->students()->attach($student->id);
        $userId = $parent->user_id;
        $parent->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->deleteJson("/api/v1/parents/{$parent->public_id}/force")
            ->assertOk()
            ->assertJsonPath('message', 'Parent permanently deleted.');

        $this->assertDatabaseMissing('parents', ['id' => $parent->id]);
        $this->assertDatabaseMissing('parent_student', ['parent_id' => $parent->id, 'student_id' => $student->id]);
        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_force_delete_requires_parent_to_be_trashed_first(): void
    {
        $parent = $this->makeParent();

        $this->withToken($this->tokenForRole('admin'))
            ->deleteJson("/api/v1/parents/{$parent->public_id}/force")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Parent must be in trash before permanent deletion.');
    }

    public function test_bulk_force_delete_permanently_removes_many_trashed_parents(): void
    {
        $a = $this->makeParent();
        $b = $this->makeParent();
        $a->delete();
        $b->delete();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/parents/bulk-force-delete', [
                'ids' => [$a->public_id, $b->public_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseMissing('parents', ['id' => $a->id]);
        $this->assertDatabaseMissing('parents', ['id' => $b->id]);
    }

    public function test_trash_actions_require_parent_manage_permission(): void
    {
        $parent = $this->makeParent();
        $token = $this->tokenForRole('teacher');

        $this->withToken($token)->getJson('/api/v1/parents/trash')->assertForbidden();
        $this->withToken($token)->deleteJson("/api/v1/parents/{$parent->public_id}")->assertForbidden();
        $this->withToken($token)
            ->postJson('/api/v1/parents/bulk-delete', ['ids' => [$parent->public_id]])
            ->assertForbidden();
    }

    private function makeParent(?Branch $branch = null): ParentProfile
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('parent');

        return ParentProfile::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);
    }
}
