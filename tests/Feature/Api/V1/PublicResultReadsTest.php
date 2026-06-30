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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicResultReadsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    /** @var list<Subject> */
    private array $subjects;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->create(['name' => '2026']);
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $this->section = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'A']);

        $this->subjects = [
            Subject::factory()->create([
                'class_id' => $this->class->id,
                'name' => 'Mathematics',
                'code' => 'MATH7',
            ]),
            Subject::factory()->create([
                'class_id' => $this->class->id,
                'name' => 'English',
                'code' => 'ENG7',
            ]),
        ];
    }

    private function seedPublishedResult(int $rollNo = 12, ?Section $section = null, ExamType $type = ExamType::Final): Enrollment
    {
        $section ??= $this->section;

        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'name_en' => 'Rahima Khatun',
            'father_name_en' => 'Abdul Karim',
            'mother_name_en' => 'Amena Begum',
            'date_of_birth' => '2014-03-09',
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $section->id,
            'roll_no' => $rollNo,
            'status' => EnrollmentStatus::Active,
        ]);

        $exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'type' => $type,
            'name' => str_replace('_', ' ', $type->value).' 2026',
            'status' => ExamStatus::Published,
        ]);
        $exam->classes()->attach($this->class->id);

        foreach ($this->subjects as $index => $subject) {
            Mark::factory()->create([
                'exam_id' => $exam->id,
                'enrollment_id' => $enrollment->id,
                'subject_id' => $subject->id,
                'obtained_marks' => $index === 0 ? 90 : 87,
                'grade' => 'A+',
                'grade_point' => 5.00,
                'entered_by' => User::factory()->create(['branch_id' => $this->branch->id])->id,
            ]);
        }

        ExamResult::factory()->create([
            'exam_id' => $exam->id,
            'enrollment_id' => $enrollment->id,
            'total_marks' => 177,
            'gpa' => 5.00,
            'grade' => 'A+',
            'is_passed' => true,
            'published_at' => now(),
        ]);

        return $enrollment;
    }

    public function test_public_result_lookup_returns_student_and_subject_marks(): void
    {
        $this->seedPublishedResult();

        $this->getJson("/api/v1/public/results?branch_id={$this->branch->public_id}&roll_no=12&class_id={$this->class->public_id}&year=2026&semester=final")
            ->assertOk()
            ->assertJsonPath('data.student_information.roll_no', 12)
            ->assertJsonPath('data.student_information.student_name', 'Rahima Khatun')
            ->assertJsonPath('data.student_information.father_name', 'Abdul Karim')
            ->assertJsonPath('data.student_information.mother_name', 'Amena Begum')
            ->assertJsonPath('data.student_information.class', 'Class 7')
            ->assertJsonPath('data.student_information.section', 'A')
            ->assertJsonPath('data.student_information.session', '2026')
            ->assertJsonPath('data.student_information.semester', 'final')
            ->assertJsonPath('data.student_information.date_of_birth', '2014-03-09')
            ->assertJsonPath('data.student_information.result', '5.00')
            ->assertJsonPath('data.subjects.0.subject_code', 'MATH7')
            ->assertJsonPath('data.subjects.0.subject_name', 'Mathematics')
            ->assertJsonPath('data.subjects.0.marks', '90.00')
            ->assertJsonPath('data.subjects.0.grade', 'A+')
            ->assertJsonCount(2, 'data.subjects');
    }

    public function test_public_result_lookup_accepts_semister_alias(): void
    {
        $this->seedPublishedResult(type: ExamType::FirstSemester);

        $this->getJson("/api/v1/public/results?branch_id={$this->branch->public_id}&roll_no=12&class_id={$this->class->public_id}&year=2026&semister=first_semester")
            ->assertOk()
            ->assertJsonPath('data.student_information.semester', 'first_semester');
    }

    public function test_public_result_lookup_hides_unpublished_results(): void
    {
        $enrollment = $this->seedPublishedResult();
        ExamResult::where('enrollment_id', $enrollment->id)->update(['published_at' => null]);

        $this->getJson("/api/v1/public/results?branch_id={$this->branch->public_id}&roll_no=12&class_id={$this->class->public_id}&year=2026&semester=final")
            ->assertStatus(404);
    }

    public function test_public_result_lookup_rejects_ambiguous_roll_number(): void
    {
        $this->seedPublishedResult();

        $otherSection = Section::factory()->create(['class_id' => $this->class->id, 'name' => 'B']);
        $this->seedPublishedResult(section: $otherSection);

        $this->getJson("/api/v1/public/results?branch_id={$this->branch->public_id}&roll_no=12&class_id={$this->class->public_id}&year=2026&semester=final")
            ->assertStatus(422)
            ->assertJsonPath('errors.roll_no.0', 'Multiple students match this roll number for the selected class and year.');
    }

    public function test_public_result_lookup_rejects_class_outside_selected_branch(): void
    {
        $this->seedPublishedResult();
        $otherBranch = Branch::factory()->create();

        $this->getJson("/api/v1/public/results?branch_id={$otherBranch->public_id}&roll_no=12&class_id={$this->class->public_id}&year=2026&semester=final")
            ->assertStatus(422)
            ->assertJsonPath('errors.class_id.0', 'The selected class does not belong to the selected branch.');
    }

    public function test_public_result_lookup_validates_required_inputs(): void
    {
        $this->getJson('/api/v1/public/results?roll_no=12')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['branch_id', 'class_id', 'year', 'semester']);
    }
}
