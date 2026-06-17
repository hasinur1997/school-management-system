<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Student;
use App\Models\TransferCertificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferCertificate>
 */
class TransferCertificateFactory extends Factory
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
            'student_id' => Student::factory(),
            'tc_no' => 'TC-MP-'.fake()->unique()->numerify('####'),
            'reason' => fake()->sentence(),
            'issue_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'issued_by' => User::factory(),
        ];
    }
}
