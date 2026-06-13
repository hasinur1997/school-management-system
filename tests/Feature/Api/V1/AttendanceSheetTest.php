<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceSheetTest extends TestCase
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

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create(['name' => '2026']);
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $this->section = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'A']);
    }

    private function token(string $role = 'admin'): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * Enroll a fresh student in the test section with the given roll/status.
     */
    private function enroll(int $rollNo, string $nameEn, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'name_en' => $nameEn,
        ]);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $rollNo,
            'status' => $status,
        ]);
    }

    public function test_sheet_with_no_records_returns_all_null_statuses(): void
    {
        $this->enroll(1, 'Karim');
        $this->enroll(2, 'Rahima');

        $this->withToken($this->token())
            ->getJson("/api/v1/attendance/sheet?class_id={$this->class->id}&section_id={$this->section->id}&date=2026-06-11")
            ->assertOk()
            ->assertJsonPath('data.date', '2026-06-11')
            ->assertJsonPath('data.class', 'Class 7')
            ->assertJsonPath('data.section', 'A')
            ->assertJsonCount(2, 'data.students')
            ->assertJsonPath('data.students.0.roll_no', 1)
            ->assertJsonPath('data.students.0.name_en', 'Karim')
            ->assertJsonPath('data.students.0.status', null)
            ->assertJsonPath('data.students.1.status', null);
    }

    public function test_sheet_returns_existing_marks_when_partially_taken(): void
    {
        $karim = $this->enroll(1, 'Karim');
        $rahima = $this->enroll(2, 'Rahima');

        StudentAttendance::factory()->create([
            'enrollment_id' => $karim->id,
            'date' => '2026-06-11',
            'status' => AttendanceStatus::Present,
        ]);

        // A mark on a different date must not leak into this sheet.
        StudentAttendance::factory()->create([
            'enrollment_id' => $rahima->id,
            'date' => '2026-06-10',
            'status' => AttendanceStatus::Absent,
        ]);

        $this->withToken($this->token())
            ->getJson("/api/v1/attendance/sheet?class_id={$this->class->id}&section_id={$this->section->id}&date=2026-06-11")
            ->assertOk()
            ->assertJsonPath('data.students.0.status', 'present')
            ->assertJsonPath('data.students.1.status', null);
    }

    public function test_tc_and_inactive_enrollments_are_excluded_from_roster(): void
    {
        $this->enroll(1, 'Karim');
        $this->enroll(2, 'TransferredOut', EnrollmentStatus::Tc);
        $this->enroll(3, 'Promoted', EnrollmentStatus::Promoted);

        $response = $this->withToken($this->token())
            ->getJson("/api/v1/attendance/sheet?class_id={$this->class->id}&section_id={$this->section->id}&date=2026-06-11")
            ->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.name_en', 'Karim');

        $names = array_column($response->json('data.students'), 'name_en');
        $this->assertNotContains('TransferredOut', $names);
        $this->assertNotContains('Promoted', $names);
    }

    public function test_date_defaults_to_today_when_omitted(): void
    {
        $enrollment = $this->enroll(1, 'Karim');
        StudentAttendance::factory()->create([
            'enrollment_id' => $enrollment->id,
            'date' => today(),
            'status' => AttendanceStatus::Late,
        ]);

        $this->withToken($this->token())
            ->getJson("/api/v1/attendance/sheet?class_id={$this->class->id}&section_id={$this->section->id}")
            ->assertOk()
            ->assertJsonPath('data.date', today()->toDateString())
            ->assertJsonPath('data.students.0.status', 'late');
    }

    public function test_roster_loads_without_n_plus_one(): void
    {
        Model::preventLazyLoading();

        $this->enroll(1, 'Karim');
        $this->enroll(2, 'Rahima');

        $this->withToken($this->token())
            ->getJson("/api/v1/attendance/sheet?class_id={$this->class->id}&section_id={$this->section->id}&date=2026-06-11")
            ->assertOk()
            ->assertJsonCount(2, 'data.students');

        Model::preventLazyLoading(false);
    }

    public function test_missing_class_or_section_is_422(): void
    {
        $this->withToken($this->token())
            ->getJson('/api/v1/attendance/sheet?date=2026-06-11')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['class_id', 'section_id']);
    }

    public function test_section_not_in_class_is_422(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 8']);

        $this->withToken($this->token())
            ->getJson("/api/v1/attendance/sheet?class_id={$otherClass->id}&section_id={$this->section->id}&date=2026-06-11")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['section_id']);
    }

    public function test_requires_attendance_create_permission(): void
    {
        $this->enroll(1, 'Karim');

        // Accountant holds no attendance permission.
        $this->withToken($this->token('accountant'))
            ->getJson("/api/v1/attendance/sheet?class_id={$this->class->id}&section_id={$this->section->id}")
            ->assertStatus(403);
    }
}
