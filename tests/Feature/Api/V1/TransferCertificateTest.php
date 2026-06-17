<?php

namespace Tests\Feature\Api\V1;

use App\Enums\EnrollmentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\AnnualResult;
use App\Models\Branch;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\TransferCertificate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TransferCertificateTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private SchoolClass $nextClass;

    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['code' => 'MP']);
        $this->session = AcademicSession::factory()->current()->create(['name' => '2026']);
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
    }

    private function adminToken(?Branch $branch = null): string
    {
        $user = User::factory()->create(['branch_id' => ($branch ?? $this->branch)->id])->assignRole('admin');

        return $user->createToken('web')->plainTextToken;
    }

    private function seedStudent(int $rollNo = 7, ?Branch $branch = null): Student
    {
        $branch ??= $this->branch;

        $student = Student::factory()->create([
            'branch_id' => $branch->id,
            'name_en' => 'Rahima Khatun',
            'name_bn' => 'রহিমা খাতুন',
            'admission_no' => 'STU-MP-2026-0009',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $rollNo,
            'status' => EnrollmentStatus::Active,
        ]);

        return $student;
    }

    public function test_issuing_creates_a_tc_flips_statuses_and_stores_a_pdf(): void
    {
        $student = $this->seedStudent();

        $response = $this->withToken($this->adminToken())
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Family relocated to Dhaka',
                'issue_date' => '2026-06-11',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.tc_no', 'TC-MP-0001')
            ->assertJsonPath('data.reason', 'Family relocated to Dhaka')
            ->assertJsonPath('data.issue_date', '2026-06-11')
            ->assertJsonPath('data.student.id', $student->id);

        $tcId = $response->json('data.id');
        $this->assertSame("/api/v1/tcs/{$tcId}/pdf", $response->json('data.pdf_url'));

        // Row persisted with the issuing user and branch.
        $this->assertDatabaseHas('transfer_certificates', [
            'id' => $tcId,
            'student_id' => $student->id,
            'tc_no' => 'TC-MP-0001',
            'branch_id' => $this->branch->id,
        ]);

        // Statuses flipped in the same transaction.
        $this->assertSame(StudentStatus::Tc, $student->fresh()->status);
        $this->assertSame(
            EnrollmentStatus::Tc,
            $student->enrollments()->first()->status,
        );

        // The PDF is persisted as the stored legal record.
        $tc = TransferCertificate::find($tcId);
        $media = $tc->getFirstMedia('certificate');
        $this->assertNotNull($media);
        $this->assertTrue(is_file($media->getPath()));
    }

    public function test_issue_rolls_back_when_pdf_rendering_fails(): void
    {
        $student = $this->seedStudent();

        // Force the last step (PDF render) to blow up; the whole issue must roll
        // back — no TC row, statuses untouched.
        Pdf::shouldReceive('loadView')->andThrow(new \RuntimeException('render failed'));

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Family relocated to Dhaka',
                'issue_date' => '2026-06-11',
            ])
            ->assertStatus(500);

        $this->assertDatabaseCount('transfer_certificates', 0);
        $this->assertSame(StudentStatus::Active, $student->fresh()->status);
        $this->assertSame(EnrollmentStatus::Active, $student->enrollments()->first()->status);
    }

    public function test_duplicate_issue_is_a_conflict(): void
    {
        $student = $this->seedStudent();
        $token = $this->adminToken();

        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'First',
                'issue_date' => '2026-06-11',
            ])->assertStatus(201);

        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Second',
                'issue_date' => '2026-06-12',
            ])
            ->assertStatus(409)
            ->assertJson(['message' => 'Transfer certificate already issued']);

        $this->assertDatabaseCount('transfer_certificates', 1);
    }

    public function test_empty_reason_is_rejected(): void
    {
        $student = $this->seedStudent();

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => '',
                'issue_date' => '2026-06-11',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_cross_branch_student_is_not_found(): void
    {
        $other = Branch::factory()->create(['code' => 'JA']);
        $student = $this->seedStudent(branch: $other);

        $this->withToken($this->adminToken())
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Relocating',
                'issue_date' => '2026-06-11',
            ])
            ->assertNotFound();
    }

    public function test_list_and_show_return_issued_tcs(): void
    {
        $student = $this->seedStudent();
        $token = $this->adminToken();

        $tcId = $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Family relocated to Dhaka',
                'issue_date' => '2026-06-11',
            ])->json('data.id');

        $this->withToken($token)
            ->getJson('/api/v1/tcs')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $tcId)
            ->assertJsonPath('data.0.tc_no', 'TC-MP-0001')
            ->assertJsonPath('meta.total', 1);

        $this->withToken($token)
            ->getJson('/api/v1/tcs?search=Rahima')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)
            ->getJson("/api/v1/tcs/{$tcId}")
            ->assertOk()
            ->assertJsonPath('data.tc_no', 'TC-MP-0001')
            ->assertJsonPath('data.student.id', $student->id);
    }

    public function test_pdf_endpoint_downloads_the_stored_certificate(): void
    {
        $student = $this->seedStudent();
        $token = $this->adminToken();

        $tcId = $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Family relocated to Dhaka',
                'issue_date' => '2026-06-11',
            ])->json('data.id');

        $response = $this->withToken($token)->get("/api/v1/tcs/{$tcId}/pdf");

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_pdf_endpoint_500s_when_the_stored_file_is_missing(): void
    {
        $student = $this->seedStudent();
        $token = $this->adminToken();

        $tcId = $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Family relocated to Dhaka',
                'issue_date' => '2026-06-11',
            ])->json('data.id');

        // Wipe the underlying file but keep the media record → the document is
        // unavailable, which is a logged 500 (an issued TC should always have it).
        @unlink(TransferCertificate::find($tcId)->getFirstMedia('certificate')->getPath());

        $this->withToken($token)
            ->getJson("/api/v1/tcs/{$tcId}/pdf")
            ->assertStatus(500);
    }

    public function test_tc_student_is_excluded_from_the_attendance_roster(): void
    {
        $student = $this->seedStudent(rollNo: 1);
        $other = $this->seedStudentNamed('Karim', rollNo: 2);
        $token = $this->adminToken();

        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Relocating',
                'issue_date' => '2026-06-11',
            ])->assertStatus(201);

        $this->withToken($token)
            ->getJson("/api/v1/attendance/sheet?class_id={$this->class->id}&section_id={$this->section->id}&date=2026-06-11")
            ->assertOk()
            ->assertJsonCount(1, 'data.students')
            ->assertJsonPath('data.students.0.name_en', 'Karim');
    }

    public function test_tc_student_is_excluded_from_invoice_generation(): void
    {
        $student = $this->seedStudent(rollNo: 1);
        $other = $this->seedStudentNamed('Karim', rollNo: 2);
        $token = $this->adminToken();

        FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'monthly_fee' => '1500.00',
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Relocating',
                'issue_date' => '2026-06-11',
            ])->assertStatus(201);

        $this->withToken($token)
            ->postJson('/api/v1/invoices/generate', ['month' => 6, 'year' => 2026])
            ->assertOk()
            ->assertJsonPath('data.created', 1);

        // Only the active student was invoiced; the TC student was skipped.
        $this->assertDatabaseHas('invoices', ['student_id' => $other->id]);
        $this->assertDatabaseMissing('invoices', ['student_id' => $student->id]);
    }

    public function test_tc_student_is_excluded_from_promotion_preview(): void
    {
        $student = $this->seedStudent(rollNo: 1);
        $passed = $this->seedStudentNamed('Karim', rollNo: 2);
        $token = $this->adminToken();

        // A published, passed annual result so the preview is meaningful (200).
        AnnualResult::create([
            'enrollment_id' => $passed->enrollments()->first()->id,
            'first_semester_gpa' => '4.00',
            'second_semester_gpa' => '4.00',
            'final_exam_gpa' => '4.00',
            'annual_gpa' => '4.00',
            'grade' => 'A',
            'is_passed' => true,
            'published_at' => now(),
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Relocating',
                'issue_date' => '2026-06-11',
            ])->assertStatus(201);

        $response = $this->withToken($token)
            ->getJson("/api/v1/promotions/preview?session_id={$this->session->id}&class_id={$this->class->id}")
            ->assertOk();

        // The TC student never appears as eligible; it is listed not_eligible/tc.
        $eligibleIds = collect($response->json('data.eligible'))->pluck('student_id');
        $this->assertFalse($eligibleIds->contains($student->id));

        $reasons = collect($response->json('data.not_eligible'))
            ->mapWithKeys(fn (array $row): array => [$row['student_id'] => $row['reason']]);
        $this->assertSame('tc', $reasons[$student->id]);
    }

    public function test_unpaid_invoices_remain_readable_after_a_tc_is_issued(): void
    {
        $student = $this->seedStudent();
        $token = $this->adminToken();

        $invoice = Invoice::factory()->create([
            'branch_id' => $this->branch->id,
            'student_id' => $student->id,
            'enrollment_id' => $student->enrollments()->first()->id,
            'status' => 'unpaid',
        ]);

        $this->withToken($token)
            ->postJson("/api/v1/students/{$student->id}/tc", [
                'reason' => 'Relocating',
                'issue_date' => '2026-06-11',
            ])->assertStatus(201);

        // The past unpaid invoice is still visible — issuing a TC keeps records.
        $this->withToken($token)
            ->getJson("/api/v1/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $invoice->id);
    }

    private function seedStudentNamed(string $nameEn, int $rollNo): Student
    {
        $student = Student::factory()->create([
            'branch_id' => $this->branch->id,
            'name_en' => $nameEn,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'roll_no' => $rollNo,
            'status' => EnrollmentStatus::Active,
        ]);

        return $student;
    }
}
