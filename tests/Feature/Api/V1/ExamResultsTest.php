<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamResultsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Exam $exam;

    /** @var array<string, Subject> */
    private array $subjects = [];

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

        foreach (['Bangla', 'English', 'Mathematics', 'Science'] as $name) {
            $this->subjects[$name] = Subject::factory()->create([
                'class_id' => $this->class->id,
                'name' => $name,
            ]);
        }

        $this->exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::FirstSemester,
            'status' => ExamStatus::Completed,
        ]);
    }

    private function staffToken(): string
    {
        $user = User::factory()->create(['branch_id' => null])->assignRole('super_admin');

        return $user->createToken('web')->plainTextToken;
    }

    private function enroll(int $rollNo, ?Section $section = null, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'name_en' => "Student {$rollNo}",
        ]);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => ($section ?? $this->section)->id,
            'roll_no' => $rollNo,
            'status' => $status,
        ]);
    }

    /**
     * Create a mark for the given enrollment + subject with an explicit grade
     * snapshot (mirrors what marks entry stores), so result math reads it.
     */
    private function mark(Enrollment $enrollment, string $subject, float $obtained, string $grade, float $point): Mark
    {
        return Mark::factory()->create([
            'exam_id' => $this->exam->id,
            'enrollment_id' => $enrollment->id,
            'subject_id' => $this->subjects[$subject]->id,
            'obtained_marks' => $obtained,
            'grade' => $grade,
            'grade_point' => $point,
            'entered_by' => User::factory()->create(['branch_id' => $this->branch->id])->id,
        ]);
    }

    /**
     * Give an enrollment a full, passing set of marks across all four subjects:
     * grade points 5.00, 4.00, 4.00, 3.50 → GPA 16.5/4 = 4.125 → 4.13 (proves
     * the average, two-dp half-up rounding, and grade-from-GPA mapping → A).
     * Total marks = 85 + 75 + 72 + 65 = 297.00.
     */
    private function fullPassingMarks(Enrollment $enrollment): void
    {
        $this->mark($enrollment, 'Bangla', 85, 'A+', 5.00);
        $this->mark($enrollment, 'English', 75, 'A', 4.00);
        $this->mark($enrollment, 'Mathematics', 72, 'A', 4.00);
        $this->mark($enrollment, 'Science', 65, 'A-', 3.50);
    }

    public function test_generate_computes_gpa_rounding_and_grade(): void
    {
        $enrollment = $this->enroll(1);
        $this->fullPassingMarks($enrollment);

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertOk()
            ->assertJsonPath('message', 'Results generated')
            ->assertJsonPath('data.generated', 1)
            ->assertJsonPath('data.skipped', []);

        $this->assertDatabaseHas('exam_results', [
            'exam_id' => $this->exam->id,
            'enrollment_id' => $enrollment->id,
            'total_marks' => 297.00,
            'gpa' => 4.13,
            'grade' => 'A',
            'is_passed' => true,
            'published_at' => null,
        ]);
    }

    public function test_any_failing_subject_fails_the_exam_and_overrides_grade(): void
    {
        $enrollment = $this->enroll(1);
        // Three strong subjects but one F — the exam must fail with grade F,
        // regardless of the (otherwise high) GPA.
        $this->mark($enrollment, 'Bangla', 90, 'A+', 5.00);
        $this->mark($enrollment, 'English', 85, 'A+', 5.00);
        $this->mark($enrollment, 'Mathematics', 80, 'A+', 5.00);
        $this->mark($enrollment, 'Science', 20, 'F', 0.00);

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertOk()
            ->assertJsonPath('data.generated', 1);

        $this->assertDatabaseHas('exam_results', [
            'enrollment_id' => $enrollment->id,
            'total_marks' => 275.00,
            'gpa' => 3.75,
            'grade' => 'F',
            'is_passed' => false,
        ]);
    }

    public function test_enrollments_missing_a_subject_are_skipped_and_reported(): void
    {
        $complete = $this->enroll(1);
        $this->fullPassingMarks($complete);

        $partial = $this->enroll(2);
        $this->mark($partial, 'Bangla', 85, 'A+', 5.00);
        $this->mark($partial, 'English', 75, 'A', 4.00);
        $this->mark($partial, 'Mathematics', 72, 'A', 4.00);
        // Science missing.

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertOk()
            ->assertJsonPath('data.generated', 1)
            ->assertJsonPath('data.skipped.0.enrollment_id', $partial->id)
            ->assertJsonPath('data.skipped.0.missing_subjects', ['Science']);

        $this->assertDatabaseHas('exam_results', ['enrollment_id' => $complete->id]);
        $this->assertDatabaseMissing('exam_results', ['enrollment_id' => $partial->id]);
    }

    public function test_no_marks_at_all_returns_422(): void
    {
        $this->enroll(1);

        $this->withToken($this->staffToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'No marks entered for this exam');
    }

    public function test_regenerate_reflects_changed_marks_then_publish_freezes(): void
    {
        $enrollment = $this->enroll(1);
        $this->fullPassingMarks($enrollment);

        $token = $this->staffToken();

        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertOk();

        $this->assertDatabaseHas('exam_results', ['enrollment_id' => $enrollment->id, 'gpa' => 4.13]);

        // Lower one subject and regenerate — the result must reflect it (and
        // there must be no duplicate row).
        Mark::where('enrollment_id', $enrollment->id)
            ->where('subject_id', $this->subjects['Science']->id)
            ->update(['obtained_marks' => 35, 'grade' => 'D', 'grade_point' => 1.00]);

        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertOk()
            ->assertJsonPath('data.generated', 1);

        $this->assertSame(1, ExamResult::where('enrollment_id', $enrollment->id)->count());
        // New grade points 5,4,4,1 → 14/4 = 3.50 → A- (the band whose grade
        // point is 3.50), down from the original 4.13 (grade A).
        $this->assertDatabaseHas('exam_results', [
            'enrollment_id' => $enrollment->id,
            'gpa' => 3.50,
            'grade' => 'A-',
        ]);

        // Publish freezes the rows and the exam.
        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/results/publish")
            ->assertOk()
            ->assertJsonPath('data.published', 1);

        $this->assertNotNull(ExamResult::where('enrollment_id', $enrollment->id)->first()->published_at);
        $this->assertDatabaseHas('exams', ['id' => $this->exam->id, 'status' => ExamStatus::Published->value]);

        // Regenerate after publish → 409.
        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertStatus(409);

        // Re-publish → 409.
        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/results/publish")
            ->assertStatus(409);
    }

    public function test_browse_orders_by_gpa_desc_and_filters(): void
    {
        $top = $this->enroll(1);
        $this->fullPassingMarks($top); // GPA 4.13, passed

        $failing = $this->enroll(2);
        $this->mark($failing, 'Bangla', 90, 'A+', 5.00);
        $this->mark($failing, 'English', 85, 'A+', 5.00);
        $this->mark($failing, 'Mathematics', 80, 'A+', 5.00);
        $this->mark($failing, 'Science', 20, 'F', 0.00); // failed

        $otherSection = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'B']);
        $elsewhere = $this->enroll(3, section: $otherSection);
        $this->fullPassingMarks($elsewhere);

        $token = $this->staffToken();

        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertOk()
            ->assertJsonPath('data.generated', 3);

        // Ordered by GPA desc: passing (4.13) before failing (3.75) within section A.
        $this->withToken($token)
            ->getJson("/api/v1/exams/{$this->exam->id}/results?section_id={$this->section->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.enrollment_id', $top->id)
            ->assertJsonPath('data.0.gpa', '4.13')
            ->assertJsonPath('data.0.total_marks', '297.00')
            ->assertJsonPath('data.1.enrollment_id', $failing->id);

        // is_passed filter narrows to the failing row.
        $this->withToken($token)
            ->getJson("/api/v1/exams/{$this->exam->id}/results?section_id={$this->section->id}&is_passed=0")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.enrollment_id', $failing->id)
            ->assertJsonPath('data.0.is_passed', false);
    }

    public function test_generate_requires_result_generate_permission(): void
    {
        $this->enroll(1);

        // A teacher holds result.view but not result.generate.
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/results/generate")
            ->assertStatus(403);
    }

    public function test_out_of_branch_exam_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $otherExam = Exam::factory()->create([
            'branch_id' => $otherBranch->id,
            'session_id' => $this->session->id,
            'class_id' => $otherClass->id,
        ]);

        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/exams/{$otherExam->id}/results")
            ->assertStatus(404);
    }
}
