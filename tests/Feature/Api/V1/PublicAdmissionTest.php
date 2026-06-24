<?php

namespace Tests\Feature\Api\V1;

use App\Models\AcademicSession;
use App\Models\AdmissionApplication;
use App\Models\AdmissionPreviousEducation;
use App\Models\Branch;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicAdmissionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->branch = Branch::factory()->create(['code' => 'JA']);
        $this->class = SchoolClass::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    /**
     * A full, valid multipart payload. Overrides are merged shallowly.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name_bn' => 'রহিম উদ্দিন',
            'name_en' => 'Rahim Uddin',
            'father_name_bn' => 'করিম উদ্দিন',
            'father_name_en' => 'Karim Uddin',
            'mother_name_bn' => 'আমেনা বেগম',
            'mother_name_en' => 'Amena Begum',
            'present_village' => 'Boalia',
            'present_post_office' => 'Rajshahi',
            'present_upazila' => 'Boalia',
            'present_district' => 'Rajshahi',
            'present_division' => 'Rajshahi',
            'father_mobile' => '01711111111',
            'permanent_village' => 'গ্রাম',
            'permanent_post_office' => 'ডাকঘর',
            'permanent_upazila' => 'উপজেলা',
            'permanent_district' => 'জেলা',
            'permanent_division' => 'বিভাগ',
            'mother_mobile' => '01822222222',
            'birth_reg_no' => '1234567890123456',
            'date_of_birth' => '2014-03-09',
            'religion' => 'Islam',
            'nationality' => 'Bangladeshi',
            'branch_id' => $this->branch->id,
            'desired_class_id' => $this->class->id,
            'photo' => UploadedFile::fake()->image('photo.jpg'),
        ], $overrides);
    }

    public function test_anonymous_submission_persists_application_media_and_previous_educations(): void
    {
        $payload = $this->payload([
            'documents' => [
                UploadedFile::fake()->createWithContent('birth.pdf', '%PDF-1.4 fake birth certificate'),
                UploadedFile::fake()->image('marksheet.png'),
            ],
            'previous_educations' => [
                ['exam_name' => 'PSC', 'institution_name' => 'City School', 'gpa' => '5.00', 'passing_year' => '2022'],
                ['exam_name' => 'JSC', 'institution_name' => 'City School', 'gpa' => '4.50', 'passing_year' => '2024'],
            ],
        ]);

        $response = $this->postJson('/api/v1/public/admissions', $payload);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Application submitted successfully.',
                'data' => [
                    'application_no' => 'APP-JA-00001',
                    'status' => 'pending',
                ],
            ]);

        $application = AdmissionApplication::firstOrFail();
        $this->assertSame($this->branch->id, $application->branch_id);
        $this->assertSame('pending', $application->status->value);
        $this->assertCount(2, $application->previousEducations);
        $this->assertSame(2, AdmissionPreviousEducation::count());
        $this->assertNotNull($application->getFirstMedia('photo'));
        $this->assertCount(2, $application->getMedia('documents'));
    }

    public function test_mother_mobile_is_optional_and_persists_empty_when_absent(): void
    {
        $payload = $this->payload();
        unset($payload['mother_mobile']);

        $this->postJson('/api/v1/public/admissions', $payload)->assertCreated();

        $this->assertSame('', AdmissionApplication::firstOrFail()->mother_mobile);
    }

    public function test_validation_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/v1/public/admissions', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name_bn', 'name_en', 'father_name_bn', 'birth_reg_no', 'photo', 'branch_id', 'desired_class_id']);
    }

    public function test_previous_education_passing_year_must_be_realistic(): void
    {
        $this->postJson('/api/v1/public/admissions', $this->payload([
            'previous_educations' => [
                [
                    'exam_name' => 'PSC',
                    'institution_name' => 'Jogonnathpur Govt. Pria',
                    'gpa' => '0',
                    'passing_year' => '2409',
                    'board_roll' => '343434343434343434343434343434',
                    'board_reg_no' => '2342342342342342343423432',
                ],
            ],
        ]))->assertStatus(422)->assertJsonValidationErrors(['previous_educations.0.passing_year']);

        $this->assertSame(0, AdmissionPreviousEducation::count());
    }

    public function test_photo_must_be_image_within_size_limit(): void
    {
        $this->postJson('/api/v1/public/admissions', $this->payload([
            'photo' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['photo']);

        $this->postJson('/api/v1/public/admissions', $this->payload([
            'birth_reg_no' => '9999999999999999',
            'photo' => UploadedFile::fake()->image('big.jpg')->size(2049),
        ]))->assertStatus(422)->assertJsonValidationErrors(['photo']);
    }

    public function test_documents_are_capped_at_five_and_type_checked(): void
    {
        $this->postJson('/api/v1/public/admissions', $this->payload([
            'documents' => array_fill(0, 6, UploadedFile::fake()->image('doc.png')),
        ]))->assertStatus(422)->assertJsonValidationErrors(['documents']);

        $this->postJson('/api/v1/public/admissions', $this->payload([
            'birth_reg_no' => '9999999999999999',
            'documents' => [UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')],
        ]))->assertStatus(422)->assertJsonValidationErrors(['documents.0']);
    }

    public function test_duplicate_birth_reg_no_is_rejected_with_message(): void
    {
        AdmissionApplication::factory()->create([
            'branch_id' => $this->branch->id,
            'desired_class_id' => $this->class->id,
            'birth_reg_no' => '1234567890123456',
        ]);

        $this->postJson('/api/v1/public/admissions', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'birth_reg_no' => 'An application with this birth registration number already exists.',
            ]);
    }

    public function test_inactive_branch_is_rejected(): void
    {
        $inactive = Branch::factory()->inactive()->create();

        $this->postJson('/api/v1/public/admissions', $this->payload([
            'branch_id' => $inactive->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['branch_id']);
    }

    public function test_inactive_class_is_rejected(): void
    {
        $inactive = SchoolClass::factory()->inactive()->create(['branch_id' => $this->branch->id]);

        $this->postJson('/api/v1/public/admissions', $this->payload([
            'desired_class_id' => $inactive->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['desired_class_id']);
    }

    public function test_class_from_another_branch_is_rejected(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);

        $this->postJson('/api/v1/public/admissions', $this->payload([
            'desired_class_id' => $otherClass->id,
        ]))->assertStatus(422)->assertJsonValidationErrors(['desired_class_id']);
    }

    public function test_eleventh_request_in_an_hour_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/public/admissions', [])->assertStatus(422);
        }

        $this->postJson('/api/v1/public/admissions', [])->assertStatus(429);
    }

    public function test_media_failure_rolls_back_the_whole_submission(): void
    {
        // Point media at an undefined disk so the photo write throws inside
        // the transaction, after the application + children are inserted.
        config(['media-library.disk_name' => 'nonexistent']);

        $payload = $this->payload([
            'previous_educations' => [
                ['exam_name' => 'PSC', 'institution_name' => 'City School'],
            ],
        ]);

        try {
            $this->postJson('/api/v1/public/admissions', $payload);
        } catch (\Throwable) {
            // The thrown disk error is expected; we assert on persistence.
        }

        $this->assertSame(0, AdmissionApplication::count());
        $this->assertSame(0, AdmissionPreviousEducation::count());
    }

    public function test_status_check_returns_status_when_dob_matches(): void
    {
        $session = AcademicSession::factory()->current()->create(['name' => '2026']);
        $application = AdmissionApplication::factory()->rejected()->create([
            'branch_id' => $this->branch->id,
            'desired_class_id' => $this->class->id,
            'application_no' => 'APP-JA-00042',
            'date_of_birth' => '2014-03-09',
            'birth_reg_no' => '20140309123456789',
            'name_bn' => 'রহিম উদ্দিন',
            'name_en' => 'Rahim Uddin',
            'religion' => 'Islam',
            'nationality' => 'Bangladeshi',
            'rejection_reason' => 'Incomplete documents.',
        ]);
        $application->addMedia(UploadedFile::fake()->image('photo.jpg'))->toMediaCollection('photo');

        $this->getJson('/api/v1/public/admissions/APP-JA-00042/status?date_of_birth=2014-03-09')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'application_no' => 'APP-JA-00042',
                    'branch' => [
                        'id' => $this->branch->id,
                        'name' => $this->branch->name,
                        'code' => $this->branch->code,
                    ],
                    'class' => [
                        'id' => $this->class->id,
                        'name' => $this->class->name,
                    ],
                    'session' => [
                        'id' => $session->id,
                        'name' => '2026',
                        'is_current' => true,
                    ],
                    'status' => 'rejected',
                    'name_bn' => 'রহিম উদ্দিন',
                    'name_en' => 'Rahim Uddin',
                    'date_of_birth' => '2014-03-09',
                    'birth_reg_no' => '20140309123456789',
                    'religion' => 'Islam',
                    'nationality' => 'Bangladeshi',
                    'rejection_reason' => 'Incomplete documents.',
                ],
            ])
            ->assertJsonPath('data.photo', fn (?string $url): bool => $url !== null && str_contains($url, 'photo.jpg'));
    }

    public function test_status_check_with_mismatched_dob_is_404(): void
    {
        AdmissionApplication::factory()->create([
            'branch_id' => $this->branch->id,
            'desired_class_id' => $this->class->id,
            'application_no' => 'APP-JA-00042',
            'date_of_birth' => '2014-03-09',
        ]);

        $this->getJson('/api/v1/public/admissions/APP-JA-00042/status?date_of_birth=2010-01-01')
            // Mismatched dob must not reveal that the application exists.
            ->assertStatus(404);
    }

    public function test_status_check_requires_date_of_birth(): void
    {
        $this->getJson('/api/v1/public/admissions/APP-JA-00042/status')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date_of_birth']);
    }
}
