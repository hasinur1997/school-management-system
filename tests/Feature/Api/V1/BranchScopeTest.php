<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\SchoolClass;
use App\Models\User;
use Database\Seeders\BranchSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SchoolClassSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchScopeTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branchOne;

    private Branch $branchTwo;

    private SchoolClass $classOne;

    private SchoolClass $classTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        Cache::flush();

        $this->branchOne = Branch::factory()->create();
        $this->branchTwo = Branch::factory()->create();
        $this->classOne = SchoolClass::factory()->create([
            'branch_id' => $this->branchOne->id,
            'name' => 'Class 1',
            'numeric_level' => 1,
        ]);
        $this->classTwo = SchoolClass::factory()->create([
            'branch_id' => $this->branchTwo->id,
            'name' => 'Class 2',
            'numeric_level' => 2,
        ]);
    }

    /**
     * Create an API token for a user with the given role. Super admins get
     * no branch (per schema); everyone else defaults to branch one.
     */
    private function tokenForRole(string $role, ?Branch $branch = null): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : ($branch ?? $this->branchOne)->id])
            ->assignRole($role);

        Sanctum::actingAs($user);

        return $user->createToken('web')->plainTextToken;
    }

    public function test_admin_cannot_reach_another_branches_class(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->classOne->id);

        // The branch filter is ignored for non-super-admins.
        $this->withToken($token)
            ->getJson("/api/v1/classes?branch_id={$this->branchTwo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->classOne->id);

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$this->classTwo->id}")
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
            ]);

        $this->withToken($token)
            ->putJson("/api/v1/classes/{$this->classTwo->id}", ['name' => 'Hijacked', 'numeric_level' => 2])
            ->assertNotFound();

        $this->withToken($token)
            ->deleteJson("/api/v1/classes/{$this->classTwo->id}")
            ->assertNotFound();

        $this->assertModelExists($this->classTwo);
        $this->assertDatabaseMissing('school_classes', ['name' => 'Hijacked']);
    }

    public function test_create_stamps_branch_automatically_and_ignores_submitted_branch_id(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/classes', [
                'name' => 'Class 3',
                'numeric_level' => 3,
                'branch_id' => $this->branchTwo->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.branch_id', $this->branchOne->id);

        $this->assertDatabaseHas('school_classes', ['numeric_level' => 3, 'branch_id' => $this->branchOne->id]);
        $this->assertDatabaseMissing('school_classes', ['numeric_level' => 3, 'branch_id' => $this->branchTwo->id]);

        // Updates ignore branch_id too: a class never changes branch.
        $this->withToken($token)
            ->putJson("/api/v1/classes/{$this->classOne->id}", [
                'name' => 'Class One',
                'numeric_level' => 1,
                'branch_id' => $this->branchTwo->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.branch_id', $this->branchOne->id);

        $this->assertDatabaseHas('school_classes', ['id' => $this->classOne->id, 'branch_id' => $this->branchOne->id]);
    }

    public function test_super_admin_sees_all_branches_and_may_filter(): void
    {
        $token = $this->tokenForRole('super_admin');

        $this->withToken($token)
            ->getJson('/api/v1/classes')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($token)
            ->getJson("/api/v1/classes?branch_id={$this->branchTwo->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.branch_id', $this->branchTwo->id);

        $this->withToken($token)
            ->getJson('/api/v1/classes?branch_id=all')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // The filter must point at an existing branch.
        $this->withToken($token)
            ->getJson('/api/v1/classes?branch_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['branch_id']);

        $this->withToken($token)
            ->getJson("/api/v1/classes/{$this->classTwo->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $this->classTwo->id);
    }

    public function test_console_context_without_an_auth_user_is_unscoped_and_does_not_crash(): void
    {
        $this->assertGuest();

        // Seeders run without an auth user and handle branches explicitly.
        $this->seed(BranchSeeder::class);
        $this->seed(SchoolClassSeeder::class);

        // 10 seeded into branch one (level 1 already existed) + class two.
        $this->assertSame(11, SchoolClass::count());

        // Creates without an auth user keep their explicit branch_id.
        $class = SchoolClass::create([
            'branch_id' => $this->branchTwo->id,
            'name' => 'Class 11',
            'numeric_level' => 11,
        ]);

        $this->assertSame($this->branchTwo->id, $class->branch_id);
        $this->assertSame(12, SchoolClass::count());
    }
}
