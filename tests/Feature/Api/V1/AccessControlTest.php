<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private User $superAdmin;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->branch = Branch::factory()->create();
        $this->superAdmin = User::factory()->create()->assignRole('super_admin');
        $this->token = $this->superAdmin->createToken('web')->plainTextToken;
    }

    public function test_plain_admin_is_denied_on_every_route(): void
    {
        $admin = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $adminToken = $admin->createToken('web')->plainTextToken;

        // Bindings ({role}, {user}) resolve before the permission middleware, so
        // targets are chosen in-branch/existing to isolate the 403 denial.
        $teacherRoleId = Role::findByName('teacher')->id;

        $routes = [
            ['get', '/api/v1/permissions'],
            ['get', '/api/v1/roles'],
            ['get', "/api/v1/roles/{$teacherRoleId}"],
            ['put', "/api/v1/roles/{$teacherRoleId}/permissions"],
            ['get', '/api/v1/users'],
            ['put', "/api/v1/users/{$admin->id}/roles"],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->withToken($adminToken)->json($method, $uri, [])
                ->assertForbidden();
        }
    }

    public function test_show_user_returns_account_for_super_admin(): void
    {
        $user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Rakib Hasan',
        ])->assignRole('admin');

        $this->withToken($this->token)
            ->getJson("/api/v1/users/{$user->getRouteKey()}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->getRouteKey())
            ->assertJsonPath('data.name', 'Rakib Hasan')
            ->assertJsonPath('data.roles', ['admin']);
    }

    public function test_show_user_is_forbidden_for_non_super_admin(): void
    {
        $admin = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('admin');
        $adminToken = $admin->createToken('web')->plainTextToken;
        $target = User::factory()->create(['branch_id' => $this->branch->id]);

        $this->withToken($adminToken)
            ->getJson("/api/v1/users/{$target->getRouteKey()}")
            ->assertForbidden();
    }

    public function test_super_admin_reaches_every_endpoint(): void
    {
        $teacherRoleId = Role::findByName('teacher')->id;

        $this->withToken($this->token)->getJson('/api/v1/permissions')->assertOk();
        $this->withToken($this->token)->getJson('/api/v1/roles')->assertOk();
        $this->withToken($this->token)->getJson("/api/v1/roles/{$teacherRoleId}")->assertOk();
        $this->withToken($this->token)->putJson("/api/v1/roles/{$teacherRoleId}/permissions", ['permissions' => []])->assertOk();
        $this->withToken($this->token)->getJson('/api/v1/users')->assertOk();
        $this->withToken($this->token)->putJson("/api/v1/users/{$this->superAdmin->id}/roles", ['roles' => ['super_admin']])->assertOk();
    }

    public function test_permissions_are_grouped_by_module(): void
    {
        $response = $this->withToken($this->token)->getJson('/api/v1/permissions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['groups' => [['module', 'permissions' => [['name', 'label']]]]],
            ]);

        $groups = collect($response->json('data.groups'));
        $branch = $groups->firstWhere('module', 'branch');

        $this->assertSame('branch', $branch['module']);
        $this->assertSame('branch.manage', $branch['permissions'][0]['name']);
        $this->assertSame('Branch Manage', $branch['permissions'][0]['label']);

        // role.manage is part of the registry, grouped under "role".
        $role = $groups->firstWhere('module', 'role');
        $this->assertSame('role.manage', $role['permissions'][0]['name']);
    }

    public function test_role_list_carries_shape(): void
    {
        User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');

        $response = $this->withToken($this->token)->getJson('/api/v1/roles');

        $response->assertOk()
            ->assertJsonCount(6, 'data');

        $superAdminRole = collect($response->json('data'))->firstWhere('name', 'super_admin');
        $this->assertTrue($superAdminRole['is_protected']);
        $this->assertSame(1, $superAdminRole['users_count']);
        $this->assertSame([], $superAdminRole['permissions']);

        $teacherRole = collect($response->json('data'))->firstWhere('name', 'teacher');
        $this->assertFalse($teacherRole['is_protected']);
        $this->assertSame(1, $teacherRole['users_count']);
        $this->assertContains('marks.entry', $teacherRole['permissions']);
    }

    public function test_show_unknown_role_is_404(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/roles/9999')
            ->assertNotFound();
    }

    public function test_sync_role_permissions_changes_effective_permissions_of_assigned_user(): void
    {
        $accountantRole = Role::findByName('accountant');
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('accountant');

        $this->assertTrue($user->fresh()->can('income.manage'));

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/roles/{$accountantRole->id}/permissions", [
                'permissions' => ['student.view', 'attendance.view'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'accountant')
            ->assertJsonPath('data.permissions', ['attendance.view', 'student.view']);

        // Effective permissions of the already-assigned user update immediately.
        $fresh = $user->fresh();
        $this->assertTrue($fresh->can('student.view'));
        $this->assertFalse($fresh->can('income.manage'));
    }

    public function test_sync_role_permissions_rejects_unknown_permission(): void
    {
        $accountantRole = Role::findByName('accountant');

        $this->withToken($this->token)
            ->putJson("/api/v1/roles/{$accountantRole->id}/permissions", [
                'permissions' => ['student.view', 'does.not.exist'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions.1');
    }

    public function test_sync_super_admin_role_permissions_is_forbidden(): void
    {
        $superAdminRole = Role::findByName('super_admin');

        $this->withToken($this->token)
            ->putJson("/api/v1/roles/{$superAdminRole->id}/permissions", [
                'permissions' => ['student.view'],
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'The super admin role cannot be modified');

        $this->assertSame(0, $superAdminRole->fresh()->permissions()->count());
    }

    public function test_sync_user_roles_happy_path(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');

        $response = $this->withToken($this->token)
            ->putJson("/api/v1/users/{$user->id}/roles", ['roles' => ['accountant']]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.roles', ['accountant']);

        $this->assertTrue($user->fresh()->hasRole('accountant'));
        $this->assertFalse($user->fresh()->hasRole('teacher'));
    }

    public function test_sync_user_roles_rejects_unknown_role(): void
    {
        $user = User::factory()->create(['branch_id' => $this->branch->id])->assignRole('teacher');

        $this->withToken($this->token)
            ->putJson("/api/v1/users/{$user->id}/roles", ['roles' => ['overlord']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('roles.0');
    }

    public function test_sync_user_roles_unknown_user_is_404(): void
    {
        $this->withToken($this->token)
            ->putJson('/api/v1/users/9999/roles', ['roles' => ['accountant']])
            ->assertNotFound();
    }

    public function test_stripping_the_last_super_admin_is_rejected(): void
    {
        // Only one super admin exists (the caller). Removing their role locks out.
        $this->withToken($this->token)
            ->putJson("/api/v1/users/{$this->superAdmin->id}/roles", ['roles' => ['admin']])
            ->assertStatus(422)
            ->assertJsonPath('errors.roles.0', 'At least one super admin is required');

        $this->assertTrue($this->superAdmin->fresh()->hasRole('super_admin'));
    }

    public function test_stripping_super_admin_allowed_when_another_active_one_exists(): void
    {
        $other = User::factory()->create()->assignRole('super_admin');

        $this->withToken($this->token)
            ->putJson("/api/v1/users/{$other->id}/roles", ['roles' => ['admin']])
            ->assertOk()
            ->assertJsonPath('data.roles', ['admin']);
    }

    public function test_users_list_paginates_and_filters_by_search_and_role(): void
    {
        User::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Karim Uddin', 'email' => 'karim@example.com'])->assignRole('accountant');
        User::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Rahim Mia'])->assignRole('teacher');

        // Pagination meta present.
        $this->withToken($this->token)->getJson('/api/v1/users?per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 1);

        // Search by name.
        $this->withToken($this->token)->getJson('/api/v1/users?search=Karim')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Karim Uddin')
            ->assertJsonPath('data.0.roles', ['accountant']);

        // Filter by role.
        $this->withToken($this->token)->getJson('/api/v1/users?role=teacher')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Rahim Mia');
    }

    public function test_users_list_is_branch_scoped_for_a_non_super_admin_delegate(): void
    {
        $otherBranch = Branch::factory()->create();
        User::factory()->create(['branch_id' => $otherBranch->id, 'name' => 'Foreign Staff'])->assignRole('teacher');

        // A delegate: holds role.manage directly but is not a super admin, so
        // the BranchScope applies and they see only their own branch's users.
        $delegate = User::factory()->create(['branch_id' => $this->branch->id, 'name' => 'Local Delegate'])->assignRole('admin');
        $delegate->givePermissionTo('role.manage');
        $delegateToken = $delegate->createToken('web')->plainTextToken;

        $response = $this->withToken($delegateToken)->getJson('/api/v1/users');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Local Delegate', $names);
        $this->assertNotContains('Foreign Staff', $names);
    }
}
