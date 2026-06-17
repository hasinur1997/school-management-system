<?php

namespace Tests\Feature\Api\V1;

use App\Enums\AssetStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TeacherAttendanceStatus;
use App\Models\AcademicSession;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EntityReportsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $accountant;

    private string $token;

    private AcademicSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-17');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['name' => 'Madani PathShala']);
        $this->accountant = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('accountant');
        $this->token = $this->accountant->createToken('web')->plainTextToken;

        $this->session = AcademicSession::factory()->current()->create([
            'name' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function student(string $status, string $admittedAt): Student
    {
        return Student::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => $status,
            'admitted_at' => $admittedAt,
        ]);
    }

    private function enroll(Student $student, SchoolClass $class, Section $section): Enrollment
    {
        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
        ]);
    }

    public function test_students_report_breaks_down_by_status_class_and_new_admissions(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7', 'numeric_level' => 7]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        // Two active (admitted this year), one tc, one inactive.
        $this->enroll($this->student('active', '2026-03-10'), $class, $section);
        $this->enroll($this->student('active', '2026-04-12'), $class, $section);
        $this->student('tc', '2025-08-01');
        $this->student('inactive', '2024-09-01');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/students?period=yearly')
            ->assertOk()
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.by_status.active', 2)
            ->assertJsonPath('data.by_status.tc', 1)
            ->assertJsonPath('data.by_status.inactive', 1)
            // tc + inactive admitted before 2026; only the two active count.
            ->assertJsonPath('data.new_admissions', 2);

        $byClass = collect($response->json('data.by_class'));
        $this->assertSame(2, $byClass->firstWhere('class', 'Class 7')['count']);
    }

    public function test_students_new_admissions_count_boundary_dates(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7', 'numeric_level' => 7]);
        $section = Section::factory()->create(['class_id' => $class->id]);

        $this->enroll($this->student('active', '2026-01-01'), $class, $section); // start edge — counts
        $this->enroll($this->student('active', '2026-12-31'), $class, $section); // end edge — counts
        $this->student('active', '2025-12-31'); // just before — excluded

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/students?period=custom&from=2026-01-01&to=2026-12-31')
            ->assertOk()
            ->assertJsonPath('data.new_admissions', 2);
    }

    public function test_teachers_report_breaks_down_status_and_attendance(): void
    {
        $present = Teacher::factory()->create(['branch_id' => $this->branch->id]);
        $absentTeacher = Teacher::factory()->inactive()->create(['branch_id' => $this->branch->id]);

        TeacherAttendance::factory()->create([
            'teacher_id' => $present->id,
            'date' => '2026-06-02',
            'status' => TeacherAttendanceStatus::Present,
        ]);
        TeacherAttendance::factory()->create([
            'teacher_id' => $present->id,
            'date' => '2026-06-03',
            'status' => TeacherAttendanceStatus::Late,
        ]);
        TeacherAttendance::factory()->create([
            'teacher_id' => $absentTeacher->id,
            'date' => '2026-06-04',
            'status' => TeacherAttendanceStatus::Absent,
        ]);
        // Outside the monthly window — excluded.
        TeacherAttendance::factory()->create([
            'teacher_id' => $present->id,
            'date' => '2026-05-30',
            'status' => TeacherAttendanceStatus::Leave,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/teachers?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.by_status.active', 1)
            ->assertJsonPath('data.by_status.inactive', 1)
            ->assertJsonPath('data.attendance.present', 1)
            ->assertJsonPath('data.attendance.late', 1)
            ->assertJsonPath('data.attendance.absent', 1)
            ->assertJsonPath('data.attendance.leave', 0);
    }

    public function test_assets_report_total_value_excludes_disposed_and_counts_additions_in_range(): void
    {
        Asset::factory()->create(['branch_id' => $this->branch->id, 'status' => AssetStatus::InUse, 'value' => '10000.00', 'purchase_date' => '2026-02-01']);
        Asset::factory()->create(['branch_id' => $this->branch->id, 'status' => AssetStatus::Damaged, 'value' => '2000.00', 'purchase_date' => '2025-01-01']);
        Asset::factory()->create(['branch_id' => $this->branch->id, 'status' => AssetStatus::Disposed, 'value' => '5000.00', 'purchase_date' => '2026-03-01']);

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/assets?period=yearly')
            ->assertOk()
            // 10000 + 2000; disposed 5000 excluded.
            ->assertJsonPath('data.total_value', '12000.00')
            ->assertJsonPath('data.count', 3)
            ->assertJsonPath('data.by_status.in_use.value', '10000.00')
            ->assertJsonPath('data.by_status.disposed.value', '5000.00')
            // Additions in 2026: in_use (Feb) + disposed (Mar); damaged is 2025.
            ->assertJsonPath('data.additions.count', 2)
            ->assertJsonPath('data.additions.value', '15000.00');
    }

    public function test_fees_report_reconciles_invoiced_collected_and_outstanding(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7', 'numeric_level' => 7]);
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student = $this->student('active', '2026-01-05');
        $enrollment = $this->enroll($student, $class, $section);

        $this->invoice($student, $enrollment, 6, '1500.00', '1500.00', InvoiceStatus::Paid);
        $this->invoice($student, $enrollment, 7, '1500.00', '500.00', InvoiceStatus::Partial);
        $this->invoice($student, $enrollment, 8, '1500.00', '0.00', InvoiceStatus::Unpaid);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/fees?period=yearly')
            ->assertOk()
            ->assertJsonPath('data.totals.invoiced', '4500.00')
            ->assertJsonPath('data.totals.collected', '2000.00')
            ->assertJsonPath('data.totals.outstanding', '2500.00');

        // outstanding = invoiced − collected, per month.
        $byMonth = collect($response->json('data.by_month'));
        $june = $byMonth->firstWhere('month', '2026-06');
        $this->assertSame('1500.00', $june['invoiced']);
        $this->assertSame('1500.00', $june['collected']);
        $this->assertSame('0.00', $june['outstanding']);

        $july = $byMonth->firstWhere('month', '2026-07');
        $this->assertSame('1000.00', $july['outstanding']);

        $byClass = collect($response->json('data.by_class'));
        $class7 = $byClass->firstWhere('class', 'Class 7');
        $this->assertSame('4500.00', $class7['invoiced']);
        $this->assertSame('2000.00', $class7['collected']);
    }

    public function test_fees_report_aggregates_in_sql(): void
    {
        $class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'numeric_level' => 7]);
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student = $this->student('active', '2026-01-05');
        $enrollment = $this->enroll($student, $class, $section);
        $this->invoice($student, $enrollment, 6, '1500.00', '1500.00', InvoiceStatus::Paid);

        DB::enableQueryLog();

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/fees?period=yearly')
            ->assertOk();

        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertStringContainsStringIgnoringCase('group by', $queries);
        $this->assertStringContainsStringIgnoringCase('sum(', $queries);
    }

    public function test_branch_scoped_reports_exclude_other_branches(): void
    {
        $other = Branch::factory()->create();
        $this->student('active', '2026-02-01');
        Student::factory()->create(['branch_id' => $other->id, 'status' => 'active', 'admitted_at' => '2026-02-01']);

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/students?period=yearly')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_teacher_without_report_view_is_forbidden(): void
    {
        $teacher = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $teacher->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/reports/students?period=monthly')
            ->assertForbidden();
    }

    public function test_custom_period_requires_from_and_to(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/reports/fees?period=custom')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);
    }

    private function invoice(Student $student, Enrollment $enrollment, int $month, string $amount, string $paid, InvoiceStatus $status): Invoice
    {
        return Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'month' => $month,
            'year' => 2026,
            'amount' => $amount,
            'paid_amount' => $paid,
            'status' => $status,
        ]);
    }
}
