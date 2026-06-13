<?php

namespace Database\Factories;

use App\Enums\StudentStatus;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
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
            'application_id' => null,
            'admission_no' => sprintf('STU-FAC-%04d-%05d', fake()->numberBetween(2000, 2099), fake()->unique()->numberBetween(1, 99999)),

            'name_bn' => 'রহিম উদ্দিন',
            'name_en' => fake()->name(),

            'father_name_bn' => 'করিম উদ্দিন',
            'father_name_en' => fake()->name('male'),
            'father_nid' => fake()->optional()->numerify('##########'),

            'mother_name_bn' => 'আমেনা বেগম',
            'mother_name_en' => fake()->name('female'),
            'mother_nid' => fake()->optional()->numerify('##########'),

            'present_village' => fake()->streetName(),
            'present_post_office' => fake()->city(),
            'present_upazila' => fake()->city(),
            'present_district' => fake()->city(),

            'permanent_village_bn' => 'গ্রাম',
            'permanent_post_office_bn' => 'ডাকঘর',
            'permanent_upazila_bn' => 'উপজেলা',
            'permanent_district_bn' => 'জেলা',

            'permanent_village_en' => fake()->streetName(),
            'permanent_post_office_en' => fake()->city(),
            'permanent_upazila_en' => fake()->city(),
            'permanent_district_en' => fake()->city(),

            'father_mobile' => fake()->numerify('017########'),
            'mother_mobile' => fake()->optional()->numerify('018########'),

            'birth_reg_no' => fake()->unique()->numerify('#################'),
            'date_of_birth' => fake()->dateTimeBetween('-15 years', '-5 years')->format('Y-m-d'),
            'religion' => fake()->randomElement(['Islam', 'Hinduism', 'Christianity', 'Buddhism']),
            'nationality' => 'Bangladeshi',
            'caste' => null,

            'status' => StudentStatus::Active,
            'admitted_at' => fake()->dateTimeBetween('-3 years', 'now')->format('Y-m-d'),
        ];
    }

    /**
     * Indicate that the student has been issued a transfer certificate.
     */
    public function tc(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StudentStatus::Tc,
        ]);
    }
}
