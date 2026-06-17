<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\IdCardBatchStatus;
use App\Enums\StudentStatus;
use App\Jobs\BuildIdCardBatch;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\IdCardBatch;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdCardBatchTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['code' => 'MP']);
        $this->session = AcademicSession::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        $this->section = Section::factory()->create(['class_id' => $this->class->id]);
    }

    private function staffToken(?Branch $branch = null): string
    {
        $user = User::factory()->create(['branch_id' => ($branch ?? $this->branch)->id])->assignRole('admin');

        return $user->createToken('web')->plainTextToken;
    }

    private function seedStudent(
        StudentStatus $status = StudentStatus::Active,
        EnrollmentStatus $enrollmentStatus = EnrollmentStatus::Active,
        ?Branch $branch = null,
    ): Student {
        $branch ??= $this->branch;

        $student = Student::factory()->create([
            'branch_id' => $branch->id,
            'status' => $status,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'status' => $enrollmentStatus,
        ]);

        return $student;
    }

    /** Count the page objects in a dompdf-produced PDF (one card per page). */
    private function pdfPageCount(string $pdf): int
    {
        return preg_match_all('~/Type\s*/Page(?![s/])~', $pdf);
    }

    public function test_batch_request_queues_a_job_and_returns_202(): void
    {
        Queue::fake();
        $this->seedStudent();

        $response = $this->withToken($this->staffToken())->postJson('/api/v1/id-cards/batch', [
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonStructure(['data' => ['batch_id', 'status']]);

        Queue::assertPushed(BuildIdCardBatch::class, 1);
    }

    public function test_empty_cohort_is_rejected(): void
    {
        $response = $this->withToken($this->staffToken())->postJson('/api/v1/id-cards/batch', [
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ]);

        $response->assertStatus(422)->assertJson(['message' => 'No eligible students']);
    }

    public function test_running_the_job_produces_a_stored_pdf(): void
    {
        Storage::fake('local');
        $this->seedStudent();
        $this->seedStudent();

        // Sync queue → the job runs during dispatch.
        $this->withToken($this->staffToken())->postJson('/api/v1/id-cards/batch', [
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ])->assertStatus(202);

        $batch = IdCardBatch::query()->sole();

        $this->assertSame(IdCardBatchStatus::Done, $batch->status);
        $this->assertNotNull($batch->file_path);
        Storage::disk('local')->assertExists($batch->file_path);
    }

    public function test_poll_reports_processing_then_done_with_download_url(): void
    {
        $processing = IdCardBatch::factory()->create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/id-cards/batch/{$processing->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'processing')
            ->assertJsonMissingPath('data.url');

        $done = IdCardBatch::factory()->done('idcards/batches/done.pdf')->create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/id-cards/batch/{$done->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.url', "/api/v1/id-cards/batch/{$done->id}/download");
    }

    public function test_download_before_done_is_a_conflict(): void
    {
        $batch = IdCardBatch::factory()->create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/id-cards/batch/{$batch->id}/download")
            ->assertStatus(409);
    }

    public function test_finished_batch_downloads_the_pdf(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('idcards/batches/done.pdf', '%PDF-1.4 fake');

        $batch = IdCardBatch::factory()->done('idcards/batches/done.pdf')->create([
            'branch_id' => $this->branch->id,
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ]);

        $response = $this->withToken($this->staffToken())
            ->get("/api/v1/id-cards/batch/{$batch->id}/download");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_foreign_batch_is_not_found(): void
    {
        $otherBranch = Branch::factory()->create(['code' => 'JA']);

        $batch = IdCardBatch::factory()->create([
            'branch_id' => $otherBranch->id,
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ]);

        $this->withToken($this->staffToken())
            ->getJson("/api/v1/id-cards/batch/{$batch->id}")
            ->assertNotFound();
    }

    public function test_tc_student_is_excluded_from_the_merged_set(): void
    {
        Storage::fake('local');
        $this->seedStudent();
        $this->seedStudent();
        $this->seedStudent(StudentStatus::Tc, EnrollmentStatus::Tc);

        $this->withToken($this->staffToken())->postJson('/api/v1/id-cards/batch', [
            'class_id' => $this->class->id,
            'session_id' => $this->session->id,
        ])->assertStatus(202);

        $batch = IdCardBatch::query()->sole();
        $pdf = Storage::disk('local')->get($batch->file_path);

        // Two active students → two card pages; the TC student is absent.
        $this->assertSame(2, $this->pdfPageCount($pdf));
    }
}
