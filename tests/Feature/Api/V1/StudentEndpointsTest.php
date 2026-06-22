<?php

namespace Tests\Feature\Api\V1;

use App\Enums\StudentStatus;
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
