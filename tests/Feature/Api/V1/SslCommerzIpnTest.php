<?php

namespace Tests\Feature\Api\V1;

use App\Contracts\PaymentGateway;
use App\Enums\EnrollmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Jobs\SendPaymentReceipt;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SslCommerzIpnTest extends TestCase
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

        $this->gateway = new FakeGateway;
        $this->app->instance(PaymentGateway::class, $this->gateway);
    }

    /**
     * Seed a student, an unpaid invoice, and a pending SSLCommerz payment
     * awaiting its IPN callback.
     *
     * @return array{invoice: Invoice, payment: Payment, tranId: string}
     */
    private function seedPendingPayment(string $amount = '1500.00'): array
    {
        $payer = User::factory()->create(['branch_id' => $this->branch->id]);

        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $payer->id,
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

        $tranId = 'TXN-'.fake()->uuid();

        $payment = Payment::factory()->create([
            'branch_id' => $this->branch->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'method' => PaymentMethod::Sslcommerz,
            'status' => PaymentStatus::Pending,
            'transaction_id' => $tranId,
            'collected_by' => $payer->id,
        ]);

        return ['invoice' => $invoice, 'payment' => $payment, 'tranId' => $tranId];
    }

    /**
     * The IPN form fields SSLCommerz posts.
     *
     * @return array<string, string>
     */
    private function ipnPayload(string $tranId, string $amount = '1500.00'): array
    {
        return [
            'tran_id' => $tranId,
            'val_id' => 'VAL-'.fake()->uuid(),
            'amount' => $amount,
            'status' => 'VALID',
        ];
    }

    public function test_valid_ipn_settles_payment_and_posts_income(): void
    {
        Queue::fake();

        $seed = $this->seedPendingPayment();

        $this->postJson('/api/v1/payments/sslcommerz/ipn', $this->ipnPayload($seed['tranId']))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.receipt_no', 'RCPT-MP-'.sprintf('%06d', $seed['payment']->id));

        $payment = $seed['payment']->fresh();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertSame('VALID', $payment->gateway_payload['status']);

        // Invoice settled and exactly one linked income row posted.
        $invoice = $seed['invoice']->fresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('1500.00', $invoice->paid_amount);
        $this->assertDatabaseCount('incomes', 1);
        $this->assertDatabaseHas('incomes', ['payment_id' => $payment->id, 'amount' => '1500.00']);

        // Payer notification queued (after commit).
        Queue::assertPushed(SendPaymentReceipt::class);
    }

    public function test_replayed_ipn_is_idempotent_and_side_effect_free(): void
    {
        $seed = $this->seedPendingPayment();

        // First call settles.
        $this->postJson('/api/v1/payments/sslcommerz/ipn', $this->ipnPayload($seed['tranId']))
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $paidAmountAfterFirst = $seed['invoice']->fresh()->paid_amount;
        $incomeCountAfterFirst = $seed['payment']->fresh()->income()->count();

        // Replay: 200 already_processed, no second income, invoice unchanged.
        Queue::fake();

        $this->postJson('/api/v1/payments/sslcommerz/ipn', $this->ipnPayload($seed['tranId']))
            ->assertOk()
            ->assertJsonPath('data.status', 'already_processed')
            ->assertJsonMissingPath('data.receipt_no');

        $this->assertDatabaseCount('incomes', 1);
        $this->assertSame(1, $incomeCountAfterFirst);
        $this->assertSame($paidAmountAfterFirst, $seed['invoice']->fresh()->paid_amount);
        Queue::assertNotPushed(SendPaymentReceipt::class);
    }

    public function test_invalid_validation_marks_payment_failed_422(): void
    {
        $this->gateway->validates(false);

        $seed = $this->seedPendingPayment();

        $this->postJson('/api/v1/payments/sslcommerz/ipn', $this->ipnPayload($seed['tranId']))
            ->assertStatus(422);

        $this->assertSame(PaymentStatus::Failed, $seed['payment']->fresh()->status);
        $this->assertDatabaseCount('incomes', 0);
        $this->assertSame(InvoiceStatus::Unpaid, $seed['invoice']->fresh()->status);
    }

    public function test_amount_mismatch_marks_payment_failed_422(): void
    {
        $seed = $this->seedPendingPayment('1500.00');

        // Gateway reports a different amount than the pending payment.
        $this->postJson('/api/v1/payments/sslcommerz/ipn', $this->ipnPayload($seed['tranId'], '999.00'))
            ->assertStatus(422);

        $this->assertSame(PaymentStatus::Failed, $seed['payment']->fresh()->status);
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_unknown_tran_id_returns_422(): void
    {
        $this->postJson('/api/v1/payments/sslcommerz/ipn', $this->ipnPayload('TXN-does-not-exist'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'Unknown transaction');

        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_landing_routes_report_status_without_writing(): void
    {
        $seed = $this->seedPendingPayment();

        $snapshot = [
            'payment' => $seed['payment']->fresh()->toArray(),
            'invoice' => $seed['invoice']->fresh()->toArray(),
        ];

        foreach (['success', 'fail', 'cancel'] as $result) {
            $this->getJson("/api/v1/payments/sslcommerz/{$result}?tran_id={$seed['tranId']}")
                ->assertOk()
                ->assertJsonPath('data.payment_id', $seed['payment']->id)
                ->assertJsonPath('data.status', 'pending');
        }

        // No DB writes from any landing route.
        $this->assertSame($snapshot['payment'], $seed['payment']->fresh()->toArray());
        $this->assertSame($snapshot['invoice'], $seed['invoice']->fresh()->toArray());
        $this->assertDatabaseCount('incomes', 0);
    }

    public function test_landing_unknown_tran_id_is_404(): void
    {
        $this->getJson('/api/v1/payments/sslcommerz/success?tran_id=TXN-nope')
            ->assertStatus(404);
    }

    public function test_two_sequential_ipns_settle_exactly_once(): void
    {
        $seed = $this->seedPendingPayment();

        $payload = $this->ipnPayload($seed['tranId']);

        $first = $this->postJson('/api/v1/payments/sslcommerz/ipn', $payload);
        $second = $this->postJson('/api/v1/payments/sslcommerz/ipn', $payload);

        $first->assertOk()->assertJsonPath('data.status', 'paid');
        $second->assertOk()->assertJsonPath('data.status', 'already_processed');

        // Exactly one settlement: one income row, invoice paid once.
        $this->assertDatabaseCount('incomes', 1);
        $this->assertSame('1500.00', $seed['invoice']->fresh()->paid_amount);
        $this->assertSame(PaymentStatus::Paid, $seed['payment']->fresh()->status);
    }
}
