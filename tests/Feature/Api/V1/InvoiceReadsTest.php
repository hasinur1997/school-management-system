<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReadsTest extends TestCase
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

    private function staffToken(): string
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * Create a student (optionally with a given login) plus an invoice for the
     * given month.
     *
     * @return array{student: Student, invoice: Invoice}
     */
    private function seedInvoice(int $month = 6, int $year = 2026, ?User $user = null): array
    {
        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'name_en' => 'Rahima Khatun',
            'user_id' => $user?->id ?? User::factory()->create(['branch_id' => $this->branch->id])->id,
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'enrollment_id' => $enrollment->id,
            'month' => $month,
            'year' => $year,
            'amount' => '1500.00',
            'due_date' => sprintf('%04d-%02d-10', $year, $month),
        ]);

        return ['student' => $student, 'invoice' => $invoice];
    }

    public function test_staff_shows_invoice_with_payments(): void
    {
        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/invoices/{$seed['invoice']->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $seed['invoice']->id)
            ->assertJsonPath('data.student.id', $seed['student']->id)
            ->assertJsonPath('data.student.name_en', 'Rahima Khatun')
            ->assertJsonPath('data.month', 6)
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.paid_amount', '0.00')
            ->assertJsonPath('data.status', 'unpaid')
            ->assertJsonPath('data.due_date', '2026-06-10')
            ->assertJsonPath('data.payments', []);
    }

    public function test_list_filters_by_student(): void
    {
        $a = $this->seedInvoice();
        $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/invoices?student_id={$a['student']->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $a['student']->id);
    }

    public function test_student_sees_own_invoice(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $seed = $this->seedInvoice(user: $user);

        $this->withToken($user->createToken('web')->plainTextToken)
            ->getJson("/api/v1/invoices/{$seed['invoice']->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $seed['invoice']->id);
    }

    public function test_unrelated_student_gets_404(): void
    {
        $seed = $this->seedInvoice();

        $intruder = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        Student::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $intruder->id]);

        $this->withToken($intruder->createToken('web')->plainTextToken)
            ->getJson("/api/v1/invoices/{$seed['invoice']->id}")
            ->assertStatus(404);
    }

    public function test_me_invoices_returns_own_for_student(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $seed = $this->seedInvoice(month: 6, user: $user);

        // A second invoice for the same student in a later month.
        Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'student_id' => $seed['student']->id,
            'enrollment_id' => $seed['invoice']->enrollment_id,
            'month' => 7,
            'year' => 2026,
        ]);

        // Another student's invoice must not leak.
        $this->seedInvoice(month: 6);

        $this->withToken($user->createToken('web')->plainTextToken)
            ->getJson('/api/v1/me/invoices?year=2026')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_me_invoices_parent_linked_child(): void
    {
        $seed = $this->seedInvoice();

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        $parent = ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);
        $parent->students()->attach($seed['student']->id);

        $this->withToken($parentUser->createToken('web')->plainTextToken)
            ->getJson("/api/v1/me/invoices?student_id={$seed['student']->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.student.id', $seed['student']->id);
    }

    public function test_me_invoices_parent_unlinked_child_is_404(): void
    {
        $seed = $this->seedInvoice();

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);

        $this->withToken($parentUser->createToken('web')->plainTextToken)
            ->getJson("/api/v1/me/invoices?student_id={$seed['student']->id}")
            ->assertStatus(404);
    }

    public function test_out_of_branch_invoice_is_404(): void
    {
        $seed = $this->seedInvoice();

        $otherBranch = Branch::factory()->create();
        $other = User::factory()->create(['branch_id' => $otherBranch->id])->assignRole('admin');

        $this->withToken($other->createToken('web')->plainTextToken)
            ->getJson("/api/v1/invoices/{$seed['invoice']->id}")
            ->assertStatus(404);
    }
}
