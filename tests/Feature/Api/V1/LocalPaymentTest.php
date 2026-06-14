<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalPaymentTest extends TestCase
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
     * Seed a student (with login) and an unpaid invoice.
     *
     * @return array{student: Student, invoice: Invoice}
     */
    private function seedInvoice(string $amount = '1500.00', ?User $user = null): array
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
            'month' => 6,
            'year' => 2026,
            'amount' => $amount,
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::Unpaid,
        ]);

        return ['student' => $student, 'invoice' => $invoice];
    }

    public function test_full_payment_settles_invoice_and_posts_income(): void
    {
        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '1500.00'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '1500.00')
            ->assertJsonPath('data.method', 'cash')
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.receipt_no', 'RCPT-MP-000001')
            ->assertJsonPath('data.invoice.id', $seed['invoice']->id)
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.invoice.paid_amount', '1500.00')
            ->assertJsonPath('data.receipt_url', '/api/v1/payments/1/receipt');

        $seed['invoice']->refresh();
        $this->assertSame(InvoiceStatus::Paid, $seed['invoice']->status);
        $this->assertSame('1500.00', $seed['invoice']->paid_amount);

        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);

        // Exactly one income row, linked to the payment.
        $this->assertDatabaseCount('incomes', 1);
        $income = Income::firstOrFail();
        $this->assertSame($payment->id, $income->payment_id);
        $this->assertSame('1500.00', $income->amount);
        $this->assertSame('Monthly fee 6/2026 — '.$seed['invoice']->invoice_no, $income->title);
        $this->assertSame($payment->paid_at->toDateString(), $income->date->toDateString());
    }

    public function test_partial_disabled_rejects_under_and_over_amount(): void
    {
        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '500.00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Full payment of 1500.00 required');

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '2000.00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Full payment of 1500.00 required');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_zero_or_negative_amount_rejected(): void
    {
        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '0'])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Amount must be greater than zero');

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '-5'])
            ->assertStatus(422);
    }

    public function test_partial_enabled_allows_partial_then_completes(): void
    {
        config(['fees.partial_payment_enabled' => true]);

        $seed = $this->seedInvoice();

        // Over outstanding is still rejected.
        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '2000.00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Amount may not exceed the outstanding 1500.00');

        // First partial payment leaves the invoice partial.
        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '600.00'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.invoice.status', 'partial')
            ->assertJsonPath('data.invoice.paid_amount', '600.00');

        // Second payment for the remaining balance completes it.
        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '900.00'])
            ->assertCreated()
            ->assertJsonPath('data.invoice.status', 'paid')
            ->assertJsonPath('data.invoice.paid_amount', '1500.00');

        $this->assertDatabaseCount('payments', 2);
        $this->assertDatabaseCount('incomes', 2);
    }

    public function test_paid_invoice_returns_409(): void
    {
        $seed = $this->seedInvoice();
        $seed['invoice']->update(['paid_amount' => '1500.00', 'status' => InvoiceStatus::Paid]);

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '1500.00'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Invoice is already paid');
    }

    public function test_pipeline_rolls_back_on_induced_failure(): void
    {
        $seed = $this->seedInvoice();

        // Induce a failure at the last step of the pipeline (income posting).
        Income::creating(function (): void {
            throw new \RuntimeException('induced failure');
        });

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '1500.00'])
            ->assertStatus(500);

        // Nothing persisted — payment, income, and invoice are untouched.
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('incomes', 0);
        $seed['invoice']->refresh();
        $this->assertSame(InvoiceStatus::Unpaid, $seed['invoice']->status);
        $this->assertSame('0.00', $seed['invoice']->paid_amount);
    }

    public function test_out_of_branch_invoice_is_404(): void
    {
        $seed = $this->seedInvoice();

        $otherBranch = Branch::factory()->create();
        $other = User::factory()->create(['branch_id' => $otherBranch->id])->assignRole('admin');

        $this->withToken($other->createToken('web')->plainTextToken)
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '1500.00'])
            ->assertStatus(404);
    }

    public function test_invoice_show_includes_payment(): void
    {
        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/local", ['amount' => '1500.00'])
            ->assertCreated();

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/invoices/{$seed['invoice']->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonCount(1, 'data.payments')
            ->assertJsonPath('data.payments.0.method', 'cash')
            ->assertJsonPath('data.payments.0.status', 'paid');
    }

    // --- Receipt PDF ---------------------------------------------------------

    /**
     * Settle an invoice via the endpoint and return the resulting payment.
     */
    private function paidPayment(User $collector, Invoice $invoice): Payment
    {
        $this->withToken($collector->createToken('web')->plainTextToken)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments/local", ['amount' => $invoice->amount])
            ->assertCreated();

        // Query before resetting the guard: a branch-scoped query re-resolves
        // (and re-caches) the sanctum guard, so the forget must come last.
        $payment = Payment::firstOrFail();

        $this->app['auth']->forgetGuards();

        return $payment;
    }

    public function test_receipt_streams_pdf_for_staff(): void
    {
        $collector = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $seed = $this->seedInvoice();
        $payment = $this->paidPayment($collector, $seed['invoice']);

        $this->withToken($collector->createToken('web')->plainTextToken)
            ->get("/api/v1/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_receipt_accessible_to_self_and_linked_parent(): void
    {
        $studentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $seed = $this->seedInvoice(user: $studentUser);

        $collector = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');

        // Build all actors + tokens up front; the payment is settled last so no
        // branch-scoped query re-caches the guard between requests (5.3 gotcha).
        $studentToken = $studentUser->createToken('web')->plainTextToken;
        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        $parent = ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);
        $parent->students()->attach($seed['student']->id);
        $parentToken = $parentUser->createToken('web')->plainTextToken;

        $payment = $this->paidPayment($collector, $seed['invoice']);

        // Self.
        $this->withToken($studentToken)
            ->get("/api/v1/payments/{$payment->id}/receipt")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->app['auth']->forgetGuards();

        // Linked parent.
        $this->withToken($parentToken)
            ->get("/api/v1/payments/{$payment->id}/receipt")
            ->assertOk();
    }

    public function test_receipt_hidden_from_unrelated_student(): void
    {
        $collector = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $seed = $this->seedInvoice();

        // Create the intruder up front; the payment is settled last so no
        // branch-scoped model is created after the guard reset (which would
        // otherwise re-cache the collector — see the 5.3 sanctum gotcha).
        $intruder = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        Student::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $intruder->id]);
        $intruderToken = $intruder->createToken('web')->plainTextToken;

        $payment = $this->paidPayment($collector, $seed['invoice']);

        $this->withToken($intruderToken)
            ->get("/api/v1/payments/{$payment->id}/receipt")
            ->assertStatus(404);
    }

    public function test_receipt_404_for_unpaid_payment(): void
    {
        $seed = $this->seedInvoice();
        $payment = Payment::factory()->create([
            'branch_id' => $this->branch->id,
            'invoice_id' => $seed['invoice']->id,
            'status' => PaymentStatus::Pending,
        ]);

        $this->withToken($this->staffToken())
            ->get("/api/v1/payments/{$payment->id}/receipt")
            ->assertStatus(404);
    }
}
