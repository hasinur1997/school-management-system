<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Task 13.4 — report PDF exports. Each of the seven report types streams a PDF
 * over the shared filter contract (Tasks 13.2/13.3 services), unknown types 404
 * on the route constraint, and report.view guards the surface.
 */
class ReportPdfTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-06-17');

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->token = User::factory()
            ->create(['branch_id' => $this->branch->id])
            ->assignRole('accountant')
            ->createToken('web')->plainTextToken;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function types(): array
    {
        return [
            'income' => ['income'],
            'expense' => ['expense'],
            'profit-loss' => ['profit-loss'],
            'students' => ['students'],
            'teachers' => ['teachers'],
            'assets' => ['assets'],
            'fees' => ['fees'],
        ];
    }

    #[DataProvider('types')]
    public function test_each_report_type_streams_a_pdf(string $type): void
    {
        $response = $this->withToken($this->token)
            ->get("/api/v1/reports/{$type}/pdf?period=monthly");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_unknown_report_type_404s_on_the_route(): void
    {
        $this->withToken($this->token)
            ->get('/api/v1/reports/nonsense/pdf?period=monthly')
            ->assertNotFound();
    }

    public function test_custom_range_drives_the_filename(): void
    {
        $response = $this->withToken($this->token)
            ->get('/api/v1/reports/income/pdf?period=custom&from=2026-01-01&to=2026-03-31');

        $response->assertOk();
        $this->assertStringContainsString(
            'report-income-2026-01-01-2026-03-31.pdf',
            $response->headers->get('content-disposition'),
        );
    }

    public function test_invalid_filter_returns_the_json_envelope(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/reports/income/pdf?period=custom')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_report_view_permission_is_required(): void
    {
        $token = User::factory()
            ->create(['branch_id' => $this->branch->id])
            ->assignRole('teacher')
            ->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/reports/income/pdf?period=monthly')
            ->assertForbidden();
    }
}
