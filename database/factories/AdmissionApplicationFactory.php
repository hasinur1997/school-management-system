<?php

namespace Database\Factories;

use App\Enums\AdmissionStatus;
use App\Models\AdmissionApplication;
use App\Models\Branch;
use App\Models\SchoolClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdmissionApplication>
 */
class AdmissionApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'branch_id' => Branch::factory(),
            'application_no' => sprintf('APP-FAC-%05d', fake()->unique()->numberBetween(1, 99999)),
            'desired_class_id' => SchoolClass::factory(),

            'name_bn' => StudentFactory::banglaName($gender),
            'name_en' => fake()->name($gender),

            'father_name_bn' => StudentFactory::banglaName('male'),
            'father_name_en' => fake()->name('male'),
            'father_nid' => fake()->optional()->numerify('##########'),

            'mother_name_bn' => StudentFactory::banglaName('female'),
            'mother_name_en' => fake()->name('female'),
            'mother_nid' => fake()->optional()->numerify('##########'),

            'present_village' => fake()->streetName(),
            'present_post_office' => fake()->city(),
            'present_upazila' => fake()->city(),
            'present_district' => fake()->city(),
            'present_division' => fake()->city(),
            'father_mobile' => fake()->numerify('017########'),

            'permanent_village' => 'গ্রাম',
            'permanent_post_office' => 'ডাকঘর',
            'permanent_upazila' => 'উপজেলা',
            'permanent_district' => 'জেলা',
            'permanent_division' => 'বিভাগ',
            'mother_mobile' => fake()->numerify('018########'),


            'birth_reg_no' => fake()->unique()->numerify('#################'),
            'date_of_birth' => fake()->dateTimeBetween('-15 years', '-5 years')->format('Y-m-d'),
            'religion' => fake()->randomElement(['Islam', 'Hinduism', 'Christianity', 'Buddhism']),
            'nationality' => 'Bangladeshi',
            'caste' => null,

            'status' => AdmissionStatus::Pending,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    /**
     * Indicate that the application has been approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdmissionStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate that the application has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AdmissionStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
            'reviewed_at' => now(),
        ]);
    }
}
