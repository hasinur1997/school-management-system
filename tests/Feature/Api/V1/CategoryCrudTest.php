<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Income;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCrudTest extends TestCase
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

    public function test_accountant_creates_category(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/categories', ['name' => 'Utilities', 'type' => 'expense']);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Utilities')
            ->assertJsonPath('data.type', 'expense');

        $this->assertDatabaseHas('categories', [
            'branch_id' => $this->branch->id,
            'name' => 'Utilities',
            'type' => 'expense',
        ]);
    }

    public function test_duplicate_tuple_is_rejected(): void
    {
        Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Utilities',
            'type' => 'expense',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/categories', ['name' => 'Utilities', 'type' => 'expense']);

        $response->assertStatus(422)
            ->assertJsonPath('errors.name.0', 'Category already exists');
    }

    public function test_same_name_different_type_is_allowed(): void
    {
        Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Tuition Fee',
            'type' => 'income',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/v1/categories', ['name' => 'Tuition Fee', 'type' => 'expense']);

        $response->assertCreated();
    }

    public function test_invalid_type_is_rejected(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/v1/categories', ['name' => 'Whatever', 'type' => 'liability']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_accountant_updates_category(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Utlities',
            'type' => 'expense',
        ]);

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/categories/{$category->id}", ['name' => 'Utilities', 'type' => 'expense']);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Utilities');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Utilities']);
    }

    public function test_delete_in_use_is_rejected(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Tuition Fee',
            'type' => 'income',
        ]);

        Income::create([
            'branch_id' => $this->branch->id,
            'category_id' => $category->id,
            'title' => 'Manual income',
            'amount' => '500.00',
            'date' => '2026-06-01',
            'created_by' => $this->accountant->id,
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Category is in use');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_unused_category_is_deleted(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Donation',
            'type' => 'income',
        ]);

        $response = $this->withToken($this->token)
            ->deleteJson("/api/v1/categories/{$category->id}");

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_list_filters_by_type(): void
    {
        Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Tuition Fee',
            'type' => 'income',
        ]);
        Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Salary',
            'type' => 'expense',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/categories?type=expense');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Salary')
            ->assertJsonPath('data.0.type', 'expense');
    }

    public function test_categories_are_branch_isolated(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreign = Category::factory()->create([
            'branch_id' => $otherBranch->id,
            'name' => 'Foreign',
            'type' => 'income',
        ]);

        Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Mine',
            'type' => 'income',
        ]);

        // List shows only the caller's branch.
        $this->withToken($this->token)
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');

        // Out-of-branch id is hidden (404, not 403).
        $this->withToken($this->token)
            ->putJson("/api/v1/categories/{$foreign->id}", ['name' => 'Hacked', 'type' => 'income'])
            ->assertNotFound();
    }

    public function test_requires_finance_permission(): void
    {
        $outsider = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $outsider->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/categories')
            ->assertForbidden();
    }
}
