<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdmissionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Jobs\SendCredentials;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdmissionApproveRejectTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private SchoolClass $class;

    private Section $section;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['code' => 'JA']);
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true, 'name' => 'Class 7']);
        $this->section = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'A']);
        $this->session = AcademicSession::factory()->current()->create(['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
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

    private function approvePayload(array $overrides = []): array
    {
        return array_merge([
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 12,
            'create_parent_account' => false,
        ], $overrides);
    }

    public function test_approve_happy_path_creates_all_rows(): void
    {
        Queue::fake();

        $application = $this->makeApplication([
            'name_en' => 'Karim Hossain',
            'name_bn' => 'করিম হোসেন',
            'father_mobile' => '01711111111',
            'present_village' => 'Shibganj',
            'permanent_village' => 'শিবগঞ্জ',
        ]);
        $application->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photo');

        $response = $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload([
                'admission_no' => 'STU-JA-2026-0012',
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Admission approved. Student account created.')
            ->assertJsonPath('data.student.admission_no', 'STU-JA-2026-0012')
            ->assertJsonPath('data.student.name_en', 'Karim Hossain')
            ->assertJsonPath('data.student.enrollment.session', '2026')
            ->assertJsonPath('data.student.enrollment.class', 'Class 7')
            ->assertJsonPath('data.student.enrollment.section', 'A')
            ->assertJsonPath('data.student.enrollment.roll_no', 12)
            ->assertJsonPath('data.parent_created', false);

        $studentId = $response->json('data.student.id');

        // Application data copied faithfully (identity + address fields).
        $student = Student::find($studentId);
        $this->assertSame('Karim Hossain', $student->name_en);
        $this->assertSame('করিম হোসেন', $student->name_bn);
        $this->assertSame('Shibganj', $student->present_village);
        $this->assertSame('শিবগঞ্জ', $student->permanent_village);
        $this->assertSame('01711111111', $student->father_mobile);
        $this->assertSame(StudentStatus::Active, $student->status);
        $this->assertSame($application->id, $student->application_id);

        // Photo media copied onto the student.
        $this->assertNotNull($student->getFirstMedia('photo'));

        // Login created with phone identifier and email null.
        $user = User::find($student->user_id);
        $this->assertNull($user->email);
        $this->assertSame('01711111111', $user->phone);
        $this->assertTrue($user->hasRole('student'));

        // Enrollment is active.
        $enrollment = Enrollment::where('student_id', $studentId)->firstOrFail();
        $this->assertSame(EnrollmentStatus::Active, $enrollment->status);
        $this->assertSame(12, $enrollment->roll_no);

        // Application marked approved with reviewer stamped.
        $application->refresh();
        $this->assertSame(AdmissionStatus::Approved, $application->status);
        $this->assertNotNull($application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);

        // Credentials job dispatched for the student.
        Queue::assertPushed(SendCredentials::class, fn ($job) => $job->user->id === $user->id && $job->role === 'Student');
    }

    public function test_approve_with_parent_creates_link_and_parent_credentials(): void
    {
        Queue::fake();

        $application = $this->makeApplication([
            'father_name_en' => 'Abdul Karim',
            'father_mobile' => '01722222222',
        ]);

        $response = $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload([
                'create_parent_account' => true,
                'parent_relation' => 'father',
            ]))
            ->assertOk()
            ->assertJsonPath('data.parent_created', true);

        $studentId = $response->json('data.student.id');

        $parent = ParentProfile::where('relation', 'father')->firstOrFail();
        $this->assertSame('Abdul Karim', $parent->name);
        $this->assertSame('01722222222', $parent->phone);
        $this->assertSame($this->branch->id, $parent->branch_id);

        // Pivot link exists (so parent /me/students will work in Phase 4).
        $this->assertDatabaseHas('parent_student', [
            'parent_id' => $parent->id,
            'student_id' => $studentId,
        ]);
        $this->assertTrue($parent->students()->whereKey($studentId)->exists());

        // The father owns the shared mobile (users.phone is unique), so the
        // parent login claims it and the student's login phone is left null.
        $parentUser = User::find($parent->user_id);
        $this->assertTrue($parentUser->hasRole('parent'));
        $this->assertSame('01722222222', $parentUser->phone);
        $this->assertNull(Student::find($studentId)->user->phone);

        // Credentials dispatched for both student and parent.
        Queue::assertPushed(SendCredentials::class, fn ($job) => $job->role === 'Student');
        Queue::assertPushed(SendCredentials::class, fn ($job) => $job->role === 'Parent' && $job->user->id === $parentUser->id);
    }

    public function test_mid_transaction_failure_rolls_back_everything(): void
    {
        Queue::fake();

        $application = $this->makeApplication();

        // Point the media disk at an undefined disk so copying the photo throws
        // mid-transaction, after the user/student/enrollment rows are written.
        $application->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photo');
        config(['media-library.disk_name' => 'does-not-exist']);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload())
            ->assertStatus(500);

        // No rows survive the rollback.
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('parents', 0);
        $this->assertSame(0, User::role('student')->count());

        $application->refresh();
        $this->assertSame(AdmissionStatus::Pending, $application->status);

        Queue::assertNotPushed(SendCredentials::class);
    }

    public function test_duplicate_roll_in_section_is_rejected(): void
    {
        $first = $this->makeApplication();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$first->public_id}/approve", $this->approvePayload(['roll_no' => 12, 'admission_no' => 'STU-JA-2026-0001']))
            ->assertOk();

        $second = $this->makeApplication();
        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$second->public_id}/approve", $this->approvePayload(['roll_no' => 12]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('roll_no');
    }

    public function test_roll_number_above_storage_limit_is_rejected(): void
    {
        $application = $this->makeApplication();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload([
                'roll_no' => 405060,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('roll_no');

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_duplicate_admission_no_is_rejected(): void
    {
        $first = $this->makeApplication();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$first->public_id}/approve", $this->approvePayload(['roll_no' => 1, 'admission_no' => 'STU-JA-2026-0001']))
            ->assertOk();

        $second = $this->makeApplication();
        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$second->public_id}/approve", $this->approvePayload(['roll_no' => 2, 'admission_no' => 'STU-JA-2026-0001']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('admission_no');
    }

    public function test_section_not_of_class_is_rejected(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'is_active' => true]);
        $foreignSection = Section::factory()->create(['class_id' => $otherClass->id, 'name' => 'Z']);

        $application = $this->makeApplication();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload(['section_id' => $foreignSection->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('section_id');
    }

    public function test_approve_requires_permission(): void
    {
        $application = $this->makeApplication();

        $this->withToken($this->tokenForRole('accountant'))
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload())
            ->assertStatus(403);
    }

    public function test_approve_blocked_after_review(): void
    {
        $token = $this->tokenForRole('admin');
        $application = $this->makeApplication();

        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload())
            ->assertOk();

        // A reviewed (approved) application cannot be reviewed again.
        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$application->public_id}/reject", ['rejection_reason' => 'Changed my mind'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Application has already been reviewed.');
    }

    public function test_reject_happy_path_and_blocks_later_approval(): void
    {
        $token = $this->tokenForRole('admin');
        $application = $this->makeApplication();

        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$application->public_id}/reject", ['rejection_reason' => 'Incomplete documents'])
            ->assertOk()
            ->assertJsonPath('message', 'Application rejected.');

        $application->refresh();
        $this->assertSame(AdmissionStatus::Rejected, $application->status);
        $this->assertSame('Incomplete documents', $application->rejection_reason);
        $this->assertNotNull($application->reviewed_by);

        // A rejected application can never be approved later (409).
        $this->withToken($token)
            ->postJson("/api/v1/admissions/{$application->public_id}/approve", $this->approvePayload())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Application has already been reviewed.');

        // Public status endpoint now reflects the rejection (Task 3.3).
        $this->getJson("/api/v1/public/admissions/{$application->application_no}/status?date_of_birth={$application->date_of_birth->format('Y-m-d')}")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_reject_requires_reason(): void
    {
        $application = $this->makeApplication();

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/admissions/{$application->public_id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_reason');
    }

    public function test_cross_branch_application_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create(['code' => 'MP']);
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id, 'is_active' => true]);
        $foreign = $this->makeApplication(['desired_class_id' => $otherClass->id], $otherBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson("/api/v1/admissions/{$foreign->public_id}/approve", $this->approvePayload())
            ->assertStatus(404);
    }
}
