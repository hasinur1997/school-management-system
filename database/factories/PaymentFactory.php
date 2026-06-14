<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'invoice_id' => Invoice::factory(),
            'receipt_no' => null,
            'amount' => '1500.00',
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Pending,
            'transaction_id' => null,
            'gateway_payload' => null,
            'paid_at' => null,
            'collected_by' => null,
        ];
    }

    /**
     * A settled (paid) payment with a receipt number and paid_at stamp.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Paid,
            'receipt_no' => sprintf('RCPT-MP-%06d', fake()->unique()->numberBetween(1, 999999)),
            'paid_at' => now(),
        ]);
    }
}
