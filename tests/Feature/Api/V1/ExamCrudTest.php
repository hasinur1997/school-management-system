<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        $admin = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $this->adminToken = $admin->createToken('web')->plainTextToken;
    }

    /**
     * A well-formed create payload targeting a single class.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'session_id' => $this->session->id,
            'class_ids' => [$this->class->id],
            'type' => ExamType::FirstSemester->value,
            'name' => 'First Semester 2026',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-10',
            ...$overrides,
        ];
    }

    public function test_admin_creates_exam_for_classes(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', $this->validPayload([
                'class_ids' => [$this->class->id, $otherClass->id],
            ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'first_semester')
            ->assertJsonPath('data.name', 'First Semester 2026')
            ->assertJsonPath('data.all_classes', false)
            ->assertJsonPath('data.status', 'upcoming')
            ->assertJsonCount(2, 'data.classes');

        $examId = Exam::query()->where('session_id', $this->session->id)->value('id');

        $this->assertDatabaseHas('exams', [
            'session_id' => $this->session->id,
            'type' => 'first_semester',
            'branch_id' => $this->branch->id,
            'all_classes' => false,
            'status' => 'upcoming',
        ]);
        $this->assertDatabaseHas('exam_class', ['exam_id' => $examId, 'class_id' => $this->class->id]);
        $this->assertDatabaseHas('exam_class', ['exam_id' => $examId, 'class_id' => $otherClass->id]);
    }

    public function test_admin_creates_all_classes_exam(): void
    {
        SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', [
                'session_id' => $this->session->id,
                'all_classes' => true,
                'type' => ExamType::Final->value,
                'name' => 'Final 2026',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.all_classes', true)
            ->assertJsonCount(0, 'data.classes');

        $this->assertDatabaseHas('exams', [
            'session_id' => $this->session->id,
            'type' => 'final',
            'all_classes' => true,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_class_ids_required_when_not_all_classes(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', $this->validPayload(['class_ids' => []]));

        $response->assertStatus(422)->assertJsonValidationErrors('class_ids');
    }

    public function test_overlapping_class_is_rejected(): void
    {
        Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::FirstSemester,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', $this->validPayload());

        $response->assertStatus(422)->assertJsonValidationErrors('class_ids');
    }

    public function test_invalid_type_is_rejected(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', $this->validPayload(['type' => 'midterm']));

        $response->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_inverted_dates_are_rejected(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', $this->validPayload([
                'start_date' => '2026-04-10',
                'end_date' => '2026-04-01',
            ]));

        $response->assertStatus(422)->assertJsonValidationErrors('end_date');
    }

    public function test_update_name_succeeds(): void
    {
        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::FirstSemester,
            'name' => 'Old Name',
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->public_id}", ['name' => 'New Name']);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'name' => 'New Name']);
    }

    public function test_status_can_advance_forward(): void
    {
        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'status' => ExamStatus::Upcoming,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->public_id}", ['status' => 'ongoing']);

        $response->assertOk()->assertJsonPath('data.status', 'ongoing');
    }

    public function test_changing_type_is_rejected(): void
    {
        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::FirstSemester,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->public_id}", ['type' => 'final']);

        $response->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_changing_session_or_classes_is_rejected(): void
    {
        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->public_id}", [
                'session_id' => $this->session->id + 1,
                'class_ids' => [$this->class->id],
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['session_id', 'class_ids']);
    }

    public function test_published_exam_name_is_editable(): void
    {
        $exam = Exam::factory()->forClass($this->class)->published()->create([
            'session_id' => $this->session->id,
            'name' => 'Old Name',
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->public_id}", ['name' => 'New Name']);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'name' => 'New Name', 'status' => 'published']);
    }

    public function test_status_regression_is_rejected(): void
    {
        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'status' => ExamStatus::Completed,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->public_id}", ['status' => 'ongoing']);

        $response->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_filters_narrow_the_list(): void
    {
        $otherSession = AcademicSession::factory()->create();

        Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::FirstSemester,
            'status' => ExamStatus::Upcoming,
        ]);
        Exam::factory()->forClass($this->class)->create([
            'session_id' => $otherSession->id,
            'type' => ExamType::Final,
            'status' => ExamStatus::Completed,
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/v1/exams?session_id={$this->session->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'first_semester');

        $this->withToken($this->adminToken)
            ->getJson('/api/v1/exams?status=completed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'final');
    }

    public function test_class_filter_matches_covered_and_all_classes_exams(): void
    {
        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        // Covers $this->class explicitly.
        Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::FirstSemester,
        ]);
        // Covers every class (all_classes).
        Exam::factory()->allClasses()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'type' => ExamType::SecondSemester,
        ]);
        // Covers only $otherClass — excluded from the $this->class filter.
        Exam::factory()->forClass($otherClass)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::Final,
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/v1/exams?class_id={$this->class->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_out_of_branch_exam_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $exam = Exam::factory()->forClass($otherClass)->create([
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/v1/exams/{$exam->public_id}")
            ->assertNotFound();
    }

    public function test_admin_deletes_exam_and_its_pivot(): void
    {
        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/exams/{$exam->public_id}")
            ->assertOk()
            ->assertJsonPath('message', 'Exam deleted');

        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
        $this->assertDatabaseMissing('exam_class', ['exam_id' => $exam->id]);
    }

    public function test_out_of_branch_exam_delete_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $exam = Exam::factory()->forClass($otherClass)->create([
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/exams/{$exam->public_id}")
            ->assertNotFound();

        $this->assertDatabaseHas('exams', ['id' => $exam->id]);
    }

    public function test_bulk_delete_removes_in_branch_exams_only(): void
    {
        $a = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::FirstSemester,
        ]);
        $b = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
            'type' => ExamType::SecondSemester,
        ]);
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $foreign = Exam::factory()->forClass($otherClass)->create([
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams/bulk-delete', [
                'ids' => [$a->public_id, $b->public_id, $foreign->public_id],
            ])
            ->assertOk()
            ->assertJsonPath('data.deleted', 2);

        $this->assertDatabaseMissing('exams', ['id' => $a->id]);
        $this->assertDatabaseMissing('exams', ['id' => $b->id]);
        // The out-of-branch exam is untouched.
        $this->assertDatabaseHas('exams', ['id' => $foreign->id]);
    }

    public function test_bulk_delete_requires_ids(): void
    {
        $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams/bulk-delete', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }

    public function test_write_requires_exam_manage_permission(): void
    {
        $teacher = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $teacher->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/exams', $this->validPayload())
            ->assertForbidden();

        $exam = Exam::factory()->forClass($this->class)->create([
            'session_id' => $this->session->id,
        ]);
        $this->withToken($token)
            ->deleteJson("/api/v1/exams/{$exam->public_id}")
            ->assertForbidden();
    }
}
