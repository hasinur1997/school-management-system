<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class IdCardSingleTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['code' => 'MP']);
        $this->session = AcademicSession::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $this->section = Section::factory()->create(['class_id' => $this->class->id]);
    }

    private function staffToken(?Branch $branch = null): string
    {
        $user = User::factory()->create(['branch_id' => ($branch ?? $this->branch)->id])->assignRole('admin');

        return $user->createToken('web')->plainTextToken;
    }

    private function seedStudent(StudentStatus $status = StudentStatus::Active, EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Active, ?Branch $branch = null): Student
    {
        $branch ??= $this->branch;

        $student = Student::factory()->create([
            'branch_id' => $branch->id,
            'name_en' => 'Rahima Khatun',
            'admission_no' => 'MP-2026-0009',
            'status' => $status,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 7,
            'status' => $enrollmentStatus,
        ]);

        return $student;
    }

    public function test_streams_a_pdf_for_an_active_student(): void
    {
        $student = $this->seedStudent();

        $response = $this->withToken($this->staffToken())
            ->get("/api/v1/students/{$student->id}/id-card");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'idcard-MP-2026-0009.pdf',
            $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_renders_with_a_photo_attached(): void
    {
        $student = $this->seedStudent();
        $student->addMedia(UploadedFile::fake()->image('photo.jpg', 200, 240))
            ->toMediaCollection('photo');

        $response = $this->withToken($this->staffToken())
            ->get("/api/v1/students/{$student->id}/id-card");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_tc_student_cannot_be_issued_a_card(): void
    {
        $student = $this->seedStudent(StudentStatus::Tc, EnrollmentStatus::Tc);

        $response = $this->withToken($this->staffToken())
            ->getJson("/api/v1/students/{$student->id}/id-card");

        $response->assertStatus(422)
            ->assertJson(['message' => 'Student has no active enrollment']);
    }

    public function test_cross_branch_student_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create(['code' => 'JA']);
        $student = $this->seedStudent(branch: $otherBranch);

        $response = $this->withToken($this->staffToken())
            ->getJson("/api/v1/students/{$student->id}/id-card");

        $response->assertNotFound();
    }
}
