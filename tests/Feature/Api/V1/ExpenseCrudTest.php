<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $accountant;

    private Category $expenseCategory;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->accountant = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('accountant');
        $this->token = $this->accountant->createToken('web')->plainTextToken;

        $this->expenseCategory = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Utilities',
            'type' => 'expense',
        ]);
    }

    public function test_accountant_creates_manual_expense(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/expenses', [
            'item_name' => 'Electricity bill',
            'amount' => '8200.00',
            'date' => '2026-06-10',
            'category_id' => $this->expenseCategory->id,
            'description' => 'May bill',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.item_name', 'Electricity bill')
            ->assertJsonPath('data.amount', '8200.00')
            ->assertJsonPath('data.date', '2026-06-10')
            ->assertJsonPath('data.category_id', $this->expenseCategory->id)
            ->assertJsonPath('data.description', 'May bill');

        $this->assertDatabaseHas('expenses', [
            'branch_id' => $this->branch->id,
            'item_name' => 'Electricity bill',
            'amount' => '8200.00',
            'created_by' => $this->accountant->id,
        ]);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/expenses', [
            'item_name' => 'Bad',
            'amount' => '-100.00',
            'date' => '2026-06-11',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_income_category_is_rejected(): void
    {
        $income = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Donation',
            'type' => 'income',
        ]);

        $this->withToken($this->token)->postJson('/api/v1/expenses', [
            'item_name' => 'Wrong category',
            'amount' => '500.00',
            'date' => '2026-06-11',
            'category_id' => $income->id,
        ])->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    public function test_accountant_updates_manual_expense(): void
    {
        $expense = Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
            'item_name' => 'Old',
            'amount' => '100.00',
        ]);

        $this->withToken($this->token)->putJson("/api/v1/expenses/{$expense->id}", [
            'item_name' => 'Updated',
            'amount' => '200.00',
            'date' => '2026-06-12',
            'category_id' => $this->expenseCategory->id,
            'description' => 'note',
        ])->assertOk()->assertJsonPath('data.item_name', 'Updated')->assertJsonPath('data.amount', '200.00');

        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'item_name' => 'Updated', 'amount' => '200.00']);
    }

    public function test_accountant_deletes_manual_expense(): void
    {
        $expense = Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/expenses/{$expense->id}")
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_date_range_filter_is_inclusive(): void
    {
        foreach (['2026-05-31', '2026-06-01', '2026-06-15', '2026-06-30', '2026-07-01'] as $date) {
            Expense::factory()->create([
                'branch_id' => $this->branch->id,
                'category_id' => $this->expenseCategory->id,
                'created_by' => $this->accountant->id,
                'date' => $date,
            ]);
        }

        $this->withToken($this->token)
            ->getJson('/api/v1/expenses?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_search_filter(): void
    {
        Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
            'item_name' => 'Electricity bill',
        ]);
        Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
            'item_name' => 'Stationery',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/expenses?search=Electric')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item_name', 'Electricity bill');
    }

    public function test_sort_by_amount(): void
    {
        Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
            'amount' => '100.00',
        ]);
        Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
            'amount' => '900.00',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/expenses?sort=amount&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.amount', '100.00')
            ->assertJsonPath('data.1.amount', '900.00');
    }

    public function test_filter_by_category(): void
    {
        $other = Category::factory()->create(['branch_id' => $this->branch->id, 'type' => 'expense']);
        Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
        ]);
        Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $other->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/expenses?category_id={$other->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category_id', $other->id);
    }

    public function test_expenses_are_branch_isolated(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreign = Expense::factory()->create([
            'branch_id' => $otherBranch->id,
            'created_by' => User::factory()->create(['branch_id' => $otherBranch->id])->id,
        ]);

        Expense::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->expenseCategory->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->getJson('/api/v1/expenses')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->deleteJson("/api/v1/expenses/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_requires_expense_manage_permission(): void
    {
        $outsider = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $outsider->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/expenses')->assertForbidden();
    }
}
