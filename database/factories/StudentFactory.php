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
     * Realistic Bangla given names by gender plus shared surname parts, so a
     * seeded cohort reads as a believable roster rather than rows of the same
     * placeholder name.
     *
     * @var array{male: list<string>, female: list<string>, surname: list<string>}
     */
    public const BANGLA_NAMES = [
        'male' => ['রহিম', 'করিম', 'আব্দুল্লাহ', 'সাকিব', 'তানভীর', 'নাইম', 'রাকিব', 'ইমরান', 'ফাহিম', 'সাব্বির', 'আরিফ', 'মাহমুদ'],
        'female' => ['আমেনা', 'ফাতেমা', 'রাবেয়া', 'সুমাইয়া', 'আয়েশা', 'নুসরাত', 'তাসনিম', 'জান্নাত', 'মারিয়া', 'রিয়া', 'সাদিয়া', 'লামিয়া'],
        'surname' => ['উদ্দিন', 'হোসেন', 'ইসলাম', 'আহমেদ', 'রহমান', 'মিয়া', 'সরকার', 'খান'],
    ];

    /**
     * A realistic Bangla full name for the given gender (`male`|`female`).
     */
    public static function banglaName(string $gender): string
    {
        return fake()->randomElement(self::BANGLA_NAMES[$gender])
            .' '.fake()->randomElement(self::BANGLA_NAMES['surname']);
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'user_id' => User::factory(),
            'branch_id' => Branch::factory(),
            'application_id' => null,
            'admission_no' => sprintf('STU-FAC-%04d-%05d', fake()->numberBetween(2000, 2099), fake()->unique()->numberBetween(1, 99999)),

            'name_bn' => self::banglaName($gender),
            'name_en' => fake()->name($gender),

            'father_name_bn' => self::banglaName('male'),
            'father_name_en' => fake()->name('male'),
            'father_nid' => fake()->optional()->numerify('##########'),

            'mother_name_bn' => self::banglaName('female'),
            'mother_name_en' => fake()->name('female'),
            'mother_nid' => fake()->optional()->numerify('##########'),

            'present_village' => fake()->streetName(),
            'present_post_office' => fake()->city(),
            'present_upazila' => fake()->city(),
            'present_district' => fake()->city(),

            'present_division' => fake()->city(),

            'permanent_village' => 'গ্রাম',
            'permanent_post_office' => 'ডাকঘর',
            'permanent_upazila' => 'উপজেলা',
            'permanent_district' => 'জেলা',
            'permanent_division' => 'বিভাগ',

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
