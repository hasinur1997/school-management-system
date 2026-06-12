<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    /**
     * Create an API token for a user with the given role.
     */
    private function tokenForRole(string $role): string
    {
        $user = User::factory()->create()->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'name' => 'Madani PathShala',
            'code' => 'MP',
            'address' => 'Main Road',
            'phone' => '01712345678',
            'email' => null,
            ...$overrides,
        ];
    }

    public function test_super_admin_can_create_branch_and_duplicate_code_fails_validation(): void
    {
        $token = $this->tokenForRole('super_admin');

        $this->withToken($token)
            ->postJson('/api/v1/branches', $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Branch created')
            ->assertJsonPath('data.name', 'Madani PathShala')
            ->assertJsonPath('data.code', 'MP')
            ->assertJsonPath('data.is_active', true);

        $branch = Branch::where('code', 'MP')->firstOrFail();
        $this->assertModelExists($branch);

        $this->withToken($token)
            ->postJson('/api/v1/branches', $this->validPayload(['name' => 'Duplicate Branch']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->withToken($token)
            ->postJson('/api/v1/branches', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_list_branches_is_paginated_and_filters_by_active_status(): void
    {
        $token = $this->tokenForRole('super_admin');

        Branch::factory()->create(['name' => 'Active One', 'code' => 'A1']);
        Branch::factory()->inactive()->create(['name' => 'Inactive One', 'code' => 'I1']);
        Branch::factory()->create(['name' => 'Active Two', 'code' => 'A2']);

        $this->withToken($token)
            ->getJson('/api/v1/branches?is_active=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_active', true);

        $this->withToken($token)
            ->getJson('/api/v1/branches?search=A2')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.code', 'A2');
    }

    public function test_show_update_and_delete_branch_happy_path(): void
    {
        $token = $this->tokenForRole('super_admin');
        $branch = Branch::factory()->create([
            'name' => 'Original Branch',
            'code' => 'OB',
        ]);

        $this->withToken($token)
            ->getJson("/api/v1/branches/{$branch->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Original Branch')
            ->assertJsonPath('data.code', 'OB');

        $this->withToken($token)
            ->putJson("/api/v1/branches/{$branch->id}", $this->validPayload([
                'name' => 'Updated Branch',
                'code' => 'UB',
                'is_active' => false,
            ]))
            ->assertOk()
            ->assertJsonPath('message', 'Branch updated')
            ->assertJsonPath('data.name', 'Updated Branch')
            ->assertJsonPath('data.code', 'UB')
            ->assertJsonPath('data.is_active', false);

        $this->withToken($token)
            ->deleteJson("/api/v1/branches/{$branch->id}")
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Branch deleted',
                'data' => null,
            ]);

        $this->assertModelMissing($branch);
    }

    public function test_delete_branch_in_use_returns_conflict(): void
    {
        $token = $this->tokenForRole('super_admin');
        $branch = Branch::factory()->create();

        User::factory()->create(['branch_id' => $branch->id]);

        $this->withToken($token)
            ->deleteJson("/api/v1/branches/{$branch->id}")
            ->assertStatus(409)
            ->assertExactJson([
                'success' => false,
                'message' => 'Branch is in use and cannot be deleted',
            ]);

        $this->assertModelExists($branch);
    }

    public function test_teacher_is_denied_branch_crud_access(): void
    {
        $token = $this->tokenForRole('teacher');

        $this->withToken($token)
            ->getJson('/api/v1/branches')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ]);
    }

    public function test_branch_seeder_creates_the_known_branches_idempotently(): void
    {
        $this->seed(BranchSeeder::class);

        $madani = Branch::where('code', 'MP')->firstOrFail();
        $jabedAli = Branch::where('code', 'JA')->firstOrFail();

        $this->assertSame('Madani PathShala', $madani->name);
        $this->assertSame('Jabed Ali', $jabedAli->name);

        $this->seed(BranchSeeder::class);

        $this->assertSame(2, Branch::count());
    }
}
