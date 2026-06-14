<?php

namespace Database\Factories;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'branch_id' => $branch,
            'session_id' => AcademicSession::factory(),
            'class_id' => SchoolClass::factory()->state(['branch_id' => $branch]),
            'monthly_fee' => fake()->randomElement(['1000.00', '1200.00', '1500.00', '2000.00']),
        ];
    }
}
