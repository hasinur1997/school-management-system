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

class IndividualPromotionTest extends TestCase
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

    /** Token for a user holding only the listed permissions (no override). */
    private function tokenWith(array $permissions): string
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        $user->givePermissionTo($permissions);

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

    private function annualResult(Enrollment $enrollment, bool $passed, ?\DateTimeInterface $publishedAt): AnnualResult
    {
        return AnnualResult::create([
            'enrollment_id' => $enrollment->id,
            'first_semester_gpa' => '4.00',
            'second_semester_gpa' => '4.00',
            'final_exam_gpa' => '4.00',
            'annual_gpa' => '4.00',
            'grade' => $passed ? 'A' : 'F',
            'is_passed' => $passed,
            'published_at' => $publishedAt,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function promote(string $token, Enrollment $source, array $overrides = [])
    {
        return $this->withToken($token)->postJson('/api/v1/promotions/individual', array_merge([
            'student_id' => $source->student_id,
            'to_session_id' => $this->toSession->id,
            'to_class_id' => $this->nextClass->id,
            'to_section_id' => $this->toSection->id,
            'roll_no' => 5,
        ], $overrides));
    }

    public function test_passed_student_happy_path(): void
    {
        $source = $this->enroll(1);
        $this->annualResult($source, passed: true, publishedAt: now());

        $this->promote($this->tokenWith(['promotion.execute']), $source)
            ->assertOk()
            ->assertJsonPath('data.student.id', $source->student_id)
            ->assertJsonPath('data.from.class', 'Class 7')
            ->assertJsonPath('data.from.session', '2025')
            ->assertJsonPath('data.to.class', 'Class 8')
            ->assertJsonPath('data.to.session', '2026')
            ->assertJsonPath('data.to.roll_no', 5)
            ->assertJsonPath('data.type', 'individual');

        // Old enrollment closed; new one opened in the target.
        $this->assertSame(EnrollmentStatus::Promoted, $source->fresh()->status);

        $new = Enrollment::where('student_id', $source->student_id)
            ->where('session_id', $this->toSession->id)->first();
        $this->assertSame($this->nextClass->id, $new->class_id);
        $this->assertSame($this->toSection->id, $new->section_id);
        $this->assertSame(5, $new->roll_no);
        $this->assertSame(EnrollmentStatus::Active, $new->status);

        // Logged as type individual, linking both enrollments.
        $log = Promotion::where('from_enrollment_id', $source->id)->first();
        $this->assertSame(PromotionType::Individual, $log->type);
        $this->assertSame($new->id, $log->to_enrollment_id);
    }

    public function test_failed_student_without_override_is_403(): void
    {
        $source = $this->enroll(1);
        $this->annualResult($source, passed: false, publishedAt: now());

        $this->promote($this->tokenWith(['promotion.execute']), $source)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Student has not passed; override permission required');

        // Nothing changed.
        $this->assertSame(EnrollmentStatus::Active, $source->fresh()->status);
        $this->assertSame(0, Promotion::count());
    }

    public function test_failed_student_with_override_is_200(): void
    {
        $source = $this->enroll(1);
        $this->annualResult($source, passed: false, publishedAt: now());

        $this->promote($this->tokenWith(['promotion.execute', 'promotion.override']), $source)
            ->assertOk()
            ->assertJsonPath('data.type', 'individual');

        $this->assertSame(EnrollmentStatus::Promoted, $source->fresh()->status);
        $this->assertSame(PromotionType::Individual, Promotion::first()->type);
    }

    public function test_duplicate_roll_in_target_section_is_422(): void
    {
        $source = $this->enroll(1);
        $this->annualResult($source, passed: true, publishedAt: now());

        // A student already holds roll 5 in the target section/session.
        $squatter = Student::factory()->create(['branch_id' => $this->branch->id]);
        Enrollment::factory()->create([
            'student_id' => $squatter->id,
            'session_id' => $this->toSession->id,
            'class_id' => $this->nextClass->id,
            'section_id' => $this->toSection->id,
            'roll_no' => 5,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->promote($this->tokenWith(['promotion.execute']), $source)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roll_no']);
    }

    public function test_roll_number_above_storage_limit_is_422(): void
    {
        $source = $this->enroll(1);
        $this->annualResult($source, passed: true, publishedAt: now());

        $this->promote($this->tokenWith(['promotion.execute']), $source, ['roll_no' => 405060])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['roll_no']);

        $this->assertSame(EnrollmentStatus::Active, $source->fresh()->status);
        $this->assertSame(0, Promotion::count());
    }

    public function test_already_enrolled_in_target_session_is_409(): void
    {
        $source = $this->enroll(1);
        $this->annualResult($source, passed: true, publishedAt: now());

        // The student already sits in the target session.
        Enrollment::factory()->create([
            'student_id' => $source->student_id,
            'session_id' => $this->toSession->id,
            'class_id' => $this->nextClass->id,
            'section_id' => $this->toSection->id,
            'roll_no' => 9,
            'status' => EnrollmentStatus::Active,
        ]);

        $this->promote($this->tokenWith(['promotion.execute']), $source)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Student is already enrolled in the target session');
    }

    public function test_requires_promotion_execute_permission(): void
    {
        $source = $this->enroll(1);
        $this->annualResult($source, passed: true, publishedAt: now());

        $token = $this->tokenWith(['promotion.view']);

        $this->promote($token, $source)->assertStatus(403);
    }

    public function test_history_filters_by_type(): void
    {
        // One individual promotion.
        $individual = $this->enroll(1);
        $this->annualResult($individual, passed: true, publishedAt: now());
        $this->promote($this->tokenWith(['promotion.execute']), $individual)->assertOk();

        // One bulk promotion (logged via a direct record for the history read).
        $bulkSource = $this->enroll(2);
        $bulkTarget = Enrollment::factory()->create([
            'student_id' => $bulkSource->student_id,
            'session_id' => $this->toSession->id,
            'class_id' => $this->nextClass->id,
            'section_id' => $this->toSection->id,
            'roll_no' => 2,
            'status' => EnrollmentStatus::Active,
        ]);
        Promotion::create([
            'student_id' => $bulkSource->student_id,
            'from_enrollment_id' => $bulkSource->id,
            'to_enrollment_id' => $bulkTarget->id,
            'type' => PromotionType::Bulk->value,
            'promoted_by' => User::factory()->create(['branch_id' => $this->branch->id])->id,
            'promoted_at' => now(),
        ]);

        $token = $this->tokenWith(['promotion.view']);

        // Unfiltered: both rows, each carrying student + from/to class names.
        // forgetGuards() clears the sanctum guard cached from the promote() call.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/promotions')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.from.class', 'Class 7')
            ->assertJsonPath('data.0.to.class', 'Class 8');

        // Filtered by type: only the individual row.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/promotions?type=individual')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'individual')
            ->assertJsonPath('data.0.student.id', $individual->student_id);
    }

    public function test_history_requires_promotion_view_permission(): void
    {
        $token = $this->tokenWith(['promotion.execute']);

        $this->withToken($token)->getJson('/api/v1/promotions')->assertStatus(403);
    }
}
