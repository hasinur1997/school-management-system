<?php

namespace Tests\Feature\Api\V1;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherAssignmentCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $this->section = Section::factory()->create(['class_id' => $this->class->id]);
        $this->subject = Subject::factory()->create(['class_id' => $this->class->id]);
    }

    /**
     * Create an API token for a user with the given role. Super admins get no
     * branch (per schema); everyone else defaults to the test branch.
     */
    private function tokenForRole(string $role, ?Branch $branch = null): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : ($branch ?? $this->branch)->id])
            ->assignRole($role);

        Sanctum::actingAs($user);

        return $user->createToken('web')->plainTextToken;
    }

    public function test_create_assignment_happy_path_with_nested_names(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teacher-assignments', [
                'teacher_id' => 4,
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'subject_id' => $this->subject->id,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Teacher assignment created')
            ->assertJsonPath('data.teacher_id', 4)
            ->assertJsonPath('data.class_id', $this->class->id)
            ->assertJsonPath('data.section_id', $this->section->id)
            ->assertJsonPath('data.subject_id', $this->subject->id)
            ->assertJsonPath('data.class.name', $this->class->name)
            ->assertJsonPath('data.section.name', $this->section->name)
            ->assertJsonPath('data.subject.name', $this->subject->name)
            ->assertJsonPath('data.session.name', $this->session->name);

        $this->assertDatabaseHas('teacher_assignments', [
            'teacher_id' => 4,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);
    }

    public function test_create_class_duty_with_null_subject(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/teacher-assignments', [
                'teacher_id' => 4,
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.subject_id', null)
            ->assertJsonPath('data.section.name', $this->section->name)
            ->assertJsonPath('data.subject', null);
    }

    public function test_duplicate_tuple_is_rejected(): void
    {
        $token = $this->tokenForRole('admin');

        $payload = [
            'teacher_id' => 4,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ];

        $this->withToken($token)->postJson('/api/v1/teacher-assignments', $payload)->assertCreated();
        $this->withToken($token)->postJson('/api/v1/teacher-assignments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['teacher_id']);

        $this->assertSame(1, TeacherAssignment::count());
    }

    public function test_duplicate_class_duty_with_null_subject_is_rejected(): void
    {
        $token = $this->tokenForRole('admin');

        $payload = [
            'teacher_id' => 4,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
        ];

        $this->withToken($token)->postJson('/api/v1/teacher-assignments', $payload)->assertCreated();
        // SQL leaves NULL subject_id out of the unique index — the Form Request
        // must catch this duplicate.
        $this->withToken($token)->postJson('/api/v1/teacher-assignments', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['teacher_id']);

        $this->assertSame(1, TeacherAssignment::count());
    }

    public function test_section_not_in_class_is_rejected(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $foreignSection = Section::factory()->create(['class_id' => $otherClass->id]);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/teacher-assignments', [
                'teacher_id' => 4,
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $foreignSection->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['section_id']);

        $this->assertSame(0, TeacherAssignment::count());
    }

    public function test_subject_not_in_class_is_rejected(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $foreignSubject = Subject::factory()->create(['class_id' => $otherClass->id]);

        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/teacher-assignments', [
                'teacher_id' => 4,
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'subject_id' => $foreignSubject->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id']);
    }

    public function test_missing_ids_are_rejected(): void
    {
        $this->withToken($this->tokenForRole('admin'))
            ->postJson('/api/v1/teacher-assignments', [
                'teacher_id' => 4,
                'session_id' => 9999,
                'class_id' => 9999,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['session_id', 'class_id']);
    }

    public function test_filters_by_teacher_and_class(): void
    {
        $token = $this->tokenForRole('admin');

        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        TeacherAssignment::factory()->create([
            'teacher_id' => 4, 'session_id' => $this->session->id, 'class_id' => $this->class->id,
        ]);
        TeacherAssignment::factory()->create([
            'teacher_id' => 4, 'session_id' => $this->session->id, 'class_id' => $otherClass->id,
        ]);
        TeacherAssignment::factory()->create([
            'teacher_id' => 7, 'session_id' => $this->session->id, 'class_id' => $this->class->id,
        ]);

        // Filter by teacher.
        $this->withToken($token)
            ->getJson('/api/v1/teacher-assignments?teacher_id=4')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);

        // Filter by class.
        $this->withToken($token)
            ->getJson("/api/v1/teacher-assignments?class_id={$this->class->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // Combined filters.
        $this->withToken($token)
            ->getJson("/api/v1/teacher-assignments?teacher_id=4&class_id={$this->class->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_eager_loads_relations_under_strict_mode(): void
    {
        $token = $this->tokenForRole('admin');

        TeacherAssignment::factory()->count(3)->create([
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        Model::shouldBeStrict();

        try {
            $this->withToken($token)
                ->getJson('/api/v1/teacher-assignments')
                ->assertOk()
                ->assertJsonCount(3, 'data')
                ->assertJsonPath('data.0.class.name', $this->class->name)
                ->assertJsonPath('data.0.subject.name', $this->subject->name);
        } finally {
            Model::shouldBeStrict(false);
        }
    }

    public function test_update_and_delete_assignment(): void
    {
        $token = $this->tokenForRole('admin');
        $assignment = TeacherAssignment::factory()->create([
            'teacher_id' => 4,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'subject_id' => $this->subject->id,
        ]);

        $this->withToken($token)
            ->putJson("/api/v1/teacher-assignments/{$assignment->id}", [
                'teacher_id' => 4,
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
                'section_id' => $this->section->id,
                'subject_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Teacher assignment updated')
            ->assertJsonPath('data.subject_id', null);

        $this->withToken($token)
            ->deleteJson("/api/v1/teacher-assignments/{$assignment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Teacher assignment deleted');

        $this->assertSame(0, TeacherAssignment::count());
    }

    public function test_assignments_are_branch_scoped(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $foreign = TeacherAssignment::factory()->create([
            'session_id' => $this->session->id,
            'class_id' => $otherClass->id,
        ]);

        $token = $this->tokenForRole('admin');

        // Out-of-branch row is invisible in the list and 404 on binding.
        $this->withToken($token)
            ->getJson('/api/v1/teacher-assignments')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withToken($token)
            ->getJson("/api/v1/teacher-assignments/{$foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');

        // Cannot assign to another branch's class.
        $this->withToken($token)
            ->postJson('/api/v1/teacher-assignments', [
                'teacher_id' => 4,
                'session_id' => $this->session->id,
                'class_id' => $otherClass->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['class_id']);

        // Super admins see across branches.
        $this->withToken($this->tokenForRole('super_admin'))
            ->getJson("/api/v1/teacher-assignments/{$foreign->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $foreign->id);
    }

    public function test_requires_teacher_update_permission(): void
    {
        $this->withToken($this->tokenForRole('teacher'))
            ->getJson('/api/v1/teacher-assignments')
            ->assertForbidden();

        $this->withToken($this->tokenForRole('teacher'))
            ->postJson('/api/v1/teacher-assignments', [
                'teacher_id' => 4,
                'session_id' => $this->session->id,
                'class_id' => $this->class->id,
            ])
            ->assertForbidden();
    }
}
