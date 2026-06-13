<?php

namespace Tests\Feature;

use App\Models\AdmissionApplication;
use App\Models\AdmissionPreviousEducation;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Services\ApplicationNoGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_rows_with_previous_education(): void
    {
        $application = AdmissionApplication::factory()
            ->has(AdmissionPreviousEducation::factory()->count(2), 'previousEducations')
            ->create();

        $this->assertDatabaseHas('admission_applications', ['id' => $application->id]);
        $this->assertCount(2, $application->previousEducations);
        $this->assertNotNull($application->branch);
        $this->assertNotNull($application->desiredClass);
    }

    public function test_deleting_application_cascades_to_previous_educations(): void
    {
        $application = AdmissionApplication::factory()
            ->has(AdmissionPreviousEducation::factory()->count(3), 'previousEducations')
            ->create();

        $this->assertDatabaseCount('admission_previous_educations', 3);

        $application->delete();

        $this->assertDatabaseCount('admission_previous_educations', 0);
    }

    public function test_generator_produces_unique_sequential_numbers_per_branch(): void
    {
        $branch = Branch::factory()->create(['code' => 'MP']);
        $class = SchoolClass::factory()->create(['branch_id' => $branch->id]);
        $generator = app(ApplicationNoGenerator::class);

        $numbers = [];

        // Two creates against the same branch: each reads the latest committed
        // sequence, so the numbers advance without collision.
        for ($i = 0; $i < 2; $i++) {
            $no = $generator->generate($branch->id);
            AdmissionApplication::factory()->create([
                'branch_id' => $branch->id,
                'desired_class_id' => $class->id,
                'application_no' => $no,
            ]);
            $numbers[] = $no;
        }

        $this->assertSame(['APP-MP-00001', 'APP-MP-00002'], $numbers);
        $this->assertSame($numbers, array_unique($numbers));
    }

    public function test_generator_sequences_are_independent_across_branches(): void
    {
        $mirpur = Branch::factory()->create(['code' => 'MP']);
        $jatrabari = Branch::factory()->create(['code' => 'JA']);
        $generator = app(ApplicationNoGenerator::class);

        AdmissionApplication::factory()->create([
            'branch_id' => $mirpur->id,
            'application_no' => $generator->generate($mirpur->id),
        ]);

        $this->assertSame('APP-JA-00001', $generator->generate($jatrabari->id));
    }

    public function test_application_no_is_unique(): void
    {
        AdmissionApplication::factory()->create(['application_no' => 'APP-MP-00001']);

        $this->expectException(QueryException::class);

        AdmissionApplication::factory()->create(['application_no' => 'APP-MP-00001']);
    }

    public function test_birth_reg_no_is_unique(): void
    {
        AdmissionApplication::factory()->create(['birth_reg_no' => '19998877665544332']);

        $this->expectException(QueryException::class);

        AdmissionApplication::factory()->create(['birth_reg_no' => '19998877665544332']);
    }
}
