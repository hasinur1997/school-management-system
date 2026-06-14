<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\AnnualResult;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionPreviewTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private SchoolClass $nextClass;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create(['name' => '2026']);
        $this->class = SchoolClass::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Class 7',
            'numeric_level' => 7,
        ]);
        $this->nextClass = SchoolClass::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Class 8',
            'numeric_level' => 8,
        ]);
        $this->section = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'A']);
    }

    private function staffToken(): string
    {
        $user = User::factory()->create(['branch_id' => null])->assignRole('super_admin');

        return $user->createToken('web')->plainTextToken;
    }

    private function enroll(int $rollNo, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'name_en' => "Student {$rollNo}",
        ]);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $rollNo,
            'status' => $status,
        ]);
    }

    private function annualResult(Enrollment $enrollment, bool $passed, ?\DateTimeInterface $publishedAt, string $gpa = '4.00'): AnnualResult
    {
        return AnnualResult::create([
            'enrollment_id' => $enrollment->id,
            'first_semester_gpa' => $gpa,
            'second_semester_gpa' => $gpa,
            'final_exam_gpa' => $gpa,
            'annual_gpa' => $gpa,
            'grade' => $passed ? 'A' : 'F',
            'is_passed' => $passed,
            'published_at' => $publishedAt,
        ]);
    }

    private function preview(string $token, ?int $sessionId = null, ?int $classId = null)
    {
        $query = http_build_query([
            'session_id' => $sessionId ?? $this->session->id,
            'class_id' => $classId ?? $this->class->id,
        ]);

        return $this->withToken($token)->getJson("/api/v1/promotions/preview?{$query}");
    }

    public function test_mixed_cohort_splits_eligible_and_not_eligible_with_reasons(): void
    {
        $passed = $this->enroll(1);
        $this->annualResult($passed, passed: true, publishedAt: now(), gpa: '4.59');

        $failed = $this->enroll(2);
        $this->annualResult($failed, passed: false, publishedAt: now());

        // Has an enrollment but no annual result row at all.
        $noResult = $this->enroll(3);

        $tc = $this->enroll(4, status: EnrollmentStatus::Tc);

        $response = $this->preview($this->staffToken())
            ->assertOk()
            ->assertJsonPath('message', 'OK')
            ->assertJsonPath('data.to_class.id', $this->nextClass->id)
            ->assertJsonPath('data.to_class.name', 'Class 8');

        // Exactly one eligible (the passed student), with their gpa + roll.
        $response->assertJsonCount(1, 'data.eligible')
            ->assertJsonPath('data.eligible.0.student_id', $passed->student_id)
            ->assertJsonPath('data.eligible.0.name_en', 'Student 1')
            ->assertJsonPath('data.eligible.0.roll_no', 1)
            ->assertJsonPath('data.eligible.0.annual_gpa', '4.59');

        // Three not-eligible: failed, no_result, tc.
        $response->assertJsonCount(3, 'data.not_eligible');

        $reasons = collect($response->json('data.not_eligible'))
            ->mapWithKeys(fn (array $row): array => [$row['student_id'] => $row['reason']]);

        $this->assertSame('failed', $reasons[$failed->student_id]);
        $this->assertSame('no_result', $reasons[$noResult->student_id]);
        $this->assertSame('tc', $reasons[$tc->student_id]);
    }

    public function test_unpublished_annual_results_409(): void
    {
        $enrollment = $this->enroll(1);
        // Generated but not published.
        $this->annualResult($enrollment, passed: true, publishedAt: null);

        $this->preview($this->staffToken())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Publish annual results first');
    }

    public function test_top_class_resolves_to_class_null(): void
    {
        // A class at the top numeric level has no next class to promote into.
        $topClass = SchoolClass::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Class 12',
            'numeric_level' => 12,
        ]);
        $topSection = Section::factory()->create(['class_id' => $topClass->id, 'name' => 'A']);

        $student = Student::factory()->create(['branch_id' => $this->branch->id, 'name_en' => 'Topper']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $topClass->id,
            'section_id' => $topSection->id,
            'roll_no' => 1,
            'status' => EnrollmentStatus::Active,
        ]);
        $this->annualResult($enrollment, passed: true, publishedAt: now());

        $this->preview($this->staffToken(), classId: $topClass->id)
            ->assertOk()
            ->assertJsonPath('data.to_class', null)
            ->assertJsonCount(1, 'data.eligible');
    }

    public function test_missing_params_422(): void
    {
        $this->withToken($this->staffToken())
            ->getJson('/api/v1/promotions/preview')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['session_id', 'class_id']);
    }

    public function test_requires_promotion_execute_permission(): void
    {
        // A teacher holds no promotion permissions.
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $user->createToken('web')->plainTextToken;

        $this->preview($token)->assertStatus(403);
    }
}
