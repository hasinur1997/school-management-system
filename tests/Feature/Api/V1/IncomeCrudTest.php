<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Income;
use App\Models\Payment;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncomeCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $accountant;

    private Category $incomeCategory;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->accountant = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('accountant');
        $this->token = $this->accountant->createToken('web')->plainTextToken;

        $this->incomeCategory = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Donation',
            'type' => 'income',
        ]);
    }

    public function test_accountant_creates_manual_income(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/v1/incomes', [
            'title' => 'Donation from alumni',
            'amount' => '25000.00',
            'date' => '2026-06-11',
            'category_id' => $this->incomeCategory->id,
            'description' => null,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Donation from alumni')
            ->assertJsonPath('data.amount', '25000.00')
            ->assertJsonPath('data.date', '2026-06-11')
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('incomes', [
            'branch_id' => $this->branch->id,
            'title' => 'Donation from alumni',
            'amount' => '25000.00',
            'payment_id' => null,
            'created_by' => $this->accountant->id,
        ]);
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/incomes', [
            'title' => 'Bad',
            'amount' => '-100.00',
            'date' => '2026-06-11',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_future_date_is_allowed(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/incomes', [
            'title' => 'Pledge',
            'amount' => '500.00',
            'date' => '2030-01-01',
        ])->assertCreated();
    }

    public function test_expense_category_is_rejected(): void
    {
        $expense = Category::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Salary',
            'type' => 'expense',
        ]);

        $this->withToken($this->token)->postJson('/api/v1/incomes', [
            'title' => 'Wrong category',
            'amount' => '500.00',
            'date' => '2026-06-11',
            'category_id' => $expense->id,
        ])->assertStatus(422)->assertJsonValidationErrors('category_id');
    }

    public function test_accountant_updates_manual_income(): void
    {
        $income = Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->incomeCategory->id,
            'created_by' => $this->accountant->id,
            'title' => 'Old',
            'amount' => '100.00',
        ]);

        $this->withToken($this->token)->putJson("/api/v1/incomes/{$income->id}", [
            'title' => 'Updated',
            'amount' => '200.00',
            'date' => '2026-06-12',
            'category_id' => $this->incomeCategory->id,
            'description' => 'note',
        ])->assertOk()->assertJsonPath('data.title', 'Updated')->assertJsonPath('data.amount', '200.00');

        $this->assertDatabaseHas('incomes', ['id' => $income->id, 'title' => 'Updated', 'amount' => '200.00']);
    }

    public function test_accountant_deletes_manual_income(): void
    {
        $income = Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->incomeCategory->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/incomes/{$income->id}")
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseMissing('incomes', ['id' => $income->id]);
    }

    public function test_system_income_appears_with_is_system_true(): void
    {
        $payment = Payment::factory()->paid()->create(['branch_id' => $this->branch->id]);
        $system = Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => null,
            'payment_id' => $payment->id,
            'created_by' => $this->accountant->id,
            'title' => 'Fee payment',
        ]);

        $this->withToken($this->token)->getJson('/api/v1/incomes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $system->id)
            ->assertJsonPath('data.0.is_system', true);
    }

    public function test_system_income_cannot_be_updated(): void
    {
        $payment = Payment::factory()->paid()->create(['branch_id' => $this->branch->id]);
        $system = Income::factory()->create([
            'branch_id' => $this->branch->id,
            'payment_id' => $payment->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->putJson("/api/v1/incomes/{$system->id}", [
            'title' => 'Hack',
            'amount' => '1.00',
            'date' => '2026-06-12',
        ])->assertStatus(403)
            ->assertJsonPath('message', 'System-generated income cannot be modified');
    }

    public function test_system_income_cannot_be_deleted(): void
    {
        $payment = Payment::factory()->paid()->create(['branch_id' => $this->branch->id]);
        $system = Income::factory()->create([
            'branch_id' => $this->branch->id,
            'payment_id' => $payment->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->deleteJson("/api/v1/incomes/{$system->id}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'System-generated income cannot be modified');

        $this->assertDatabaseHas('incomes', ['id' => $system->id]);
    }

    public function test_date_range_filter_is_inclusive(): void
    {
        foreach (['2026-05-31', '2026-06-01', '2026-06-15', '2026-06-30', '2026-07-01'] as $date) {
            Income::factory()->create([
                'branch_id' => $this->branch->id,
                'category_id' => $this->incomeCategory->id,
                'created_by' => $this->accountant->id,
                'date' => $date,
            ]);
        }

        $this->withToken($this->token)
            ->getJson('/api/v1/incomes?from=2026-06-01&to=2026-06-30')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_sort_by_amount(): void
    {
        Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->incomeCategory->id,
            'created_by' => $this->accountant->id,
            'amount' => '100.00',
        ]);
        Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->incomeCategory->id,
            'created_by' => $this->accountant->id,
            'amount' => '900.00',
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/incomes?sort=amount&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.amount', '100.00')
            ->assertJsonPath('data.1.amount', '900.00');
    }

    public function test_filter_by_category(): void
    {
        $other = Category::factory()->create(['branch_id' => $this->branch->id, 'type' => 'income']);
        Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->incomeCategory->id,
            'created_by' => $this->accountant->id,
        ]);
        Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $other->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)
            ->getJson("/api/v1/incomes?category_id={$other->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category_id', $other->id);
    }

    public function test_incomes_are_branch_isolated(): void
    {
        $otherBranch = Branch::factory()->create();
        $foreign = Income::factory()->create([
            'branch_id' => $otherBranch->id,
            'created_by' => User::factory()->create(['branch_id' => $otherBranch->id])->id,
        ]);

        Income::factory()->create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->incomeCategory->id,
            'created_by' => $this->accountant->id,
        ]);

        $this->withToken($this->token)->getJson('/api/v1/incomes')
            ->assertOk()->assertJsonCount(1, 'data');

        $this->withToken($this->token)
            ->deleteJson("/api/v1/incomes/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_requires_income_manage_permission(): void
    {
        $outsider = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');
        $token = $outsider->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/incomes')->assertForbidden();
    }
}
