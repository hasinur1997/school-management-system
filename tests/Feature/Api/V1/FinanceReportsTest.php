<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceReportsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $accountant;

    private string $token;

    private Category $tuition;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-17');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create(['name' => 'Madani PathShala']);
        $this->accountant = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('accountant');
        $this->token = $this->accountant->createToken('web')->plainTextToken;

        $this->tuition = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Tuition Fee',
            'type' => 'income',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function income(string $amount, string $date, ?int $categoryId = null, ?int $paymentId = null): Income
    {
        return Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $categoryId,
            'payment_id' => $paymentId,
            'created_by' => $this->accountant->id,
            'amount' => $amount,
            'date' => $date,
        ]);
    }

    private function expense(string $amount, string $date, ?int $categoryId = null): Expense
    {
        return Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $categoryId,
            'created_by' => $this->accountant->id,
            'amount' => $amount,
            'date' => $date,
        ]);
    }

    public function test_income_report_totals_and_category_breakdown_include_system_fee_income(): void
    {
        // Categorised income.
        $this->income('120000.00', '2026-06-01', $this->tuition->id);
        // System-generated fee income (payment_id set, no category) → Uncategorized.
        $payment = Payment::factory()->paid()->create(['branch_id' => $this->branch->id]);
        $this->income('32000.00', '2026-06-10', null, $payment->id);
        // Outside the monthly window — excluded.
        $this->income('99999.00', '2026-05-15', $this->tuition->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/income?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.filters.period', 'monthly')
            ->assertJsonPath('data.filters.from', '2026-06-01')
            ->assertJsonPath('data.filters.to', '2026-06-30')
            ->assertJsonPath('data.filters.branch', 'Madani PathShala')
            ->assertJsonPath('data.total', '152000.00');

        $byCategory = collect($response->json('data.by_category'));
        $this->assertSame('120000.00', $byCategory->firstWhere('category', 'Tuition Fee')['amount']);
        $this->assertSame('32000.00', $byCategory->firstWhere('category', 'Uncategorized')['amount']);
    }

    public function test_income_series_is_daily_for_a_month(): void
    {
        $this->income('5000.00', '2026-06-01', $this->tuition->id);
        $this->income('3000.00', '2026-06-01', $this->tuition->id);
        $this->income('7000.00', '2026-06-15', $this->tuition->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/income?period=monthly')
            ->assertOk();

        $series = collect($response->json('data.series'));
        $this->assertSame('8000.00', $series->firstWhere('date', '2026-06-01')['amount']);
        $this->assertSame('7000.00', $series->firstWhere('date', '2026-06-15')['amount']);
    }

    public function test_series_switches_to_monthly_beyond_62_days(): void
    {
        $this->income('1000.00', '2026-01-10', $this->tuition->id);
        $this->income('2000.00', '2026-01-20', $this->tuition->id);
        $this->income('5000.00', '2026-03-05', $this->tuition->id);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/income?period=custom&from=2026-01-01&to=2026-06-30')
            ->assertOk();

        $series = collect($response->json('data.series'));
        $this->assertSame('3000.00', $series->firstWhere('date', '2026-01-01')['amount']);
        $this->assertSame('5000.00', $series->firstWhere('date', '2026-03-01')['amount']);
    }

    public function test_report_aggregation_uses_sql_group_by(): void
    {
        $this->income('5000.00', '2026-06-01', $this->tuition->id);

        DB::enableQueryLog();

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/income?period=monthly')
            ->assertOk();

        $queries = collect(DB::getQueryLog())->pluck('query')->implode(' | ');
        DB::disableQueryLog();

        $this->assertStringContainsStringIgnoringCase('group by', $queries);
        $this->assertStringContainsStringIgnoringCase('sum(', $queries);
    }

    public function test_expense_report_shares_income_shape(): void
    {
        $salary = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Salary',
            'type' => 'expense',
        ]);
        $this->expense('40000.00', '2026-06-05', $salary->id);
        $this->expense('10000.00', '2026-06-06', null);

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/expense?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.total', '50000.00')
            ->assertJsonStructure(['data' => ['filters', 'total', 'by_category', 'series']]);
    }

    public function test_profit_loss_net_can_be_negative(): void
    {
        $this->income('10000.00', '2026-06-01', $this->tuition->id);
        $this->expense('13200.00', '2026-06-02');

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/profit-loss?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.income_total', '10000.00')
            ->assertJsonPath('data.expense_total', '13200.00')
            ->assertJsonPath('data.net', '-3200.00');
    }

    public function test_profit_loss_combined_series_pairs_income_and_expense(): void
    {
        $this->income('5000.00', '2026-06-01', $this->tuition->id);
        $this->expense('2000.00', '2026-06-01');
        $this->expense('800.00', '2026-06-03');

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/reports/profit-loss?period=monthly')
            ->assertOk();

        $series = collect($response->json('data.series'));
        $day1 = $series->firstWhere('date', '2026-06-01');
        $this->assertSame('5000.00', $day1['income']);
        $this->assertSame('2000.00', $day1['expense']);

        $day3 = $series->firstWhere('date', '2026-06-03');
        $this->assertSame('0.00', $day3['income']);
        $this->assertSame('800.00', $day3['expense']);
    }

    public function test_super_admin_consolidated_view_adds_by_branch(): void
    {
        $other = Branch::factory()->create(['name' => 'Second Branch']);

        // Income in each branch (created unscoped — super admin context not yet active).
        $this->income('10000.00', '2026-06-01', $this->tuition->id);
        Income::factory()->create([
            'branch_id' => $other->id,
            'category_id' => null,
            'created_by' => User::factory()->create(['branch_id' => $other->id])->id,
            'amount' => '15000.00',
            'date' => '2026-06-02',
        ]);

        $superAdmin = User::factory()->create(['branch_id' => null])->assignRole('super_admin');
        $token = $superAdmin->createToken('web')->plainTextToken;

        $response = $this->withToken($token)
            ->getJson('/api/v1/reports/income?period=monthly&branch_id=all')
            ->assertOk()
            ->assertJsonPath('data.total', '25000.00')
            ->assertJsonPath('data.filters.branch', 'All Branches');

        $byBranch = collect($response->json('data.by_branch'));
        $this->assertSame('10000.00', $byBranch->firstWhere('branch', 'Madani PathShala')['amount']);
        $this->assertSame('15000.00', $byBranch->firstWhere('branch', 'Second Branch')['amount']);
    }

    public function test_super_admin_can_scope_to_single_branch(): void
    {
        $other = Branch::factory()->create(['name' => 'Second Branch']);
        $this->income('10000.00', '2026-06-01', $this->tuition->id);
        Income::factory()->create([
            'branch_id' => $other->id,
            'category_id' => null,
            'created_by' => User::factory()->create(['branch_id' => $other->id])->id,
            'amount' => '15000.00',
            'date' => '2026-06-02',
        ]);

        $superAdmin = User::factory()->create(['branch_id' => null])->assignRole('super_admin');
        $token = $superAdmin->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/reports/income?period=monthly&branch_id={$other->id}")
            ->assertOk()
            ->assertJsonPath('data.total', '15000.00')
            ->assertJsonPath('data.filters.branch', 'Second Branch')
            ->assertJsonMissingPath('data.by_branch');
    }

    public function test_branch_scoped_report_excludes_other_branches(): void
    {
        $other = Branch::factory()->create();
        $this->income('10000.00', '2026-06-01', $this->tuition->id);
        Income::factory()->create([
            'branch_id' => $other->id,
            'category_id' => null,
            'created_by' => User::factory()->create(['branch_id' => $other->id])->id,
            'amount' => '15000.00',
            'date' => '2026-06-02',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/reports/income?period=monthly')
            ->assertOk()
            ->assertJsonPath('data.total', '10000.00')
            ->assertJsonMissingPath('data.by_branch');
    }

    public function test_custom_period_requires_from_and_to(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/reports/income?period=custom')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_from_after_to_is_rejected(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/reports/income?period=custom&from=2026-06-30&to=2026-06-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('to');
    }

    public function test_teacher_without_report_view_is_forbidden(): void
    {
        $teacher = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $teacher->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/reports/income?period=monthly')
            ->assertForbidden();
    }
}
