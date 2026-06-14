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
     * A well-formed create payload.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::FirstSemester->value,
            'name' => 'First Semester 2026',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-10',
            ...$overrides,
        ];
    }

    public function test_admin_creates_exam(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'first_semester')
            ->assertJsonPath('data.name', 'First Semester 2026')
            ->assertJsonPath('data.status', 'upcoming');

        $this->assertDatabaseHas('exams', [
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => 'first_semester',
            'branch_id' => $this->branch->id,
            'status' => 'upcoming',
        ]);
    }

    public function test_duplicate_tuple_is_rejected(): void
    {
        Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::FirstSemester,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/exams', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This exam already exists for the class')
            ->assertJsonValidationErrors('type');
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
        $exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::FirstSemester,
            'name' => 'Old Name',
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->id}", ['name' => 'New Name']);

        $response->assertOk()->assertJsonPath('data.name', 'New Name');
        $this->assertDatabaseHas('exams', ['id' => $exam->id, 'name' => 'New Name']);
    }

    public function test_status_can_advance_forward(): void
    {
        $exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'status' => ExamStatus::Upcoming,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->id}", ['status' => 'ongoing']);

        $response->assertOk()->assertJsonPath('data.status', 'ongoing');
    }

    public function test_changing_type_is_rejected(): void
    {
        $exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::FirstSemester,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->id}", ['type' => 'final']);

        $response->assertStatus(422)->assertJsonValidationErrors('type');
    }

    public function test_changing_session_or_class_is_rejected(): void
    {
        $exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->id}", [
                'session_id' => $this->session->id + 1,
                'class_id' => $this->class->id + 1,
            ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['session_id', 'class_id']);
    }

    public function test_editing_published_exam_returns_409(): void
    {
        $exam = Exam::factory()->published()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->id}", ['name' => 'Anything']);

        $response->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Published exams cannot be modified',
            ]);
    }

    public function test_status_regression_is_rejected(): void
    {
        $exam = Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'status' => ExamStatus::Completed,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/exams/{$exam->id}", ['status' => 'ongoing']);

        $response->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_filters_narrow_the_list(): void
    {
        $otherSession = AcademicSession::factory()->create();

        Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'type' => ExamType::FirstSemester,
            'status' => ExamStatus::Upcoming,
        ]);
        Exam::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $otherSession->id,
            'class_id' => $this->class->id,
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

    public function test_out_of_branch_exam_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create();
        $otherClass = SchoolClass::factory()->create(['branch_id' => $otherBranch->id]);
        $exam = Exam::factory()->create([
            'branch_id' => $otherBranch->id,
            'session_id' => $this->session->id,
            'class_id' => $otherClass->id,
        ]);

        $this->withToken($this->adminToken)
            ->getJson("/api/v1/exams/{$exam->id}")
            ->assertNotFound();
    }

    public function test_write_requires_exam_manage_permission(): void
    {
        $teacher = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $teacher->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/exams', $this->validPayload())
            ->assertForbidden();
    }
}
