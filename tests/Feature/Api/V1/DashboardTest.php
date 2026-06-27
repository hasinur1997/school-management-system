<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AdmissionStatus;
use App\Enums\AssetStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\TeacherAttendance;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    private function userForRole(string $role): User
    {
        return User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);
    }

    private function token(User $user): string
    {
        return $user->createToken('web')->plainTextToken;
    }

    private function makeStudent(?Branch $branch = null): Student
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('student');

        return Student::factory()->create(['branch_id' => $branch->id, 'user_id' => $user->id]);
    }

    private int $roll = 0;

    private function enroll(Student $student, ?Section $section = null): Enrollment
    {
        $section ??= $this->section;

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $section->class_id,
            'section_id' => $section->id,
            'roll_no' => ++$this->roll,
            'status' => EnrollmentStatus::Active,
        ]);
    }

    private function mark(Enrollment $enrollment, string $date, AttendanceStatus $status): void
    {
        StudentAttendance::factory()->create([
            'enrollment_id' => $enrollment->id,
            'date' => $date,
            'status' => $status,
        ]);
    }

    public function test_staff_dashboard_shape_and_figures(): void
    {
        $today = today()->toDateString();
        $thisMonth = now()->startOfMonth()->toDateString();

        // Today's attendance: 2 of 3 recorded marks count as present (present +
        // late) → 66.7%. The 4th student is left unmarked (counts toward the
        // head-count total, not the percentage).
        $this->mark($this->enroll($this->makeStudent()), $today, AttendanceStatus::Present);
        $this->mark($this->enroll($this->makeStudent()), $today, AttendanceStatus::Late);
        $this->mark($this->enroll($this->makeStudent()), $today, AttendanceStatus::Absent);
        $this->enroll($this->makeStudent());

        // Two pending admissions (an approved one must not count).
        AdmissionApplication::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'desired_class_id' => $this->class->id,
            'status' => AdmissionStatus::Pending,
        ]);
        AdmissionApplication::factory()->create([
            'branch_id' => $this->branch->id,
            'desired_class_id' => $this->class->id,
            'status' => AdmissionStatus::Approved,
        ]);

        // This month: income 152000, expense 90500, net 61500.
        Income::factory()->create(['branch_id' => $this->branch->id, 'amount' => '152000.00', 'date' => $thisMonth]);
        Expense::factory()->create(['branch_id' => $this->branch->id, 'amount' => '90500.00', 'date' => $thisMonth]);
        // A prior-month figure must not bleed into the month totals.
        Income::factory()->create(['branch_id' => $this->branch->id, 'amount' => '999.00', 'date' => now()->subMonthNoOverflow()->startOfMonth()->toDateString()]);

        // Unpaid invoice count = unpaid + partial (paid excluded).
        Invoice::factory()->count(2)->create(['branch_id' => $this->branch->id, 'status' => InvoiceStatus::Unpaid]);
        Invoice::factory()->create(['branch_id' => $this->branch->id, 'status' => InvoiceStatus::Partial]);
        Invoice::factory()->create(['branch_id' => $this->branch->id, 'status' => InvoiceStatus::Paid]);

        // Asset value = in_use + damaged, disposed excluded.
        Asset::factory()->create(['branch_id' => $this->branch->id, 'value' => '300000.00', 'status' => AssetStatus::InUse]);
        Asset::factory()->create(['branch_id' => $this->branch->id, 'value' => '85000.00', 'status' => AssetStatus::Damaged]);
        Asset::factory()->create(['branch_id' => $this->branch->id, 'value' => '50000.00', 'status' => AssetStatus::Disposed]);

        $this->withToken($this->token($this->userForRole('admin')))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role_view', 'staff')
            ->assertJsonPath('data.today_attendance_percent', 66.7)
            ->assertJsonPath('data.pending_admissions', 2)
            ->assertJsonPath('data.month.income', '152000.00')
            ->assertJsonPath('data.month.expense', '90500.00')
            ->assertJsonPath('data.month.net', '61500.00')
            ->assertJsonPath('data.unpaid_invoices', 3)
            ->assertJsonPath('data.totals.students', 4)
            ->assertJsonPath('data.totals.asset_value', '385000.00');
    }

    public function test_accountant_also_gets_the_staff_view(): void
    {
        $this->withToken($this->token($this->userForRole('accountant')))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role_view', 'staff');
    }

    public function test_staff_attendance_percent_is_zero_when_nothing_recorded(): void
    {
        $this->withToken($this->token($this->userForRole('admin')))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.today_attendance_percent', 0)
            ->assertJsonPath('data.totals.asset_value', '0.00');
    }

    public function test_teacher_dashboard_shape(): void
    {
        $teacherUser = $this->userForRole('teacher');
        $teacher = Teacher::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $teacherUser->id]);

        // A second roster whose attendance has already been taken today.
        $sectionB = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'B']);

        foreach ([$this->section, $sectionB] as $section) {
            TeacherAssignment::factory()->create([
                'teacher_id' => $teacher->id,
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $section->id,
            ]);
        }

        // Section B has a mark today; Section A does not → A is pending.
        $this->mark($this->enroll($this->makeStudent(), $sectionB), today()->toDateString(), AttendanceStatus::Present);

        TeacherAttendance::factory()->create([
            'teacher_id' => $teacher->id,
            'date' => today()->toDateString(),
        ]);

        $this->withToken($this->token($teacherUser))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role_view', 'teacher')
            ->assertJsonPath('data.checked_in', true)
            ->assertJsonCount(2, 'data.classes')
            ->assertJsonCount(1, 'data.attendance_pending')
            ->assertJsonPath('data.attendance_pending.0.class', 'Class 7')
            ->assertJsonPath('data.attendance_pending.0.section', 'A');
    }

    public function test_student_dashboard_shape(): void
    {
        $student = $this->makeStudent();
        $enrollment = $this->enroll($student);
        $this->mark($enrollment, now()->startOfMonth()->toDateString(), AttendanceStatus::Present);

        Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'status' => InvoiceStatus::Unpaid,
        ]);

        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'name' => 'Final 2026',
        ]);
        ExamResult::factory()->published()->create([
            'exam_id' => $exam->id,
            'enrollment_id' => $enrollment->id,
            'gpa' => '5.00',
            'grade' => 'A+',
            'is_passed' => true,
        ]);

        $this->withToken($this->token($student->user))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role_view', 'student')
            ->assertJsonPath('data.attendance.present', 1)
            ->assertJsonCount(1, 'data.unpaid_invoices')
            ->assertJsonPath('data.latest_result.exam', 'Final 2026')
            ->assertJsonPath('data.latest_result.grade', 'A+');
    }

    public function test_parent_sees_per_child_blocks_for_linked_children_only(): void
    {
        $linkedChild = $this->makeStudent();
        $this->enroll($linkedChild);
        $unlinkedChild = $this->makeStudent();

        $parentUser = $this->userForRole('parent');
        $parent = ParentProfile::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $parentUser->id]);
        $parent->students()->attach($linkedChild->id);

        $this->withToken($this->token($parentUser))
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.role_view', 'parent')
            ->assertJsonCount(1, 'data.children')
            ->assertJsonPath('data.children.0.student_id', $linkedChild->id)
            ->assertJsonPath('data.children.0.attendance.present', 0)
            ->assertJsonPath('data.children.0.latest_result', null);
    }

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }
}
