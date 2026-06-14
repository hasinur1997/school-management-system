<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['code' => 'MP']);
        $this->session = AcademicSession::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $this->section = Section::factory()->create(['class_id' => $this->class->id]);

        $admin = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $this->adminToken = $admin->createToken('web')->plainTextToken;
    }

    /**
     * Create a student in the suite's branch with an enrollment in the suite's
     * class for the current session.
     */
    private function enrolledStudent(
        StudentStatus $studentStatus = StudentStatus::Active,
        EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Active,
        ?SchoolClass $class = null,
        ?User $user = null,
    ): Student {
        $class ??= $this->class;

        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'status' => $studentStatus,
            'user_id' => $user?->id ?? User::factory()->create(['branch_id' => $this->branch->id])->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $class->id,
            'section_id' => $class->is($this->class) ? $this->section->id : Section::factory()->create(['class_id' => $class->id])->id,
            'status' => $enrollmentStatus,
        ]);

        return $student;
    }

    private function feeStructure(string $monthlyFee = '1500.00', ?SchoolClass $class = null): FeeStructure
    {
        return FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => ($class ?? $this->class)->id,
            'monthly_fee' => $monthlyFee,
        ]);
    }

    public function test_generation_creates_invoices_and_is_idempotent(): void
    {
        $this->feeStructure();
        $a = $this->enrolledStudent();
        $this->enrolledStudent();

        $first = $this->withToken($this->adminToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 6, 'year' => 2026]);

        $first->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.skipped_existing', 0)
            ->assertJsonPath('data.missing_fee_structure', []);

        $this->assertDatabaseHas('invoices', [
            'student_id' => $a->id,
            'month' => 6,
            'year' => 2026,
            'amount' => '1500.00',
            'paid_amount' => '0.00',
            'status' => 'unpaid',
            'due_date' => '2026-06-10',
        ]);

        // invoice_no follows INV-{branchCode}-{yyyymm}-{seq}.
        $invoice = Invoice::where('student_id', $a->id)->first();
        $this->assertMatchesRegularExpression('/^INV-MP-202606-\d{4}$/', $invoice->invoice_no);

        // Re-running the same period creates nothing and skips the existing two.
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 6, 'year' => 2026])
            ->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.skipped_existing', 2);

        $this->assertSame(2, Invoice::where('month', 6)->where('year', 2026)->count());
    }

    public function test_tc_and_inactive_students_are_excluded(): void
    {
        $this->feeStructure();
        $active = $this->enrolledStudent();
        $tc = $this->enrolledStudent(StudentStatus::Tc);
        $inactive = $this->enrolledStudent(StudentStatus::Inactive);

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 6, 'year' => 2026])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('invoices', ['student_id' => $active->id]);
        $this->assertDatabaseMissing('invoices', ['student_id' => $tc->id]);
        $this->assertDatabaseMissing('invoices', ['student_id' => $inactive->id]);
    }

    public function test_missing_fee_structure_is_reported_and_skipped(): void
    {
        // A second class with no fee structure.
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        $this->feeStructure();
        $withFee = $this->enrolledStudent();
        $withoutFee = $this->enrolledStudent(class: $otherClass);

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 6, 'year' => 2026])
            ->assertOk()
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.missing_fee_structure', [['class_id' => $otherClass->id]]);

        $this->assertDatabaseHas('invoices', ['student_id' => $withFee->id]);
        $this->assertDatabaseMissing('invoices', ['student_id' => $withoutFee->id]);
    }

    public function test_amount_is_snapshot_and_unaffected_by_later_fee_edit(): void
    {
        $fee = $this->feeStructure('1500.00');
        $student = $this->enrolledStudent();

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 6, 'year' => 2026])
            ->assertOk();

        // Editing the fee structure must not touch the issued invoice.
        $fee->update(['monthly_fee' => '1600.00']);

        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'month' => 6,
            'amount' => '1500.00',
        ]);

        // A later month copies the new amount.
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 7, 'year' => 2026])
            ->assertOk();

        $this->assertDatabaseHas('invoices', [
            'student_id' => $student->id,
            'month' => 7,
            'amount' => '1600.00',
        ]);
    }

    public function test_invalid_month_is_422(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 13, 'year' => 2026])
            ->assertStatus(422)
            ->assertJsonValidationErrors('month');
    }

    public function test_generate_requires_fee_manage(): void
    {
        $viewer = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');

        $this->withToken($viewer->createToken('web')->plainTextToken)
            ->postJson('/api/v1/invoices/generate', ['month' => 6, 'year' => 2026])
            ->assertStatus(403);
    }
}
