<?php

namespace Database\Factories;

use App\Enums\TeacherAttendanceStatus;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAttendance>
 */
class TeacherAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = fake()->dateTimeBetween('-1 month', 'now');

        return [
            'teacher_id' => Teacher::factory(),
            'date' => $checkIn->format('Y-m-d'),
            'check_in_at' => $checkIn,
            'check_out_at' => null,
            'check_in_ip' => fake()->ipv4(),
            'status' => fake()->randomElement([TeacherAttendanceStatus::Present, TeacherAttendanceStatus::Late]),
            'corrected_by' => null,
        ];
    }
}
