<?php

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Enrollment;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $from = Enrollment::factory()->create();

        return [
            'student_id' => $from->student_id,
            'from_enrollment_id' => $from->id,
            'to_enrollment_id' => Enrollment::factory(),
            'type' => PromotionType::Bulk,
            'promoted_by' => User::factory(),
            'promoted_at' => now(),
        ];
    }

    /**
     * A held-back record: the student stayed (no new enrollment).
     */
    public function heldBack(): static
    {
        return $this->state(fn (array $attributes) => [
            'to_enrollment_id' => null,
        ]);
    }
}
