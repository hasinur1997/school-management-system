<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\PromotionType;
use App\Models\AcademicSession;
use App\Models\AnnualResult;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Promotion;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkPromotionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $fromSession;

    private AcademicSession $toSession;

    private SchoolClass $class;

    private SchoolClass $nextClass;

    private Section $section;

    private Section $toSection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->fromSession = AcademicSession::factory()->current()->create(['name' => '2025']);
        $this->toSession = AcademicSession::factory()->create(['name' => '2026']);

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
        $this->toSection = Section::factory()->create(['class_id' => $this->nextClass->id, 'name' => 'A']);
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
            'session_id' => $this->fromSession->id,
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function bulk(string $token, array $overrides = [])
    {
        return $this->withToken($token)->postJson('/api/v1/promotions/bulk', array_merge([
            'from_session_id' => $this->fromSession->id,
            'from_class_id' => $this->class->id,
            'to_session_id' => $this->toSession->id,
            'to_section_id' => $this->toSection->id,
            'roll_strategy' => 'by_merit',
        ], $overrides));
    }

    public function test_by_merit_happy_path_counts_statuses_logs_and_rolls(): void
    {
        // Old rolls deliberately do not match merit order.
        $low = $this->enroll(1);
        $this->annualResult($low, passed: true, publishedAt: now(), gpa: '3.00');

        $top = $this->enroll(2);
        $this->annualResult($top, passed: true, publishedAt: now(), gpa: '5.00');

        $mid = $this->enroll(3);
        $this->annualResult($mid, passed: true, publishedAt: now(), gpa: '4.00');

        $failed = $this->enroll(4);
        $this->annualResult($failed, passed: false, publishedAt: now());

        $this->bulk($this->staffToken())
            ->assertOk()
            ->assertJsonPath('data.promoted', 3)
            ->assertJsonPath('data.held', 1);

        // Old enrollments closed: promoted vs failed.
        $this->assertSame(EnrollmentStatus::Promoted, $low->fresh()->status);
        $this->assertSame(EnrollmentStatus::Promoted, $top->fresh()->status);
        $this->assertSame(EnrollmentStatus::Promoted, $mid->fresh()->status);
        $this->assertSame(EnrollmentStatus::Failed, $failed->fresh()->status);

        // by_merit reassigns rolls 1..n by annual GPA desc in the target section.
        $newRoll = fn (Enrollment $old): int => Enrollment::where('student_id', $old->student_id)
            ->where('session_id', $this->toSession->id)
            ->value('roll_no');

        $this->assertSame(1, $newRoll($top));
        $this->assertSame(2, $newRoll($mid));
        $this->assertSame(3, $newRoll($low));

        // Promoted students land in the next class + target section, active.
        $newTop = Enrollment::where('student_id', $top->student_id)
            ->where('session_id', $this->toSession->id)->first();
        $this->assertSame($this->nextClass->id, $newTop->class_id);
        $this->assertSame($this->toSection->id, $newTop->section_id);
        $this->assertSame(EnrollmentStatus::Active, $newTop->status);

        // Every move logged as type bulk; promoted link to a new enrollment,
        // the held row carries a null to_enrollment_id.
        $this->assertSame(4, Promotion::count());
        $this->assertSame(4, Promotion::where('type', PromotionType::Bulk->value)->count());

        $topLog = Promotion::where('from_enrollment_id', $top->id)->first();
        $this->assertSame($newTop->id, $topLog->to_enrollment_id);

        $failedLog = Promotion::where('from_enrollment_id', $failed->id)->first();
        $this->assertNull($failedLog->to_enrollment_id);
    }

    public function test_keep_strategy_carries_old_roll_and_holds_failed_in_same_class(): void
    {
        $passed = $this->enroll(7);
        $this->annualResult($passed, passed: true, publishedAt: now(), gpa: '4.50');

        $failed = $this->enroll(9);
        $this->annualResult($failed, passed: false, publishedAt: now());

        $this->bulk($this->staffToken(), ['roll_strategy' => 'keep'])
            ->assertOk()
            ->assertJsonPath('data.promoted', 1)
            ->assertJsonPath('data.held', 1);

        // keep: the passed student carries roll 7 into the next class.
        $newPassed = Enrollment::where('student_id', $passed->student_id)
            ->where('session_id', $this->toSession->id)->first();
        $this->assertSame($this->nextClass->id, $newPassed->class_id);
        $this->assertSame(7, $newPassed->roll_no);

        // The failed student is re-enrolled in the SAME class for the new
        // session, keeping roll + section, status active.
        $newFailed = Enrollment::where('student_id', $failed->student_id)
            ->where('session_id', $this->toSession->id)->first();
        $this->assertSame($this->class->id, $newFailed->class_id);
        $this->assertSame($this->section->id, $newFailed->section_id);
        $this->assertSame(9, $newFailed->roll_no);
        $this->assertSame(EnrollmentStatus::Active, $newFailed->status);
        $this->assertSame(EnrollmentStatus::Failed, $failed->fresh()->status);
    }

    public function test_tc_and_no_result_students_are_untouched(): void
    {
        $passed = $this->enroll(1);
        $this->annualResult($passed, passed: true, publishedAt: now());

        $tc = $this->enroll(2, status: EnrollmentStatus::Tc);
        $this->annualResult($tc, passed: true, publishedAt: now());

        $noResult = $this->enroll(3);

        $this->bulk($this->staffToken())
            ->assertOk()
            ->assertJsonPath('data.promoted', 1)
            ->assertJsonPath('data.held', 0);

        // TC stays TC; the no-result active student stays active. Neither gets a
        // new enrollment or a promotion log.
        $this->assertSame(EnrollmentStatus::Tc, $tc->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $noResult->fresh()->status);
        $this->assertSame(0, Enrollment::where('session_id', $this->toSession->id)
            ->whereIn('student_id', [$tc->student_id, $noResult->student_id])->count());
        $this->assertSame(1, Promotion::count());
    }

    public function test_induced_failure_mid_run_rolls_everything_back(): void
    {
        $a = $this->enroll(1);
        $this->annualResult($a, passed: true, publishedAt: now(), gpa: '5.00');

        $b = $this->enroll(2);
        $this->annualResult($b, passed: true, publishedAt: now(), gpa: '4.00');

        // A squatter (not in the cohort) already occupies roll 1 of the target
        // section/session, so the by_merit insert (top passer → roll 1) hits the
        // unique(session, class, section, roll) constraint mid-transaction.
        $squatter = Student::factory()->create(['branch_id' => $this->branch->id]);
        Enrollment::factory()->create([
            'student_id' => $squatter->id,
            'session_id' => $this->toSession->id,
            'class_id' => $this->nextClass->id,
            'section_id' => $this->toSection->id,
            'roll_no' => 1,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->bulk($this->staffToken())->assertStatus(500);

        // Atomicity: nothing changed. Old enrollments still active, no logs, no
        // new cohort enrollments.
        $this->assertSame(EnrollmentStatus::Active, $a->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $b->fresh()->status);
        $this->assertSame(0, Promotion::count());
        $this->assertSame(0, Enrollment::where('session_id', $this->toSession->id)
            ->whereIn('student_id', [$a->student_id, $b->student_id])->count());
    }

    public function test_rerun_is_409(): void
    {
        $passed = $this->enroll(1);
        $this->annualResult($passed, passed: true, publishedAt: now());

        $this->bulk($this->staffToken())->assertOk();

        $this->bulk($this->staffToken())
            ->assertStatus(409)
            ->assertJsonPath('message', 'This class has already been promoted for the target session');
    }

    public function test_unpublished_results_is_409(): void
    {
        $enrollment = $this->enroll(1);
        $this->annualResult($enrollment, passed: true, publishedAt: null);

        $this->bulk($this->staffToken())
            ->assertStatus(409)
            ->assertJsonPath('message', 'Publish annual results first');
    }

    public function test_same_session_is_422(): void
    {
        $this->bulk($this->staffToken(), ['to_session_id' => $this->fromSession->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_session_id']);
    }

    public function test_section_not_in_next_class_is_422(): void
    {
        // A section that belongs to the source class, not the next class.
        $this->bulk($this->staffToken(), ['to_section_id' => $this->section->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to_section_id']);
    }

    public function test_requires_promotion_execute_permission(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $user->createToken('web')->plainTextToken;

        $this->bulk($token)->assertStatus(403);
    }
}
