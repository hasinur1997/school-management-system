<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\CheckinIpWhitelist;
use App\Models\User;
use App\Services\WhitelistService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckinIpWhitelistTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
    }

    private function token(string $role = 'admin', ?Branch $branch = null): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : ($branch ?? $this->branch)->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    public function test_admin_can_create_a_whitelist_entry(): void
    {
        $response = $this->withToken($this->token())
            ->postJson('/api/v1/checkin-ips', [
                'ip_address' => '103.4.5.0/24',
                'label' => 'School WiFi',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.ip_address', '103.4.5.0/24')
            ->assertJsonPath('data.label', 'School WiFi')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.branch_id', $this->branch->id);

        $this->assertDatabaseHas('checkin_ip_whitelists', [
            'branch_id' => $this->branch->id,
            'ip_address' => '103.4.5.0/24',
        ]);
    }

    public function test_admin_can_list_only_their_branch_entries(): void
    {
        CheckinIpWhitelist::factory()->create(['branch_id' => $this->branch->id, 'ip_address' => '10.0.0.1']);
        $other = Branch::factory()->create();
        CheckinIpWhitelist::factory()->create(['branch_id' => $other->id, 'ip_address' => '10.0.0.2']);

        $response = $this->withToken($this->token())->getJson('/api/v1/checkin-ips');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.ip_address', '10.0.0.1');
    }

    public function test_admin_cannot_see_or_touch_another_branch_entry(): void
    {
        $other = Branch::factory()->create();
        $entry = CheckinIpWhitelist::factory()->create(['branch_id' => $other->id, 'ip_address' => '10.0.0.9']);

        $this->withToken($this->token())
            ->putJson("/api/v1/checkin-ips/{$entry->id}", ['is_active' => false])
            ->assertNotFound();

        $this->withToken($this->token())
            ->deleteJson("/api/v1/checkin-ips/{$entry->id}")
            ->assertNotFound();
    }

    public function test_update_can_toggle_is_active_and_edit_fields(): void
    {
        $entry = CheckinIpWhitelist::factory()->create([
            'branch_id' => $this->branch->id,
            'ip_address' => '192.168.1.1',
            'is_active' => true,
        ]);

        $response = $this->withToken($this->token())
            ->putJson("/api/v1/checkin-ips/{$entry->id}", [
                'is_active' => false,
                'label' => 'Renamed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.label', 'Renamed');

        $this->assertDatabaseHas('checkin_ip_whitelists', [
            'id' => $entry->id,
            'is_active' => false,
            'label' => 'Renamed',
        ]);
    }

    public function test_delete_removes_the_entry(): void
    {
        $entry = CheckinIpWhitelist::factory()->create(['branch_id' => $this->branch->id]);

        $this->withToken($this->token())
            ->deleteJson("/api/v1/checkin-ips/{$entry->id}")
            ->assertOk();

        $this->assertDatabaseMissing('checkin_ip_whitelists', ['id' => $entry->id]);
    }

    public function test_invalid_cidr_is_rejected(): void
    {
        $this->withToken($this->token())
            ->postJson('/api/v1/checkin-ips', ['ip_address' => '103.4.5.0/40'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ip_address');

        $this->withToken($this->token())
            ->postJson('/api/v1/checkin-ips', ['ip_address' => 'not-an-ip'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ip_address');
    }

    public function test_duplicate_ip_in_branch_is_rejected(): void
    {
        CheckinIpWhitelist::factory()->create([
            'branch_id' => $this->branch->id,
            'ip_address' => '103.4.5.0/24',
        ]);

        $this->withToken($this->token())
            ->postJson('/api/v1/checkin-ips', ['ip_address' => '103.4.5.0/24'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ip_address');
    }

    public function test_same_ip_allowed_in_different_branches(): void
    {
        $other = Branch::factory()->create();
        CheckinIpWhitelist::factory()->create(['branch_id' => $other->id, 'ip_address' => '103.4.5.0/24']);

        $this->withToken($this->token())
            ->postJson('/api/v1/checkin-ips', ['ip_address' => '103.4.5.0/24'])
            ->assertCreated();
    }

    public function test_active_whitelist_cache_reflects_writes(): void
    {
        $service = app(WhitelistService::class);
        $branch = $this->branch->fresh();

        // Prime the cache: empty.
        $this->assertCount(0, $service->activeFor($branch));

        // Write through the API (must invalidate the cache).
        $this->withToken($this->token())
            ->postJson('/api/v1/checkin-ips', ['ip_address' => '10.10.10.10'])
            ->assertCreated();

        $active = $service->activeFor($branch);
        $this->assertCount(1, $active);
        $this->assertSame('10.10.10.10', $active->first()->ip_address);

        // Toggling inactive must drop it from the cached active set.
        $entry = $active->first();
        $this->withToken($this->token())
            ->putJson("/api/v1/checkin-ips/{$entry->id}", ['is_active' => false])
            ->assertOk();

        $this->assertCount(0, $service->activeFor($branch));
    }

    public function test_requires_manage_permission(): void
    {
        $this->withToken($this->token('teacher'))
            ->getJson('/api/v1/checkin-ips')
            ->assertForbidden();
    }
}
