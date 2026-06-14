<?php

namespace Tests\Feature\Api\V1;

use App\Models\Asset;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $accountant;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->accountant = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('accountant');
        $this->token = $this->accountant->createToken('web')->plainTextToken;
    }

    public function test_accountant_creates_asset_defaulting_to_in_use(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/assets', [
            'name' => 'Projector',
            'value' => '45000.00',
            'description' => 'Epson, Room 3',
            'purchase_date' => '2026-02-01',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Projector')
            ->assertJsonPath('data.value', '45000.00')
            ->assertJsonPath('data.description', 'Epson, Room 3')
            ->assertJsonPath('data.purchase_date', '2026-02-01')
            ->assertJsonPath('data.status', 'in_use');

        $this->assertDatabaseHas('assets', [
            'branch_id' => $this->branch->id,
            'name' => 'Projector',
            'value' => '45000.00',
            'status' => 'in_use',
            'created_by' => $this->accountant->id,
        ]);
    }

    public function test_negative_value_is_rejected(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/assets', [
            'name' => 'Bad',
            'value' => '-100.00',
        ])->assertStatus(422)->assertJsonValidationErrors('value');
    }

    public function test_invalid_status_is_rejected(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/assets', [
            'name' => 'Bad status',
            'value' => '100.00',
            'status' => 'sold',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_accountant_updates_asset_status(): void
    {
        $asset = Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'name' => 'Laptop',
            'value' => '60000.00',
            'status' => 'in_use',
        ]);

        $this->withToken($this->token)->putJson("/api/v1/assets/{$asset->id}", [
            'name' => 'Laptop',
            'value' => '60000.00',
            'status' => 'damaged',
        ])->assertOk()->assertJsonPath('data.status', 'damaged');

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'damaged']);
    }

    public function test_accountant_deletes_asset(): void
    {
        $asset = Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/assets/{$asset->id}")
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_summary_excludes_disposed_from_total_value(): void
    {
        // in_use: 2 × 180000 = 360000
        Asset::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'value' => '180000.00',
            'status' => 'in_use',
        ]);
        // damaged: 2 × 12500 = 25000
        Asset::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'value' => '12500.00',
            'status' => 'damaged',
        ]);
        // disposed: excluded from total_value but counted
        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'value' => '99999.00',
            'status' => 'disposed',
        ]);

        $this->withToken($this->token)->getJson('/api/v1/assets/summary')
            ->assertOk()
            ->assertJsonPath('data.total_value', '385000.00')
            ->assertJsonPath('data.count', 5)
            ->assertJsonPath('data.by_status.in_use.count', 2)
            ->assertJsonPath('data.by_status.in_use.value', '360000.00')
            ->assertJsonPath('data.by_status.damaged.count', 2)
            ->assertJsonPath('data.by_status.damaged.value', '25000.00')
            ->assertJsonPath('data.by_status.disposed.count', 1)
            ->assertJsonPath('data.by_status.disposed.value', '99999.00');
    }

    public function test_summary_handles_empty_register(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/assets/summary')
            ->assertOk()
            ->assertJsonPath('data.total_value', '0.00')
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.by_status.in_use.count', 0)
            ->assertJsonPath('data.by_status.in_use.value', '0.00')
            ->assertJsonPath('data.by_status.disposed.count', 0);
    }

    public function test_filter_by_status(): void
    {
        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'status' => 'in_use',
        ]);
        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'status' => 'damaged',
        ]);

        $this->withToken($this->token)->getJson('/api/v1/assets?status=damaged')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'damaged');
    }

    public function test_search_filter(): void
    {
        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'name' => 'Office Projector',
        ]);
        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'name' => 'Whiteboard',
        ]);

        $this->withToken($this->token)->getJson('/api/v1/assets?search=Project')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Office Projector');
    }

    public function test_sort_by_value(): void
    {
        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'value' => '100.00',
        ]);
        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'value' => '900.00',
        ]);

        $this->withToken($this->token)->getJson('/api/v1/assets?sort=value&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.value', '100.00')
            ->assertJsonPath('data.1.value', '900.00');
    }

    public function test_assets_are_branch_isolated(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreign = Asset::factory()->create([
            'branch_id' => $otherBranch->id,
            'created_by' => User::factory()->create(['branch_id' => $otherBranch->id])->id,
        ]);

        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->getJson('/api/v1/assets')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->deleteJson("/api/v1/assets/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_summary_is_branch_isolated(): void
    {
        $otherBranch = Branch::factory()->create();
        Asset::factory()->create([
            'branch_id' => $otherBranch->id,
            'created_by' => User::factory()->create(['branch_id' => $otherBranch->id])->id,
            'value' => '50000.00',
            'status' => 'in_use',
        ]);

        Asset::factory()->create([
            'branch_id' => $this->branch->id,
            'created_by' => $this->accountant->id,
            'value' => '10000.00',
            'status' => 'in_use',
        ]);

        $this->withToken($this->token)->getJson('/api/v1/assets/summary')
            ->assertOk()
            ->assertJsonPath('data.total_value', '10000.00')
            ->assertJsonPath('data.count', 1);
    }

    public function test_requires_asset_manage_permission(): void
    {
        $outsider = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $outsider->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/assets')->assertForbidden();
    }
}
