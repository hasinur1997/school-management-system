<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceMonthlyTest extends TestCase
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

    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    private function makeStudent(?Branch $branch = null): Student
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('student');

        return Student::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);
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

    /**
     * Enroll the student in the test section and return the active enrollment.
     */
    private function enroll(Student $student): Enrollment
    {
        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => 9,
            'status' => EnrollmentStatus::Active,
        ]);
    }

    /**
     * Mark a single day's attendance for the enrollment.
     */
    private function mark(Enrollment $enrollment, string $date, AttendanceStatus $status): void
    {
        StudentAttendance::factory()->create([
            'enrollment_id' => $enrollment->id,
            'date' => $date,
            'status' => $status,
        ]);
    }

    public function test_summary_counts_and_day_list_for_the_month(): void
    {
        $student = $this->makeStudent();
        $enrollment = $this->enroll($student);

        $this->mark($enrollment, '2026-06-01', AttendanceStatus::Present);
        $this->mark($enrollment, '2026-06-02', AttendanceStatus::Absent);
        $this->mark($enrollment, '2026-06-03', AttendanceStatus::Present);
        $this->mark($enrollment, '2026-06-04', AttendanceStatus::Late);

        // A mark in a different month must not leak into June's totals.
        $this->mark($enrollment, '2026-07-01', AttendanceStatus::Absent);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/students/{$student->id}/attendance?month=6&year=2026")
            ->assertOk()
            ->assertJsonPath('data.month', 6)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.summary.present', 2)
            ->assertJsonPath('data.summary.absent', 1)
            ->assertJsonPath('data.summary.late', 1)
            ->assertJsonPath('data.summary.leave', 0)
            ->assertJsonPath('data.summary.working_days', 4)
            ->assertJsonCount(4, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-06-01')
            ->assertJsonPath('data.days.0.status', 'present')
            ->assertJsonPath('data.days.1.date', '2026-06-02')
            ->assertJsonPath('data.days.1.status', 'absent');
    }

    public function test_self_and_linked_parent_get_200_unrelated_get_404(): void
    {
        // Create every actor up front: creating a Student/ParentProfile stamps
        // branch_id via auth()->user(), which re-resolves the sanctum guard off
        // the leftover request token — so all model creation must precede the
        // sequence of authenticated requests (each reset with forgetGuards).
        $student = $this->makeStudent();
        $enrollment = $this->enroll($student);
        $this->mark($enrollment, '2026-06-01', AttendanceStatus::Present);

        $linkedParent = $this->makeParent();
        $linkedParent->students()->attach($student->id);
        $otherStudent = $this->makeStudent();
        $unrelatedParent = $this->makeParent();

        $selfToken = $student->user->createToken('web')->plainTextToken;
        $linkedParentToken = $linkedParent->user->createToken('web')->plainTextToken;
        $otherStudentToken = $otherStudent->user->createToken('web')->plainTextToken;
        $unrelatedParentToken = $unrelatedParent->user->createToken('web')->plainTextToken;

        // The student sees their own sheet.
        $this->app['auth']->forgetGuards();
        $this->withToken($selfToken)
            ->getJson("/api/v1/students/{$student->id}/attendance?month=6&year=2026")
            ->assertOk()
            ->assertJsonPath('data.summary.present', 1);

        // A linked parent sees it too.
        $this->app['auth']->forgetGuards();
        $this->withToken($linkedParentToken)
            ->getJson("/api/v1/students/{$student->id}/attendance?month=6&year=2026")
            ->assertOk()
            ->assertJsonPath('data.summary.present', 1);

        // An unrelated student → 404 (existence hidden).
        $this->app['auth']->forgetGuards();
        $this->withToken($otherStudentToken)
            ->getJson("/api/v1/students/{$student->id}/attendance?month=6&year=2026")
            ->assertStatus(404);

        // An unlinked parent → 404.
        $this->app['auth']->forgetGuards();
        $this->withToken($unrelatedParentToken)
            ->getJson("/api/v1/students/{$student->id}/attendance?month=6&year=2026")
            ->assertStatus(404);
    }

    public function test_me_attendance_is_student_only(): void
    {
        $student = $this->makeStudent();
        $enrollment = $this->enroll($student);
        $this->mark($enrollment, '2026-06-01', AttendanceStatus::Leave);

        $this->withToken($student->user->createToken('web')->plainTextToken)
            ->getJson('/api/v1/me/attendance?month=6&year=2026')
            ->assertOk()
            ->assertJsonPath('data.summary.leave', 1)
            ->assertJsonPath('data.days.0.status', 'leave');

        // A non-student (admin) has no student profile → 403.
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenForRole('admin'))
            ->getJson('/api/v1/me/attendance?month=6&year=2026')
            ->assertStatus(403);
    }

    public function test_invalid_month_is_422(): void
    {
        $student = $this->makeStudent();
        $this->enroll($student);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/students/{$student->id}/attendance?month=13&year=2026")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);
    }

    public function test_month_and_year_default_to_current(): void
    {
        $student = $this->makeStudent();
        $enrollment = $this->enroll($student);
        $this->mark($enrollment, today()->toDateString(), AttendanceStatus::Present);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/students/{$student->id}/attendance")
            ->assertOk()
            ->assertJsonPath('data.month', (int) today()->month)
            ->assertJsonPath('data.year', (int) today()->year)
            ->assertJsonPath('data.summary.present', 1);
    }

    public function test_out_of_branch_student_is_404(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreign = $this->makeStudent($otherBranch);

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/students/{$foreign->id}/attendance?month=6&year=2026")
            ->assertStatus(404);
    }
}
