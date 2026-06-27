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
use App\Models\Mark;
use App\Models\ParentProfile;
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

class ResultReadsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

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

        foreach (['Mathematics', 'English'] as $name) {
            $this->subjects[$name] = Subject::factory()->create([
                'class_id' => $this->class->id,
                'name' => $name,
            ]);
        }
    }

    private function staffToken(): string
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * Build a fully-populated, published first-semester result for one student:
     * an exam, two subject marks, a published exam_result and a published
     * annual_result. Returns the student + enrollment for assertions.
     *
     * @return array{student: Student, enrollment: Enrollment}
     */
    private function seedResult(int $rollNo, string $admissionNo, bool $published = true): array
    {
        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'name_en' => 'Rahima Khatun',
            'admission_no' => $admissionNo,
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $rollNo,
            'status' => EnrollmentStatus::Active,
        ]);

        // One exam per (session, type), covering this class; reused across students.
        $exam = Exam::firstOrCreate(
            [
                'session_id' => $this->session->id,
                'type' => ExamType::FirstSemester,
            ],
            [
                'branch_id' => $this->branch->id,
                'name' => 'First Semester 2026',
                'all_classes' => false,
                'start_date' => '2026-04-01',
                'end_date' => '2026-04-10',
                'status' => $published ? ExamStatus::Published : ExamStatus::Completed,
            ],
        );
        $exam->classes()->syncWithoutDetaching([$this->class->id]);

        Mark::factory()->create([
            'exam_id' => $exam->id,
            'enrollment_id' => $enrollment->id,
            'subject_id' => $this->subjects['Mathematics']->id,
            'obtained_marks' => 78.50,
            'grade' => 'A',
            'grade_point' => 4.00,
            'entered_by' => User::factory()->create(['branch_id' => $this->branch->id])->id,
        ]);

        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'enrollment_id' => $enrollment->id,
            'total_marks' => 78.50,
            'gpa' => 4.00,
            'grade' => 'A',
            'is_passed' => true,
            'published_at' => $published ? now() : null,
        ]);

        AnnualResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'first_semester_gpa' => 4.50,
            'second_semester_gpa' => 4.25,
            'final_exam_gpa' => 4.80,
            'annual_gpa' => 4.59,
            'grade' => 'A+',
            'is_passed' => true,
            'published_at' => $published ? now() : null,
        ]);

        return ['student' => $student, 'enrollment' => $enrollment];
    }

    public function test_search_by_admission_no_returns_full_bundle(): void
    {
        $this->seedResult(12, 'MP-2026-0009');

        $this->withToken($this->staffToken())
            ->getJson('/api/v1/results/search?admission_no=MP-2026-0009')
            ->assertOk()
            ->assertJsonPath('data.student.name_en', 'Rahima Khatun')
            ->assertJsonPath('data.student.admission_no', 'MP-2026-0009')
            ->assertJsonPath('data.student.class', 'Class 7')
            ->assertJsonPath('data.student.section', 'A')
            ->assertJsonPath('data.student.roll_no', 12)
            ->assertJsonPath('data.exams.0.type', 'first_semester')
            ->assertJsonPath('data.exams.0.published', true)
            ->assertJsonPath('data.exams.0.gpa', '4.00')
            ->assertJsonPath('data.exams.0.subjects.0.name', 'Mathematics')
            ->assertJsonPath('data.exams.0.subjects.0.obtained_marks', '78.50')
            ->assertJsonPath('data.exams.0.subjects.0.grade_point', '4.00')
            ->assertJsonPath('data.annual.annual_gpa', '4.59')
            ->assertJsonPath('data.annual.grade', 'A+')
            ->assertJsonPath('data.annual.published', true);
    }

    public function test_search_by_coordinates_returns_full_bundle(): void
    {
        $this->seedResult(12, 'MP-2026-0009');

        $url = "/api/v1/results/search?session_id={$this->session->id}&class_id={$this->class->id}&section_id={$this->section->id}&roll_no=12";

        $this->withToken($this->staffToken())
            ->getJson($url)
            ->assertOk()
            ->assertJsonPath('data.student.admission_no', 'MP-2026-0009')
            ->assertJsonPath('data.student.roll_no', 12);
    }

    public function test_search_no_match_is_404(): void
    {
        $this->withToken($this->staffToken())
            ->getJson('/api/v1/results/search?admission_no=NO-SUCH')
            ->assertStatus(404);
    }

    public function test_search_mixed_query_styles_is_422(): void
    {
        $this->withToken($this->staffToken())
            ->getJson('/api/v1/results/search?admission_no=MP-2026-0009&roll_no=12')
            ->assertStatus(422);
    }

    public function test_search_missing_query_is_422(): void
    {
        // Incomplete coordinates (only roll_no) count as neither style.
        $this->withToken($this->staffToken())
            ->getJson('/api/v1/results/search?roll_no=12')
            ->assertStatus(422);
    }

    public function test_out_of_branch_search_is_404(): void
    {
        $this->seedResult(12, 'MP-2026-0009');

        // A staff user in another branch cannot find the student.
        $otherBranch = Branch::factory()->create();
        $other = User::factory()->create(['branch_id' => $otherBranch->id])->assignRole('admin');

        $this->withToken($other->createToken('web')->plainTextToken)
            ->getJson('/api/v1/results/search?admission_no=MP-2026-0009')
            ->assertStatus(404);
    }

    public function test_staff_sees_unpublished_results_flagged(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009', published: false);

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/enrollments/{$seed['enrollment']->id}/results")
            ->assertOk()
            ->assertJsonCount(1, 'data.exams')
            ->assertJsonPath('data.exams.0.published', false)
            ->assertJsonPath('data.annual.published', false);
    }

    public function test_student_sees_published_results_only(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009', published: false);
        $student = $seed['student'];

        // Give the student a login.
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $student->update(['user_id' => $user->id]);

        $this->withToken($user->createToken('web')->plainTextToken)
            ->getJson("/api/v1/enrollments/{$seed['enrollment']->id}/results")
            ->assertOk()
            ->assertJsonCount(0, 'data.exams')
            ->assertJsonPath('data.annual', null);
    }

    public function test_student_sees_own_published_bundle(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009');
        $student = $seed['student'];

        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $student->update(['user_id' => $user->id]);

        $this->withToken($user->createToken('web')->plainTextToken)
            ->getJson("/api/v1/enrollments/{$seed['enrollment']->id}/results")
            ->assertOk()
            ->assertJsonCount(1, 'data.exams')
            ->assertJsonPath('data.exams.0.published', true)
            ->assertJsonPath('data.annual.published', true);
    }

    public function test_other_student_results_are_404(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009');

        // A different, unrelated student in the same branch.
        $intruder = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        Student::factory()->create(['branch_id' => $this->branch->id, 'user_id' => $intruder->id]);

        $this->withToken($intruder->createToken('web')->plainTextToken)
            ->getJson("/api/v1/enrollments/{$seed['enrollment']->id}/results")
            ->assertStatus(404);
    }

    public function test_linked_parent_can_read_child_results(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009');

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        $parent = ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);
        $parent->students()->attach($seed['student']->id);

        $this->withToken($parentUser->createToken('web')->plainTextToken)
            ->getJson("/api/v1/enrollments/{$seed['enrollment']->id}/results")
            ->assertOk()
            ->assertJsonPath('data.student.admission_no', 'MP-2026-0009');
    }

    public function test_unlinked_parent_gets_404(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009');

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);

        $this->withToken($parentUser->createToken('web')->plainTextToken)
            ->getJson("/api/v1/enrollments/{$seed['enrollment']->id}/results")
            ->assertStatus(404);
    }

    public function test_me_results_returns_own_for_student(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009');
        $student = $seed['student'];

        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('student');
        $student->update(['user_id' => $user->id]);

        // A passed student_id is ignored — the caller always gets their own.
        $other = $this->seedResult(13, 'MP-2026-0010');

        $this->withToken($user->createToken('web')->plainTextToken)
            ->getJson("/api/v1/me/results?student_id={$other['student']->id}")
            ->assertOk()
            ->assertJsonPath('data.student.admission_no', 'MP-2026-0009');
    }

    public function test_me_results_parent_linked_child(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009');

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        $parent = ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);
        $parent->students()->attach($seed['student']->id);

        $this->withToken($parentUser->createToken('web')->plainTextToken)
            ->getJson("/api/v1/me/results?student_id={$seed['student']->id}")
            ->assertOk()
            ->assertJsonPath('data.student.admission_no', 'MP-2026-0009');
    }

    public function test_me_results_parent_unlinked_child_is_404(): void
    {
        $seed = $this->seedResult(12, 'MP-2026-0009');

        $parentUser = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('parent');
        ParentProfile::factory()->create(['user_id' => $parentUser->id, 'branch_id' => $this->branch->id]);

        $this->withToken($parentUser->createToken('web')->plainTextToken)
            ->getJson("/api/v1/me/results?student_id={$seed['student']->id}")
            ->assertStatus(404);
    }
}
