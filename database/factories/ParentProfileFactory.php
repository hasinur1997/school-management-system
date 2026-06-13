<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\ParentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ParentProfile>
 */
class ParentProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ParentProfile>
     */
    protected $model = ParentProfile::class;

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
            'phone' => fake()->unique()->numerify('017########'),
            'relation' => fake()->randomElement(['father', 'mother', 'guardian']),
        ];
    }
}
