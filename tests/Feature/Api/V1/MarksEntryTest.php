<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\ExamType;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Mark;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\GradeResolver;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarksEntryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Subject $subject;

    private Exam $exam;

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
        $this->subject = Subject::factory()->create([
            'class_id' => $this->class->id,
            'name' => 'Mathematics',
            'full_marks' => 100,
            'pass_marks' => 33,
        ]);
        $this->exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::FirstSemester,
        ]);
    }

    /**
     * A super admin holds marks.entry via Gate::before and has no teacher
     * profile, so it is the non-teacher staff that bypasses the assignment check.
     */
    private function superAdminToken(): string
    {
        $user = User::factory()->create(['branch_id' => null])->assignRole('super_admin');

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * Create a teacher login (users row + teacher profile) and return its token.
     *
     * @return array{0: Teacher, 1: string}
     */
    private function teacherToken(): array
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $teacher = Teacher::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $user->id]);

        return [$teacher, $user->createToken('web')->plainTextToken];
    }

    private function enroll(int $rollNo, ?Section $section = null, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
    {
        $student = Student::factory()->create(['branch_id' => $this->branch->id]);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => ($section ?? $this->section)->id,
            'roll_no' => $rollNo,
            'status' => $status,
        ]);
    }

    public function test_sheet_returns_roster_with_existing_marks(): void
    {
        $a = $this->enroll(1);
        $b = $this->enroll(2);

        Mark::factory()->create([
            'exam_id' => $this->exam->id,
            'enrollment_id' => $a->id,
            'subject_id' => $this->subject->id,
            'obtained_marks' => 78.50,
            'grade' => 'A',
            'grade_point' => 4.00,
            'entered_by' => User::factory()->create(['branch_id' => $this->branch->id])->id,
        ]);

        $this->withToken($this->superAdminToken())
            ->getJson("/api/v1/exams/{$this->exam->id}/marks/sheet?subject_id={$this->subject->id}&section_id={$this->section->id}")
            ->assertOk()
            ->assertJsonPath('data.subject.full_marks', 100)
            ->assertJsonPath('data.subject.pass_marks', 33)
            ->assertJsonCount(2, 'data.students')
            ->assertJsonPath('data.students.0.enrollment_id', $a->id)
            ->assertJsonPath('data.students.0.obtained_marks', 78.5)
            ->assertJsonPath('data.students.1.enrollment_id', $b->id)
            ->assertJsonPath('data.students.1.obtained_marks', null);
    }

    public function test_bulk_save_inserts_then_updates_with_snapshots(): void
    {
        $a = $this->enroll(1);
        $b = $this->enroll(2);

        DB::connection()->enableQueryLog();

        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [
                    ['enrollment_id' => $a->id, 'obtained_marks' => 78.5],
                    ['enrollment_id' => $b->id, 'obtained_marks' => 91],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 2);

        // The bulk write is a single insert statement, not one per record.
        $inserts = array_filter(
            DB::connection()->getQueryLog(),
            fn (array $entry): bool => str_contains(strtolower($entry['query']), 'insert into "marks"'),
        );
        $this->assertCount(1, $inserts);
        DB::connection()->disableQueryLog();

        // Grade + grade point snapshots resolved from the scale at entry.
        $this->assertDatabaseHas('marks', [
            'exam_id' => $this->exam->id, 'enrollment_id' => $a->id, 'subject_id' => $this->subject->id,
            'obtained_marks' => 78.50, 'grade' => 'A', 'grade_point' => 4.00,
        ]);
        $this->assertDatabaseHas('marks', [
            'exam_id' => $this->exam->id, 'enrollment_id' => $b->id, 'subject_id' => $this->subject->id,
            'obtained_marks' => 91.00, 'grade' => 'A+', 'grade_point' => 5.00,
        ]);

        // Re-post the same exam+subject with a changed mark — updates, no dupes.
        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [
                    ['enrollment_id' => $a->id, 'obtained_marks' => 55],
                    ['enrollment_id' => $b->id, 'obtained_marks' => 91],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 2);

        $this->assertSame(2, Mark::count());
        $this->assertDatabaseHas('marks', [
            'enrollment_id' => $a->id, 'obtained_marks' => 55.00, 'grade' => 'B', 'grade_point' => 3.00,
        ]);
    }

    public function test_stored_grade_is_immune_to_later_scale_changes(): void
    {
        $a = $this->enroll(1);

        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [['enrollment_id' => $a->id, 'obtained_marks' => 78.5]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('marks', [
            'enrollment_id' => $a->id, 'grade' => 'A', 'grade_point' => 4.00,
        ]);

        // Replace the scale so 78.5 would now resolve to A+ — stored mark must
        // not change (grade is snapshotted at entry).
        app(GradeResolver::class)->replace([
            ['grade' => 'A+', 'min_marks' => 70, 'max_marks' => 100, 'grade_point' => 5.00, 'is_fail' => false],
            ['grade' => 'A', 'min_marks' => 50, 'max_marks' => 69, 'grade_point' => 4.00, 'is_fail' => false],
            ['grade' => 'B', 'min_marks' => 33, 'max_marks' => 49, 'grade_point' => 3.00, 'is_fail' => false],
            ['grade' => 'F', 'min_marks' => 0, 'max_marks' => 32, 'grade_point' => 0.00, 'is_fail' => true],
        ]);

        $this->assertDatabaseHas('marks', [
            'enrollment_id' => $a->id, 'grade' => 'A', 'grade_point' => 4.00,
        ]);
    }

    public function test_unassigned_teacher_is_forbidden_but_super_admin_succeeds(): void
    {
        $a = $this->enroll(1);
        [, $teacherToken] = $this->teacherToken();

        $this->withToken($teacherToken)
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [['enrollment_id' => $a->id, 'obtained_marks' => 78.5]],
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'You are not assigned to this subject');

        // The sanctum guard caches the resolved user across requests in a single
        // test, so reset it before authenticating as a different user.
        $this->app['auth']->forgetGuards();

        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [['enrollment_id' => $a->id, 'obtained_marks' => 78.5]],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);
    }

    public function test_assigned_teacher_can_save(): void
    {
        $a = $this->enroll(1);
        [$teacher, $teacherToken] = $this->teacherToken();

        TeacherAssignment::factory()->create([
            'teacher_id' => $teacher->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        $this->withToken($teacherToken)
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [['enrollment_id' => $a->id, 'obtained_marks' => 78.5]],
            ])
            ->assertOk()
            ->assertJsonPath('data.saved', 1);
    }

    public function test_published_exam_is_frozen_with_409(): void
    {
        $a = $this->enroll(1);
        $published = Exam::factory()->published()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::Final,
        ]);

        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$published->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [['enrollment_id' => $a->id, 'obtained_marks' => 78.5]],
            ])
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Marks are frozen for published exams',
            ]);
    }

    public function test_out_of_range_marks_are_rejected_per_row(): void
    {
        $a = $this->enroll(1);
        $b = $this->enroll(2);

        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [
                    ['enrollment_id' => $a->id, 'obtained_marks' => 78.5],
                    ['enrollment_id' => $b->id, 'obtained_marks' => 120],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['marks.1.obtained_marks']);
    }

    public function test_subject_not_in_exam_class_is_rejected(): void
    {
        $a = $this->enroll(1);
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $foreignSubject = Subject::factory()->create(['class_id' => $otherClass->id]);

        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $foreignSubject->id,
                'marks' => [['enrollment_id' => $a->id, 'obtained_marks' => 78.5]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject_id']);
    }

    public function test_tc_enrollment_is_rejected_keyed_to_the_row(): void
    {
        $active = $this->enroll(1);
        $tc = $this->enroll(2, status: EnrollmentStatus::Tc);

        $this->withToken($this->superAdminToken())
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [
                    ['enrollment_id' => $active->id, 'obtained_marks' => 78.5],
                    ['enrollment_id' => $tc->id, 'obtained_marks' => 60],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['marks.1.enrollment_id']);
    }

    public function test_browse_lists_marks_for_the_exam(): void
    {
        $a = $this->enroll(1);
        Mark::factory()->create([
            'exam_id' => $this->exam->id,
            'enrollment_id' => $a->id,
            'subject_id' => $this->subject->id,
            'obtained_marks' => 78.50,
            'grade' => 'A',
            'grade_point' => 4.00,
            'entered_by' => User::factory()->create(['branch_id' => $this->branch->id])->id,
        ]);

        $this->withToken($this->superAdminToken())
            ->getJson("/api/v1/exams/{$this->exam->id}/marks?subject_id={$this->subject->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.grade', 'A')
            ->assertJsonPath('data.0.obtained_marks', 78.5)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_save_requires_marks_entry_permission(): void
    {
        $a = $this->enroll(1);

        // An admin holds marks.view but not marks.entry.
        $admin = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $token = $admin->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson("/api/v1/exams/{$this->exam->id}/marks", [
                'subject_id' => $this->subject->id,
                'marks' => [['enrollment_id' => $a->id, 'obtained_marks' => 78.5]],
            ])
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

        $this->withToken($this->superAdminToken())
            ->getJson("/api/v1/exams/{$otherExam->id}/marks?subject_id={$this->subject->id}")
            ->assertOk();

        // A non-super-admin in this branch cannot see the other branch's exam.
        $teacherUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $teacherToken = $teacherUser->createToken('web')->plainTextToken;

        $this->app['auth']->forgetGuards();

        $this->withToken($teacherToken)
            ->getJson("/api/v1/exams/{$otherExam->id}/marks?subject_id={$this->subject->id}")
            ->assertStatus(404);
    }
}
