<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Enrollment;
use App\Models\StudentAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentAttendance>
 */
class StudentAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'date' => fake()->date(),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
            'recorded_by' => User::factory(),
        ];
    }
}
