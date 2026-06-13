<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPreviousEducation;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdmissionReviewTest extends TestCase
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

    public function test_index_defaults_to_pending(): void
    {
        $this->makeApplication(['status' => AdmissionStatus::Pending]);
        $this->makeApplication(['status' => AdmissionStatus::Approved]);
        $this->makeApplication(['status' => AdmissionStatus::Rejected]);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson('/api/v1/admissions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_filters_and_search(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);

        $karim = $this->makeApplication([
            'name_en' => 'Karim Hossain',
            'father_mobile' => '01711111111',
            'created_at' => '2026-06-10 11:20:00',
        ]);
        $this->makeApplication([
            'name_en' => 'Rahim Uddin',
            'desired_class_id' => $otherClass->id,
            'created_at' => '2026-06-01 09:00:00',
        ]);

        $token = $this->tokenForRole('admin');

        // status filter (approved)
        $this->makeApplication(['status' => AdmissionStatus::Approved, 'name_en' => 'Approved One']);
        $this->withToken($token)->getJson('/api/v1/admissions?status=approved')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name_en', 'Approved One');

        // desired_class_id filter
        $this->withToken($token)->getJson("/api/v1/admissions?desired_class_id={$this->class->id}")
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name_en', 'Karim Hossain');

        // date range filter
        $this->withToken($token)->getJson('/api/v1/admissions?from=2026-06-05&to=2026-06-15')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name_en', 'Karim Hossain');

        // search by name
        $this->withToken($token)->getJson('/api/v1/admissions?search=karim')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $karim->id);

        // search by application_no
        $this->withToken($token)->getJson('/api/v1/admissions?search='.$karim->application_no)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $karim->id);

        // search by father_mobile
        $this->withToken($token)->getJson('/api/v1/admissions?search=01711111111')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $karim->id);
    }

    public function test_show_returns_full_detail(): void
    {
        $application = $this->makeApplication(['name_en' => 'Karim Hossain']);
        $application->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photo');
        $application->addMedia(UploadedFile::fake()->createWithContent('marksheet.pdf', '%PDF-1.4 fake'))->toMediaCollection('documents');
        AdmissionPreviousEducation::factory()->count(2)->create(['application_id' => $application->id]);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/admissions/{$application->id}")
            ->assertOk()
            ->assertJsonPath('data.name_en', 'Karim Hossain')
            ->assertJsonPath('data.desired_class.id', $this->class->id)
            ->assertJsonPath('data.name_bn', $application->name_bn)
            ->assertJsonPath('data.reviewed_by', null)
            ->assertJsonPath('data.reviewed_at', null)
            ->assertJsonCount(2, 'data.previous_educations')
            ->assertJsonCount(1, 'data.documents')
            ->assertJsonPath('data.documents.0.name', 'marksheet.pdf')
            ->assertJsonPath('data.documents.0.url', fn ($url) => is_string($url) && $url !== '')
            ->assertJsonPath('data.photo_url', fn ($url) => is_string($url) && $url !== '');
    }

    public function test_cross_branch_application_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create(['code' => 'MP']);
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);
        $foreign = $this->makeApplication(['desired_class_id' => $otherClass->id], $otherBranch);

        $token = $this->tokenForRole('admin');

        // Not listed
        $this->withToken($token)->getJson('/api/v1/admissions')
            ->assertOk()->assertJsonCount(0, 'data');

        // Not viewable
        $this->withToken($token)->getJson("/api/v1/admissions/{$foreign->id}")
            ->assertStatus(404);
    }

    public function test_index_has_no_n_plus_one(): void
    {
        AdmissionApplication::factory()->count(3)->create([
            'branch_id' => $this->branch->id,
            'desired_class_id' => $this->class->id,
            'status' => AdmissionStatus::Pending,
        ]);

        $token = $this->tokenForRole('admin');

        Model::shouldBeStrict();

        try {
            $this->withToken($token)
                ->getJson('/api/v1/admissions')
                ->assertOk()
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('data.0.desired_class.name', $this->class->name);
        } finally {
            Model::shouldBeStrict(false);
        }
    }

    public function test_requires_permission(): void
    {
        $this->withToken($this->tokenForRole('accountant'))
            ->getJson('/api/v1/admissions')
            ->assertStatus(403);
    }
}
