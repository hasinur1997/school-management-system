<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AcademicSession;
use App\Models\AnnualResult;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnualResultsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    /** @var array<string, Exam> */
    private array $exams = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(GradingScaleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create(['name' => '2026']);
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $this->section = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'A']);

        foreach (ExamType::cases() as $type) {
            $this->exams[$type->value] = Exam::factory()->forClass($this->class)->create([
                'session_id' => $this->session->id,
                'type' => $type,
                'status' => ExamStatus::Published,
            ]);
        }
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

    /**
     * Persist a published per-exam result for the given enrollment + exam type.
     */
    private function examResult(Enrollment $enrollment, ExamType $type, float $gpa, bool $passed = true, string $grade = 'A'): ExamResult
    {
        return ExamResult::factory()->published()->create([
            'exam_id' => $this->exams[$type->value]->id,
            'enrollment_id' => $enrollment->id,
            'gpa' => $gpa,
            'grade' => $grade,
            'is_passed' => $passed,
        ]);
    }

    private function generate(string $token)
    {
        return $this->withToken($token)->postJson('/api/v1/annual-results/generate', [
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);
    }

    public function test_generate_weights_25_25_50_with_half_up_rounding(): void
    {
        $enrollment = $this->enroll(1);
        // 0.25·3.55 + 0.25·3.55 + 0.50·3.56 = 3.555 → must round half-up to 3.56
        // (float accumulation would lose this edge).
        $this->examResult($enrollment, ExamType::FirstSemester, 3.55);
        $this->examResult($enrollment, ExamType::SecondSemester, 3.55);
        $this->examResult($enrollment, ExamType::Final, 3.56);

        $this->generate($this->staffToken())
            ->assertOk()
            ->assertJsonPath('message', 'Annual results generated')
            ->assertJsonPath('data.generated', 1)
            ->assertJsonPath('data.skipped', []);

        $this->assertDatabaseHas('annual_results', [
            'enrollment_id' => $enrollment->id,
            'first_semester_gpa' => 3.55,
            'second_semester_gpa' => 3.55,
            'final_exam_gpa' => 3.56,
            'annual_gpa' => 3.56,
            // GPA 3.56 maps to the A- band (grade point 3.50).
            'grade' => 'A-',
            'is_passed' => true,
            'published_at' => null,
        ]);
    }

    public function test_failed_final_fails_the_year_regardless_of_weighted_number(): void
    {
        $enrollment = $this->enroll(1);
        // Strong semesters, but the final was failed (is_passed false). The
        // weighted GPA stays high, yet the year must fail.
        $this->examResult($enrollment, ExamType::FirstSemester, 5.00);
        $this->examResult($enrollment, ExamType::SecondSemester, 5.00);
        $this->examResult($enrollment, ExamType::Final, 4.00, passed: false, grade: 'F');

        $this->generate($this->staffToken())
            ->assertOk()
            ->assertJsonPath('data.generated', 1);

        // 0.25·5 + 0.25·5 + 0.50·4 = 4.50 (grade A), but is_passed is false.
        $this->assertDatabaseHas('annual_results', [
            'enrollment_id' => $enrollment->id,
            'annual_gpa' => 4.50,
            'is_passed' => false,
        ]);
    }

    public function test_generate_requires_all_three_exams_published(): void
    {
        $this->exams[ExamType::SecondSemester->value]->update(['status' => ExamStatus::Completed]);

        $enrollment = $this->enroll(1);
        $this->examResult($enrollment, ExamType::FirstSemester, 4.00);
        $this->examResult($enrollment, ExamType::Final, 4.00);

        $this->generate($this->staffToken())
            ->assertStatus(409)
            ->assertJsonPath('message', 'All three exams must be published first');

        $this->assertDatabaseMissing('annual_results', ['enrollment_id' => $enrollment->id]);
    }

    public function test_enrollments_missing_an_exam_result_are_skipped_and_reported(): void
    {
        $complete = $this->enroll(1);
        $this->examResult($complete, ExamType::FirstSemester, 4.00);
        $this->examResult($complete, ExamType::SecondSemester, 4.00);
        $this->examResult($complete, ExamType::Final, 4.00);

        $partial = $this->enroll(2);
        // Missing the first semester result.
        $this->examResult($partial, ExamType::SecondSemester, 4.00);
        $this->examResult($partial, ExamType::Final, 4.00);

        $this->generate($this->staffToken())
            ->assertOk()
            ->assertJsonPath('data.generated', 1)
            ->assertJsonPath('data.skipped.0.enrollment_id', $partial->id)
            ->assertJsonPath('data.skipped.0.reason', 'missing first_semester result');

        $this->assertDatabaseHas('annual_results', ['enrollment_id' => $complete->id]);
        $this->assertDatabaseMissing('annual_results', ['enrollment_id' => $partial->id]);
    }

    public function test_regenerate_reflects_changes_then_publish_freezes_and_409s(): void
    {
        $enrollment = $this->enroll(1);
        $this->examResult($enrollment, ExamType::FirstSemester, 4.00);
        $this->examResult($enrollment, ExamType::SecondSemester, 4.00);
        $this->examResult($enrollment, ExamType::Final, 4.00);

        $token = $this->staffToken();

        $this->generate($token)->assertOk()->assertJsonPath('data.generated', 1);
        $this->assertDatabaseHas('annual_results', ['enrollment_id' => $enrollment->id, 'annual_gpa' => 4.00]);

        // Lower the final result and regenerate — the annual figure must follow
        // and there must be no duplicate row.
        ExamResult::where('enrollment_id', $enrollment->id)
            ->where('exam_id', $this->exams[ExamType::Final->value]->id)
            ->update(['gpa' => 3.00]);

        $this->generate($token)->assertOk()->assertJsonPath('data.generated', 1);

        $this->assertSame(1, AnnualResult::where('enrollment_id', $enrollment->id)->count());
        // 0.25·4 + 0.25·4 + 0.50·3 = 3.50.
        $this->assertDatabaseHas('annual_results', ['enrollment_id' => $enrollment->id, 'annual_gpa' => 3.50]);

        // Publish freezes the rows.
        $this->withToken($token)
            ->postJson('/api/v1/annual-results/publish', [
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.published', 1);

        $this->assertNotNull(AnnualResult::where('enrollment_id', $enrollment->id)->first()->published_at);

        // Regenerate after publish → 409.
        $this->generate($token)->assertStatus(409);

        // Re-publish → 409.
        $this->withToken($token)
            ->postJson('/api/v1/annual-results/publish', [
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
            ])
            ->assertStatus(409);
    }

    public function test_unknown_tuple_is_422(): void
    {
        $this->withToken($this->staffToken())
            ->postJson('/api/v1/annual-results/generate', [
                'session_id' => $this->session->id,
                'class_id' => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('class_id');
    }

    public function test_generate_requires_result_generate_permission(): void
    {
        // A teacher holds result.view but not result.generate.
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $user->createToken('web')->plainTextToken;

        $this->generate($token)->assertStatus(403);
    }

    public function test_tc_enrollment_is_excluded_from_generation(): void
    {
        $active = $this->enroll(1);
        $this->examResult($active, ExamType::FirstSemester, 4.00);
        $this->examResult($active, ExamType::SecondSemester, 4.00);
        $this->examResult($active, ExamType::Final, 4.00);

        // A transferred-out student keeps per-exam results but must not appear
        // in (or be skip-reported by) annual generation.
        $tc = $this->enroll(2, status: EnrollmentStatus::Tc);
        $this->examResult($tc, ExamType::FirstSemester, 4.00);
        $this->examResult($tc, ExamType::SecondSemester, 4.00);
        $this->examResult($tc, ExamType::Final, 4.00);

        $this->generate($this->staffToken())
            ->assertOk()
            ->assertJsonPath('data.generated', 1)
            ->assertJsonPath('data.skipped', []);

        $this->assertDatabaseMissing('annual_results', ['enrollment_id' => $tc->id]);
    }
}
