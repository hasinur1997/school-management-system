<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StudentStatus;
use App\Jobs\SendCredentials;
use App\Mail\CredentialsMail;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentEndpointsTest extends TestCase
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

    /**
     * Create a student (with login + student role) optionally enrolled in the
     * current session under the given class/section/roll.
     */
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

    public function test_index_filters_search_and_pagination_without_n_plus_one(): void
    {
        Model::preventLazyLoading();

        $class7 = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $section = Section::factory()->create(['class_id' => $class7->id, 'name' => 'A']);
        $class8 = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 8']);
        $section8 = Section::factory()->create(['class_id' => $class8->id]);

        $this->makeStudent(
            ['name_en' => 'Rahima Khatun', 'admission_no' => 'MP-2026-0009', 'status' => StudentStatus::Active],
            enrollment: ['class_id' => $class7->id, 'section_id' => $section->id, 'roll_no' => 12],
        );
        $this->makeStudent(
            ['name_en' => 'Karim Mia', 'admission_no' => 'MP-2026-0010', 'status' => StudentStatus::Active],
            enrollment: ['class_id' => $class8->id, 'section_id' => $section8->id, 'roll_no' => 3],
        );
        $this->makeStudent(
            ['name_en' => 'Inactive Person', 'admission_no' => 'MP-2026-0011', 'status' => StudentStatus::Inactive],
            enrollment: ['class_id' => $class7->id, 'section_id' => $section->id, 'roll_no' => 20],
        );

        $token = $this->tokenForRole('admin');

        // class filter
        $this->withToken($token)
            ->getJson("/api/v1/students?class_id={$class7->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // status filter
        $this->withToken($token)
            ->getJson('/api/v1/students?status=active')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // search across name + compact shape with current enrollment
        $this->withToken($token)
            ->getJson('/api/v1/students?search=Rahima')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name_en', 'Rahima Khatun')
            ->assertJsonPath('data.0.admission_no', 'MP-2026-0009')
            ->assertJsonPath('data.0.class', 'Class 7')
            ->assertJsonPath('data.0.section', 'A')
            ->assertJsonPath('data.0.roll_no', 12);

        // pagination
        $this->withToken($token)
            ->getJson('/api/v1/students?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 3);

        Model::preventLazyLoading(false);
    }

    public function test_student_sees_own_profile_but_not_others_and_cannot_list(): void
    {
        $student = $this->makeStudent();
        $other = $this->makeStudent();

        $token = $student->user->createToken('web')->plainTextToken;

        // own show
        $this->withToken($token)
            ->getJson("/api/v1/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $student->id);

        // another student → 404 (policy denies, existence hidden)
        $this->withToken($token)
            ->getJson("/api/v1/students/{$other->id}")
            ->assertStatus(404);

        // cannot access the list (lacks student.view)
        $this->withToken($token)
            ->getJson('/api/v1/students')
            ->assertStatus(403);
    }

    public function test_staff_show_returns_full_profile_with_enrollments(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student = $this->makeStudent(
            enrollment: ['class_id' => $class->id, 'section_id' => $section->id, 'roll_no' => 5],
        );

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/students/{$student->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $student->id)
            ->assertJsonPath('data.permanent_district', $student->permanent_district)
            ->assertJsonCount(1, 'data.enrollments')
            ->assertJsonPath('data.enrollments.0.roll_no', 5);
    }

    public function test_show_unknown_id_returns_404(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->getJson('/api/v1/students/999999')
            ->assertStatus(404);
    }

    public function test_update_happy_path(): void
    {
        $student = $this->makeStudent(['name_en' => 'Old Name']);

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$student->id}", $this->validUpdatePayload(['name_en' => 'New Name']))
            ->assertOk()
            ->assertJsonPath('data.name_en', 'New Name');

        $this->assertDatabaseHas('students', ['id' => $student->id, 'name_en' => 'New Name']);
    }

    public function test_update_rejects_admission_no_change(): void
    {
        $student = $this->makeStudent();

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$student->id}", $this->validUpdatePayload(['admission_no' => 'HACK-0001']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['admission_no']);
    }

    public function test_status_can_go_inactive_but_tc_is_rejected(): void
    {
        $student = $this->makeStudent();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->patchJson("/api/v1/students/{$student->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');

        $this->withToken($token)
            ->patchJson("/api/v1/students/{$student->id}/status", ['status' => 'tc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_photo_upload_then_replacement_and_invalid_file(): void
    {
        Storage::fake('public');
        $student = $this->makeStudent();
        $token = $this->tokenForRole('admin');

        $first = $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/photo", [
                'photo' => UploadedFile::fake()->image('first.jpg'),
            ])
            ->assertOk();
        $this->assertNotNull($first->json('data.photo_url'));
        $this->assertSame(1, $student->fresh()->getMedia('photo')->count());

        // Replacement keeps a single file.
        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/photo", [
                'photo' => UploadedFile::fake()->image('second.png'),
            ])
            ->assertOk();
        $this->assertSame(1, $student->fresh()->getMedia('photo')->count());

        // Wrong type rejected.
        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/photo", [
                'photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);

        // Oversize rejected.
        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/photo", [
                'photo' => UploadedFile::fake()->image('big.jpg')->size(3000),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_cross_branch_student_returns_404(): void
    {
        $other = Branch::factory()->create();
        $student = $this->makeStudent([], $other);
        $token = $this->tokenForRole('admin');

        $this->withToken($token)->getJson("/api/v1/students/{$student->id}")->assertStatus(404);
        $this->withToken($token)
            ->putJson("/api/v1/students/{$student->id}", $this->validUpdatePayload())
            ->assertStatus(404);
    }

    public function test_update_can_change_birth_reg_no(): void
    {
        $student = $this->makeStudent(['birth_reg_no' => '1990111111111111111']);

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$student->public_id}", $this->validUpdatePayload([
                'birth_reg_no' => '2020999999999999999',
            ]))
            ->assertOk()
            ->assertJsonPath('data.birth_reg_no', '2020999999999999999');

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'birth_reg_no' => '2020999999999999999',
        ]);
    }

    public function test_update_birth_reg_no_must_be_unique(): void
    {
        $taken = $this->makeStudent(['birth_reg_no' => '1111111111111111111']);
        $student = $this->makeStudent(['birth_reg_no' => '2222222222222222222']);

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$student->public_id}", $this->validUpdatePayload([
                'birth_reg_no' => $taken->birth_reg_no,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['birth_reg_no']);
    }

    public function test_update_can_change_student_email(): void
    {
        $student = $this->makeStudent();

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$student->public_id}", $this->validUpdatePayload([
                'student_email' => 'student.updated@example.test',
            ]))
            ->assertOk()
            ->assertJsonPath('data.student_email', 'student.updated@example.test');

        $this->assertDatabaseHas('users', [
            'id' => $student->user_id,
            'email' => 'student.updated@example.test',
        ]);
    }

    public function test_update_enrollment_happy_path(): void
    {
        $class7 = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $sectionA = Section::factory()->create(['class_id' => $class7->id]);
        $class8 = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $sectionB = Section::factory()->create(['class_id' => $class8->id]);

        $student = $this->makeStudent();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $class7->id,
            'section_id' => $sectionA->id,
            'roll_no' => 5,
            'status' => 'active',
        ]);

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$student->public_id}/enrollments/{$enrollment->public_id}", [
                'session_id' => $this->session->public_id,
                'class_id' => $class8->public_id,
                'section_id' => $sectionB->public_id,
                'roll_no' => 9,
                'status' => 'promoted',
            ])
            ->assertOk()
            ->assertJsonPath('data.roll_no', 9)
            ->assertJsonPath('data.class_id', $class8->public_id)
            ->assertJsonPath('data.status', 'promoted');

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'class_id' => $class8->id,
            'section_id' => $sectionB->id,
            'roll_no' => 9,
            'status' => 'promoted',
        ]);
    }

    public function test_update_enrollment_rejects_section_outside_class(): void
    {
        $class7 = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $sectionA = Section::factory()->create(['class_id' => $class7->id]);
        $class8 = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $foreignSection = Section::factory()->create(['class_id' => $class8->id]);

        $student = $this->makeStudent();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $class7->id,
            'section_id' => $sectionA->id,
            'roll_no' => 5,
        ]);

        // class_id = class7 but section belongs to class8 → 422 on section_id.
        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$student->public_id}/enrollments/{$enrollment->public_id}", [
                'session_id' => $this->session->public_id,
                'class_id' => $class7->public_id,
                'section_id' => $foreignSection->public_id,
                'roll_no' => 5,
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['section_id']);
    }

    public function test_update_enrollment_rejects_duplicate_roll(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        $studentA = $this->makeStudent();
        $enrollmentA = Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 5,
        ]);

        // Another student already holds roll 7 in the same section/session.
        $studentB = $this->makeStudent();
        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 7,
        ]);

        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$studentA->public_id}/enrollments/{$enrollmentA->public_id}", [
                'session_id' => $this->session->public_id,
                'class_id' => $class->public_id,
                'section_id' => $section->public_id,
                'roll_no' => 7,
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roll_no']);
    }

    public function test_update_enrollment_of_another_student_returns_404(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        $studentA = $this->makeStudent();
        $studentB = $this->makeStudent();
        $enrollmentB = Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 5,
        ]);

        // enrollmentB belongs to studentB, addressed under studentA → 404.
        $this->withToken($this->tokenForRole('admin'))
            ->putJson("/api/v1/students/{$studentA->public_id}/enrollments/{$enrollmentB->public_id}", [
                'session_id' => $this->session->public_id,
                'class_id' => $class->public_id,
                'section_id' => $section->public_id,
                'roll_no' => 6,
                'status' => 'active',
            ])
            ->assertStatus(404);
    }

    public function test_store_creates_student_login_and_active_enrollment(): void
    {
        Queue::fake();

        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        $response = $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/students', $this->validStorePayload([
                'class_id' => $class->public_id,
                'section_id' => $section->public_id,
                'roll_no' => 7,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.name_en', 'Rahim Uddin')
            ->assertJsonPath('data.status', 'active');

        // Admission number was auto-generated and the profile is in our branch.
        $this->assertNotNull($response->json('data.admission_no'));

        $student = Student::firstWhere('name_en', 'Rahim Uddin');
        $this->assertSame($this->branch->id, $student->branch_id);
        $this->assertNotNull($student->user_id);

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 7,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('model_has_roles', ['model_id' => $student->user_id]);
        Queue::assertPushed(SendCredentials::class, fn ($job) => $job->role === 'Student');
    }

    public function test_store_with_parent_creates_link_and_parent_credentials(): void
    {
        Queue::fake();

        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/students', $this->validStorePayload([
                'class_id' => $class->public_id,
                'section_id' => $section->public_id,
                'roll_no' => 7,
                'create_parent_account' => true,
                'parent_relation' => 'father',
            ]))
            ->assertStatus(201);

        $student = Student::firstWhere('name_en', 'Rahim Uddin');
        $this->assertDatabaseHas('parent_student', ['student_id' => $student->id]);
        Queue::assertPushed(SendCredentials::class, fn ($job) => $job->role === 'Parent');
    }

    public function test_store_with_email_recipients_sends_student_and_parent_credentials(): void
    {
        Mail::fake();

        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/students', $this->validStorePayload([
                'class_id' => $class->public_id,
                'section_id' => $section->public_id,
                'roll_no' => 7,
                'student_email' => 'student@example.test',
                'create_parent_account' => true,
                'parent_relation' => 'father',
                'parent_email' => 'parent@example.test',
            ]))
            ->assertStatus(201)
            ->assertJsonPath('data.student_email', 'student@example.test');

        Mail::assertSent(CredentialsMail::class, function (CredentialsMail $mail): bool {
            return $mail->hasTo('student@example.test')
                && $mail->role === 'Student'
                && $mail->identifier === 'student@example.test';
        });

        Mail::assertSent(CredentialsMail::class, function (CredentialsMail $mail): bool {
            return $mail->hasTo('parent@example.test')
                && $mail->role === 'Parent'
                && $mail->identifier === '01712345678';
        });
    }

    public function test_store_rejects_duplicate_roll_in_same_class_section(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);
        Enrollment::factory()->create([
            'student_id' => $this->makeStudent()->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_no' => 7,
        ]);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/students', $this->validStorePayload([
                'class_id' => $class->public_id,
                'section_id' => $section->public_id,
                'roll_no' => 7,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roll_no']);
    }

    public function test_store_rejects_section_outside_class(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $foreignSection = Section::factory()->create(['class_id' => $otherClass->id]);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/students', $this->validStorePayload([
                'class_id' => $class->public_id,
                'section_id' => $foreignSection->public_id,
                'roll_no' => 7,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['section_id']);
    }

    public function test_store_forbidden_without_create_permission(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        // teacher holds student.view but not student.create.
        $this->withToken($this->tokenForRole('teacher'))
            ->postJson('/api/v1/students', $this->validStorePayload([
                'class_id' => $class->public_id,
                'section_id' => $section->public_id,
                'roll_no' => 7,
            ]))
            ->assertStatus(403);
    }

    /**
     * A full, valid POST /students payload; merge overrides on top. The caller
     * supplies the academic ids (public ids) and roll for the enrollment.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validStorePayload(array $overrides = []): array
    {
        return array_merge($this->validUpdatePayload([
            'birth_reg_no' => '1990111111111111111',
            'session_id' => $this->session->public_id,
            'create_parent_account' => false,
        ]), $overrides);
    }

    /**
     * A full, valid PUT payload; merge overrides on top.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validUpdatePayload(array $overrides = []): array
    {
        return array_merge([
            'name_bn' => 'রহিম',
            'name_en' => 'Rahim Uddin',
            'father_name_bn' => 'করিম',
            'father_name_en' => 'Karim Uddin',
            'mother_name_bn' => 'আমেনা',
            'mother_name_en' => 'Amena Begum',
            'present_village' => 'Village',
            'present_post_office' => 'Post',
            'present_upazila' => 'Upazila',
            'present_district' => 'District',
            'present_division' => 'Division',
            'permanent_village' => 'গ্রাম',
            'permanent_post_office' => 'ডাকঘর',
            'permanent_upazila' => 'উপজেলা',
            'permanent_district' => 'জেলা',
            'permanent_division' => 'বিভাগ',
            'father_mobile' => '01712345678',
            'date_of_birth' => '2014-01-01',
            'religion' => 'Islam',
            'nationality' => 'Bangladeshi',
        ], $overrides);
    }
}
