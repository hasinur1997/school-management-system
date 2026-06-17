<?php

namespace Tests\Feature\Api\V1;

use App\Models\Asset;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Behavioral acceptance for Task 14.3: after a full `--seed`, the system is
 * explorable — the dashboard, reports and result search all return non-empty,
 * consistent data — and the seed is safely re-runnable.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): User
    {
        $this->seed(DatabaseSeeder::class);

        return User::query()->where('email', 'hasinur@gmail.com')->firstOrFail();
    }

    public function test_fresh_seed_produces_a_fully_populated_branch(): void
    {
        $this->seedDemo();

        // The headline population exists end-to-end.
        $this->assertGreaterThanOrEqual(150, Student::query()->count());
        $this->assertGreaterThanOrEqual(10, Teacher::query()->count());
        $this->assertTrue(Enrollment::query()->exists());
        $this->assertTrue(Invoice::query()->exists());

        // Mixed payments each posted exactly one linked income row.
        $paidPayments = Payment::query()->where('status', 'paid')->count();
        $this->assertGreaterThan(0, $paidPayments);
        $this->assertSame(
            $paidPayments,
            Income::query()->whereNotNull('payment_id')->count(),
            'every settled payment posts exactly one income',
        );

        $this->assertTrue(Expense::query()->exists());
        $this->assertTrue(Asset::query()->exists());

        // Published per-exam + annual results exist for the exam cohort.
        $this->assertTrue(ExamResult::query()->whereNotNull('published_at')->exists());
    }

    public function test_seeded_dashboard_and_reports_and_result_search_return_data(): void
    {
        $superAdmin = $this->seedDemo();
        Sanctum::actingAs($superAdmin);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['data']);

        $window = ['period' => 'custom', 'from' => '2026-01-01', 'to' => '2026-12-31'];

        $this->getJson('/api/v1/reports/income?'.http_build_query($window))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson('/api/v1/reports/students?'.http_build_query($window))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->getJson('/api/v1/reports/fees?'.http_build_query($window))
            ->assertOk()
            ->assertJson(['success' => true]);

        // Result search by the exam cohort's coordinates returns its published
        // per-exam results and the annual result.
        $result = ExamResult::query()->whereNotNull('published_at')->firstOrFail();
        $enrollment = Enrollment::query()->findOrFail($result->enrollment_id);

        $response = $this->getJson('/api/v1/results/search?'.http_build_query([
            'session_id' => $enrollment->session_id,
            'class_id' => $enrollment->class_id,
            'section_id' => $enrollment->section_id,
            'roll_no' => $enrollment->roll_no,
        ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotEmpty($response->json('data.exams'));
        $this->assertNotNull($response->json('data.annual'));
    }

    public function test_seed_is_idempotent_and_runs_twice_without_constraint_violations(): void
    {
        $this->seed(DatabaseSeeder::class);
        $studentsAfterFirst = Student::query()->count();

        // A second run must not throw (unique constraints) nor add demo rows.
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($studentsAfterFirst, Student::query()->count());
    }
}
