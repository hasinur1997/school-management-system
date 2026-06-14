<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\PaymentGateway;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlinePaymentInitTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private FakeGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['code' => 'MP']);
        $this->session = AcademicSession::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $this->section = Section::factory()->create(['class_id' => $this->class->id]);

        // Bind the fake gateway for the whole test — no network.
        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    private function staffToken(): string
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('accountant');

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

    public function test_staff_inits_checkout_and_persists_pending_payment(): void
    {
        $this->gateway->returning('https://sandbox.sslcommerz.com/checkout/abc123');

        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online")
            ->assertCreated()
            ->assertJsonPath('data.payment_id', 1)
            ->assertJsonPath('data.gateway_url', 'https://sandbox.sslcommerz.com/checkout/abc123')
            ->assertJsonPath('data.transaction_id', fn (string $id): bool => str_starts_with($id, 'TXN-'));

        // Pending payment + transaction id persisted before redirect.
        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(PaymentMethod::Sslcommerz, $payment->method);
        $this->assertSame('1500.00', $payment->amount);
        $this->assertStringStartsWith('TXN-', $payment->transaction_id);
        $this->assertNull($payment->receipt_no);
        $this->assertNull($payment->paid_at);

        // Nothing settled — no income posted, invoice untouched.
        $this->assertDatabaseCount('incomes', 0);
        $seed['invoice']->refresh();
        $this->assertSame(InvoiceStatus::Unpaid, $seed['invoice']->status);
    }

    public function test_amount_defaults_to_outstanding(): void
    {
        $seed = $this->seedInvoice();
        $seed['invoice']->update(['paid_amount' => '500.00', 'status' => InvoiceStatus::Partial]);

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online")
            ->assertCreated();

        $this->assertSame('1000.00', Payment::firstOrFail()->amount);
    }

    public function test_paid_invoice_returns_409(): void
    {
        $seed = $this->seedInvoice();
        $seed['invoice']->update(['paid_amount' => '1500.00', 'status' => InvoiceStatus::Paid]);

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online", ['amount' => '1500.00'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Invoice is already paid');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_gateway_failure_returns_502_and_marks_payment_failed(): void
    {
        $this->gateway->failNext();

        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online", ['amount' => '1500.00'])
            ->assertStatus(502)
            ->assertJsonPath('message', 'Payment gateway unavailable. Try again.');

        // The pending payment was persisted, then marked failed.
        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertSame('1500.00', $payment->amount);
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_over_amount_rejected_422(): void
    {
        $seed = $this->seedInvoice();

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online", ['amount' => '2000.00'])
            ->assertStatus(422)
            ->assertJsonPath('errors.amount.0', 'Full payment of 1500.00 required');

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_student_self_can_init(): void
    {
        $studentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $seed = $this->seedInvoice(user: $studentUser);

        $this->withToken($studentUser->createToken('web')->plainTextToken)
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online")
            ->assertCreated();
    }

    public function test_linked_parent_inits_201_unrelated_student_404(): void
    {
        $studentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $seed = $this->seedInvoice(user: $studentUser);

        // Build both actors + tokens up front; a branch-scoped model created
        // between requests would re-cache the sanctum guard (5.3 gotcha).
        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        $parent = ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);
        $parent->students()->attach($seed['student']->id);
        $parentToken = $parentUser->createToken('web')->plainTextToken;

        $intruder = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        Student::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $intruder->id]);
        $intruderToken = $intruder->createToken('web')->plainTextToken;

        // Linked parent → 201.
        $this->withToken($parentToken)
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online")
            ->assertCreated();

        $this->app['auth']->forgetGuards();

        // Unrelated student → 404 (existence hidden).
        $this->withToken($intruderToken)
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online")
            ->assertStatus(404);
    }

    public function test_out_of_branch_invoice_is_404(): void
    {
        $seed = $this->seedInvoice();

        $otherBranch = Branch::factory()->create();
        $other = User::factory()->create(['branch_id' => $otherBranch->id])->assignRole('accountant');

        $this->withToken($other->createToken('web')->plainTextToken)
            ->postJson("/api/v1/invoices/{$seed['invoice']->id}/payments/online", ['amount' => '1500.00'])
            ->assertStatus(404);
    }
}
