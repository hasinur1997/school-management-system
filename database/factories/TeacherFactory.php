<?php

namespace Database\Factories;

use App\Enums\TeacherStatus;
use App\Models\Branch;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Teacher>
 */
class TeacherFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('017########'),
            'designation' => fake()->randomElement(['Assistant Teacher', 'Senior Teacher', 'Head Teacher']),
            'joining_date' => fake()->date(),
            'status' => TeacherStatus::Active,
        ];
    }

    /**
     * Indicate that the teacher is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TeacherStatus::Inactive,
        ]);
    }
}
