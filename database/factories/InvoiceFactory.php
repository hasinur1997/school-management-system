<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();
        $month = fake()->numberBetween(1, 12);
        $year = 2026;

        return [
            'branch_id' => $branch,
            'student_id' => Student::factory()->state(['branch_id' => $branch]),
            'enrollment_id' => Enrollment::factory(),
            'invoice_no' => sprintf('INV-MP-%04d%02d-%04d', $year, $month, fake()->unique()->numberBetween(1, 9999)),
            'month' => $month,
            'year' => $year,
            'amount' => '1500.00',
            'paid_amount' => '0.00',
            'status' => InvoiceStatus::Unpaid,
            'due_date' => sprintf('%04d-%02d-10', $year, $month),
        ];
    }
}
