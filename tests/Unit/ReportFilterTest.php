<?php

namespace Tests\Unit;

use App\Enums\ReportPeriod;
use App\Http\Requests\Report\ReportFilterRequest;
use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\User;
use App\Support\ReportFilter;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Redirector;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReportFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    // --- Period range resolution (frozen time) ---------------------------------

    public function test_weekly_resolves_to_current_iso_week_monday_to_sunday(): void
    {
        CarbonImmutable::setTestNow('2026-06-17 10:30:00'); // a Wednesday

        [$start, $end] = $this->filter(ReportPeriod::Weekly)->range();

        $this->assertSame('2026-06-15 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-21 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_monthly_resolves_to_current_calendar_month(): void
    {
        CarbonImmutable::setTestNow('2026-06-17 10:30:00');

        [$start, $end] = $this->filter(ReportPeriod::Monthly)->range();

        $this->assertSame('2026-06-01 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-30 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_yearly_resolves_to_current_academic_session(): void
    {
        CarbonImmutable::setTestNow('2026-06-17 10:30:00');

        AcademicSession::factory()->create([
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
        ]);

        [$start, $end] = $this->filter(ReportPeriod::Yearly)->range();

        $this->assertSame('2025-07-01 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-30 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_yearly_falls_back_to_calendar_year_without_current_session(): void
    {
        CarbonImmutable::setTestNow('2026-06-17 10:30:00');

        [$start, $end] = $this->filter(ReportPeriod::Yearly)->range();

        $this->assertSame('2026-01-01 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-31 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_explicit_dates_override_a_non_custom_period(): void
    {
        CarbonImmutable::setTestNow('2026-06-17 10:30:00');

        $filter = new ReportFilter(
            ReportPeriod::Weekly,
            CarbonImmutable::parse('2026-01-05'),
            CarbonImmutable::parse('2026-01-20'),
            null,
        );

        [$start, $end] = $filter->range();

        $this->assertSame('2026-01-05 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-20 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    // --- Granularity helper ----------------------------------------------------

    public function test_granularity_is_daily_at_62_days_and_monthly_beyond(): void
    {
        $from = CarbonImmutable::parse('2026-01-01');

        $daily = new ReportFilter(ReportPeriod::Custom, $from, $from->addDays(62), null);
        $monthly = new ReportFilter(ReportPeriod::Custom, $from, $from->addDays(63), null);

        $this->assertSame('daily', $daily->granularity());
        $this->assertSame('monthly', $monthly->granularity());
    }

    // --- Custom validation matrix ---------------------------------------------

    public function test_custom_requires_both_from_and_to(): void
    {
        $user = $this->superAdmin();

        try {
            $this->validate(['period' => 'custom'], $user);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('from', $e->errors());
            $this->assertArrayHasKey('to', $e->errors());
        }
    }

    public function test_from_must_not_exceed_to(): void
    {
        $this->expectException(ValidationException::class);

        $this->validate([
            'period' => 'custom',
            'from' => '2026-02-01',
            'to' => '2026-01-01',
        ], $this->superAdmin());
    }

    public function test_custom_with_valid_range_resolves_to_those_dates(): void
    {
        $request = $this->validate([
            'period' => 'custom',
            'from' => '2026-01-01',
            'to' => '2026-01-31',
        ], $this->superAdmin());

        [$start, $end] = $request->toFilter()->range();

        $this->assertSame('2026-01-01 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-01-31 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    // --- Branch resolution -----------------------------------------------------

    public function test_non_super_admin_branch_id_is_ignored_and_forced_to_own(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $branch = Branch::factory()->create();
        $other = Branch::factory()->create();
        $accountant = User::factory()->create(['branch_id' => $branch->id])->assignRole('accountant');

        $request = $this->validate([
            'period' => 'monthly',
            'branch_id' => $other->id,
        ], $accountant);

        $this->assertSame($branch->id, $request->toFilter()->branchId);
    }

    public function test_super_admin_all_resolves_to_null_branch(): void
    {
        $request = $this->validate([
            'period' => 'monthly',
            'branch_id' => 'all',
        ], $this->superAdmin());

        $this->assertNull($request->toFilter()->branchId);
    }

    public function test_super_admin_can_target_a_single_branch(): void
    {
        $branch = Branch::factory()->create();

        $request = $this->validate([
            'period' => 'monthly',
            'branch_id' => $branch->id,
        ], $this->superAdmin());

        $this->assertSame($branch->id, $request->toFilter()->branchId);
    }

    // --- Helpers ---------------------------------------------------------------

    private function filter(ReportPeriod $period): ReportFilter
    {
        return new ReportFilter($period, null, null, null);
    }

    private function superAdmin(): User
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        return User::factory()->create()->assignRole('super_admin');
    }

    /**
     * Run the ReportFilterRequest through its full resolution for a given user,
     * returning the validated request (throws ValidationException on failure).
     *
     * @param  array<string, mixed>  $query
     */
    private function validate(array $query, User $user): ReportFilterRequest
    {
        $request = ReportFilterRequest::create('/api/v1/reports/income', 'GET', $query);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make(Redirector::class));
        $request->setUserResolver(fn () => $user);
        $request->validateResolved();

        return $request;
    }
}
