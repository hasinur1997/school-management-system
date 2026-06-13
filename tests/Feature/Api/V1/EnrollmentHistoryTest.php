<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\ParentProfile;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
    }

    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    private function makeStudent(?Branch $branch = null): Student
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('student');

        return Student::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);
    }

    private function makeParent(?Branch $branch = null): ParentProfile
    {
        $branch ??= $this->branch;
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->assignRole('parent');

        return ParentProfile::factory()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Build a two-session class history for a student: an older promoted row and
     * a current active row. Returns the student.
     */
    private function withHistory(Student $student): Student
    {
        $old = AcademicSession::factory()->create(['name' => '2025', 'is_current' => false]);
        $current = AcademicSession::factory()->current()->create(['name' => '2026']);

        $class6 = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 6']);
        $section6 = Section::factory()->create(['class_id' => $class6->id, 'name' => 'A']);
        $class7 = SchoolClass::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Class 7']);
        $section7 = Section::factory()->create(['class_id' => $class7->id, 'name' => 'A']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $old->id,
            'class_id' => $class6->id,
            'section_id' => $section6->id,
            'roll_no' => 9,
            'status' => EnrollmentStatus::Promoted,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $current->id,
            'class_id' => $class7->id,
            'section_id' => $section7->id,
            'roll_no' => 12,
            'status' => EnrollmentStatus::Active,
        ]);

        return $student;
    }

    public function test_staff_view_returns_history_newest_first_without_n_plus_one(): void
    {
        Model::preventLazyLoading();

        $student = $this->withHistory($this->makeStudent());

        $this->withToken($this->tokenForRole('admin'))
            ->getJson("/api/v1/students/{$student->id}/enrollments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.session', '2026')
            ->assertJsonPath('data.0.class', 'Class 7')
            ->assertJsonPath('data.0.section', 'A')
            ->assertJsonPath('data.0.roll_no', 12)
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.1.session', '2025')
            ->assertJsonPath('data.1.class', 'Class 6')
            ->assertJsonPath('data.1.roll_no', 9)
            ->assertJsonPath('data.1.status', 'promoted');

        Model::preventLazyLoading(false);
    }

    public function test_student_sees_own_history(): void
    {
        $student = $this->withHistory($this->makeStudent());
        $token = $student->user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/students/{$student->id}/enrollments")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_unrelated_student_and_parent_get_404(): void
    {
        $student = $this->withHistory($this->makeStudent());

        // Another student probing → 404 (existence hidden).
        $otherStudent = $this->makeStudent();
        $studentToken = $otherStudent->user->createToken('web')->plainTextToken;
        $this->withToken($studentToken)
            ->getJson("/api/v1/students/{$student->id}/enrollments")
            ->assertStatus(404);

        // A parent not linked to this student → 404.
        $unrelatedParent = $this->makeParent();
        $parentToken = $unrelatedParent->user->createToken('web')->plainTextToken;
        $this->withToken($parentToken)
            ->getJson("/api/v1/students/{$student->id}/enrollments")
            ->assertStatus(404);
    }

    public function test_linked_parent_sees_history(): void
    {
        $student = $this->withHistory($this->makeStudent());
        $parent = $this->makeParent();
        $parent->students()->attach($student->id);

        $token = $parent->user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/students/{$student->id}/enrollments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.roll_no', 12);
    }
}
