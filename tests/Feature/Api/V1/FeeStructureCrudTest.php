<?php

namespace Tests\Feature\Api\V1;

use App\Models\AcademicSession;
use App\Models\Branch;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeStructureCrudTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AcademicSession $session;

    private SchoolClass $class;

    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->session = AcademicSession::factory()->current()->create();
        $this->class = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);

        $admin = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $this->adminToken = $admin->createToken('web')->plainTextToken;
    }

    /**
     * A well-formed create payload.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return [
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'monthly_fee' => '1500.00',
            ...$overrides,
        ];
    }

    public function test_admin_creates_fee_structure(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/fee-structures', $this->validPayload());

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.session_id', $this->session->id)
            ->assertJsonPath('data.class_id', $this->class->id)
            ->assertJsonPath('data.monthly_fee', '1500.00');

        $this->assertDatabaseHas('fee_structures', [
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'monthly_fee' => '1500.00',
        ]);
    }

    public function test_duplicate_tuple_is_rejected(): void
    {
        FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/fee-structures', $this->validPayload());

        $response->assertStatus(422)
            ->assertJsonPath('errors.class_id.0', 'Fee already defined for this class and session');
    }

    public function test_negative_amount_is_rejected(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/fee-structures', $this->validPayload(['monthly_fee' => '-1.00']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('monthly_fee');
    }

    public function test_three_decimal_amount_is_rejected(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson('/api/v1/fee-structures', $this->validPayload(['monthly_fee' => '1500.123']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors('monthly_fee');
    }

    public function test_admin_updates_amount(): void
    {
        $feeStructure = FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
            'monthly_fee' => '1500.00',
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/fee-structures/{$feeStructure->id}", ['monthly_fee' => '1600.00']);

        $response->assertOk()
            ->assertJsonPath('success', true)
            // Money is serialized as a 2dp decimal string.
            ->assertJsonPath('data.monthly_fee', '1600.00');

        $this->assertDatabaseHas('fee_structures', [
            'id' => $feeStructure->id,
            'monthly_fee' => '1600.00',
        ]);
    }

    public function test_changing_class_or_session_is_prohibited(): void
    {
        $feeStructure = FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->putJson("/api/v1/fee-structures/{$feeStructure->id}", [
                'monthly_fee' => '1600.00',
                'class_id' => SchoolClass::factory()->create(['branch_id' => $this->branch->id])->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('class_id');
    }

    public function test_no_delete_route_exists(): void
    {
        $feeStructure = FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->deleteJson("/api/v1/fee-structures/{$feeStructure->id}");

        $response->assertStatus(405);
    }

    public function test_list_filters_by_session_and_class(): void
    {
        FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $this->class->id,
        ]);

        $otherClass = SchoolClass::factory()->create(['branch_id' => $this->branch->id]);
        FeeStructure::factory()->create([
            'branch_id' => $this->branch->id,
            'session_id' => $this->session->id,
            'class_id' => $otherClass->id,
        ]);

        $response = $this->withToken($this->adminToken)
            ->getJson("/api/v1/fee-structures?class_id={$this->class->id}");

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.class_id', $this->class->id);
    }
}
