<?php

namespace Tests\Feature;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\Student;
use App\Services\AdmissionNoGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTablesTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_builds_coherent_student_with_enrollment_graph(): void
    {
        $enrollment = Enrollment::factory()->create();
        $student = $enrollment->student;

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertNotNull($student->user);
        $this->assertNotNull($student->branch);
        // Class and section are coherent: the section belongs to the class.
        $this->assertSame($enrollment->class_id, $enrollment->section->class_id);
        $this->assertNotNull($enrollment->session);
        $this->assertTrue($student->enrollments->contains($enrollment));
    }

    public function test_parent_student_linking_via_factory(): void
    {
        $parent = ParentProfile::factory()
            ->hasAttached(Student::factory()->count(2), [], 'students')
            ->create();

        $this->assertCount(2, $parent->students);
        $this->assertDatabaseCount('parent_student', 2);

        $student = $parent->students->first();
        $this->assertTrue($student->load('parents')->parents->contains($parent));
    }

    public function test_current_enrollment_resolves_to_the_current_session(): void
    {
        $student = Student::factory()->create();
        $current = AcademicSession::factory()->current()->create();
        $past = AcademicSession::factory()->create();

        $pastEnrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $past->id,
        ]);
        $currentEnrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $current->id,
        ]);

        $resolved = $student->currentEnrollment;

        $this->assertNotNull($resolved);
        $this->assertSame($currentEnrollment->id, $resolved->id);
        $this->assertNotSame($pastEnrollment->id, $resolved->id);
    }

    public function test_student_and_session_pair_is_unique(): void
    {
        $student = Student::factory()->create();
        $session = AcademicSession::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $session->id,
        ]);

        $this->expectException(QueryException::class);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $session->id,
        ]);
    }

    public function test_roll_is_unique_within_session_class_section(): void
    {
        $first = Enrollment::factory()->create(['roll_no' => 5]);

        $this->expectException(QueryException::class);

        // Same session/class/section, same roll — duplicate roll for a
        // different student must violate the composite unique index.
        Enrollment::factory()->create([
            'session_id' => $first->session_id,
            'class_id' => $first->class_id,
            'section_id' => $first->section_id,
            'roll_no' => 5,
        ]);
    }

    public function test_admission_no_is_unique(): void
    {
        Student::factory()->create(['admission_no' => 'STU-MP-2026-00001']);

        $this->expectException(QueryException::class);

        Student::factory()->create(['admission_no' => 'STU-MP-2026-00001']);
    }

    public function test_birth_reg_no_is_unique(): void
    {
        Student::factory()->create(['birth_reg_no' => '19998877665544332']);

        $this->expectException(QueryException::class);

        Student::factory()->create(['birth_reg_no' => '19998877665544332']);
    }

    public function test_admission_no_generator_produces_sequential_per_branch_year_numbers(): void
    {
        $branch = Branch::factory()->create(['code' => 'MP']);
        $generator = app(AdmissionNoGenerator::class);

        $numbers = [];

        for ($i = 0; $i < 2; $i++) {
            $no = $generator->generate($branch->id, 2026);
            Student::factory()->create([
                'branch_id' => $branch->id,
                'admission_no' => $no,
            ]);
            $numbers[] = $no;
        }

        $this->assertSame(['STU-MP-2026-00001', 'STU-MP-2026-00002'], $numbers);
    }

    public function test_admission_no_generator_sequences_reset_per_year_and_branch(): void
    {
        $mirpur = Branch::factory()->create(['code' => 'MP']);
        $jatrabari = Branch::factory()->create(['code' => 'JA']);
        $generator = app(AdmissionNoGenerator::class);

        Student::factory()->create([
            'branch_id' => $mirpur->id,
            'admission_no' => $generator->generate($mirpur->id, 2026),
        ]);

        // Different branch starts fresh.
        $this->assertSame('STU-JA-2026-00001', $generator->generate($jatrabari->id, 2026));
        // Different year on the same branch starts fresh.
        $this->assertSame('STU-MP-2027-00001', $generator->generate($mirpur->id, 2027));
    }
}
