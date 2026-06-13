<?php

namespace Database\Factories;

use App\Models\AdmissionApplication;
use App\Models\AdmissionPreviousEducation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionPreviousEducation>
 */
class AdmissionPreviousEducationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => AdmissionApplication::factory(),
            'exam_name' => fake()->randomElement(['PSC', 'JSC', 'SSC']),
            'institution_name' => fake()->company().' School',
            'gpa' => fake()->optional()->randomFloat(2, 1, 5),
            'passing_year' => fake()->optional()->numberBetween(2010, 2025),
            'board_roll' => fake()->optional()->numerify('######'),
            'board_reg_no' => fake()->optional()->numerify('##########'),
        ];
    }
}
