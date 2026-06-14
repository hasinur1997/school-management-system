<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\GradingScale;
use App\Models\User;
use App\Services\GradeResolver;
use Database\Seeders\GradingScaleSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingScaleTest extends TestCase
{
    use RefreshDatabase;

    private string $superToken;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(GradingScaleSeeder::class);

        $branch = Branch::factory()->create();

        $super = User::factory()->create(['branch_id' => null])->assignRole('super_admin');
        $this->superToken = $super->createToken('web')->plainTextToken;

        $admin = User::factory()->create(['branch_id' => $branch->id])->assignRole('admin');
        $this->adminToken = $admin->createToken('web')->plainTextToken;
    }

    /**
     * A well-formed replacement scale (highest band first).
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return ['scale' => GradingScaleSeeder::DEFAULT_SCALE];
    }

    public function test_authenticated_user_reads_ordered_scale(): void
    {
        $response = $this->withToken($this->adminToken)->getJson('/api/v1/grading-scales');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.grade', 'A+')
            ->assertJsonPath('data.0.grade_point', '5.00')
            ->assertJsonPath('data.6.grade', 'F')
            ->assertJsonPath('data.6.is_fail', true)
            ->assertJsonCount(7, 'data');
    }

    public function test_get_requires_authentication(): void
    {
        $this->getJson('/api/v1/grading-scales')->assertUnauthorized();
    }

    public function test_put_replaces_scale_and_refreshes_cache(): void
    {
        // Prime the cache via a read.
        app(GradeResolver::class)->all();

        $payload = $this->validPayload();
        $payload['scale'][0]['grade_point'] = 5.00;
        $payload['scale'][0]['min_marks'] = 85; // shrink A+, widen A
        $payload['scale'][1]['max_marks'] = 84;

        $response = $this->withToken($this->superToken)->putJson('/api/v1/grading-scales', $payload);

        $response->assertOk()
            ->assertJsonPath('message', 'Grading scale updated')
            ->assertJsonPath('data.0.min_marks', 85);

        // Cache reflects the write immediately: 84 now resolves to A, not A+.
        $this->assertSame('A', app(GradeResolver::class)->resolve(84)['grade']);
        $this->assertSame('A+', app(GradeResolver::class)->resolve(85)['grade']);
        $this->assertDatabaseHas('grading_scales', ['grade' => 'A+', 'min_marks' => 85]);
    }

    public function test_put_requires_setting_manage_permission(): void
    {
        $this->withToken($this->adminToken)
            ->putJson('/api/v1/grading-scales', $this->validPayload())
            ->assertForbidden();
    }

    public function test_put_rejects_a_gap_in_coverage(): void
    {
        $payload = $this->validPayload();
        // Drop the D band (33–39), leaving a gap between C (40) and F (32).
        unset($payload['scale'][5]);
        $payload['scale'] = array_values($payload['scale']);

        $this->withToken($this->superToken)
            ->putJson('/api/v1/grading-scales', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Scale must cover 0–100 without gaps');

        $this->assertDatabaseHas('grading_scales', ['grade' => 'D']);
    }

    public function test_put_rejects_overlapping_ranges(): void
    {
        $payload = $this->validPayload();
        $payload['scale'][1]['max_marks'] = 85; // A now 70–85, overlaps A+ 80–100

        $this->withToken($this->superToken)
            ->putJson('/api/v1/grading-scales', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Scale ranges must not overlap');
    }

    public function test_put_rejects_multiple_fail_rows(): void
    {
        $payload = $this->validPayload();
        $payload['scale'][5]['is_fail'] = true; // D also marked fail

        $this->withToken($this->superToken)
            ->putJson('/api/v1/grading-scales', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Scale must have exactly one failing grade');
    }

    public function test_put_rejects_zero_fail_rows(): void
    {
        $payload = $this->validPayload();
        $payload['scale'][6]['is_fail'] = false; // no failing band at all

        $this->withToken($this->superToken)
            ->putJson('/api/v1/grading-scales', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Scale must have exactly one failing grade');
    }

    public function test_seeder_loads_exact_bangladesh_scale(): void
    {
        $this->assertSame(7, GradingScale::count());

        $this->assertDatabaseHas('grading_scales', ['grade' => 'A+', 'min_marks' => 80, 'max_marks' => 100, 'grade_point' => 5.00, 'is_fail' => false]);
        $this->assertDatabaseHas('grading_scales', ['grade' => 'F', 'min_marks' => 0, 'max_marks' => 32, 'grade_point' => 0.00, 'is_fail' => true]);
    }
}
