<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    /**
     * Register a throwaway route guarded by a spatie permission check.
     */
    private function defineGuardedRoute(string $permission): void
    {
        Route::middleware(['auth:sanctum', "permission:{$permission}"])
            ->get('/api/test/guarded', fn () => response()->json(['success' => true, 'message' => 'OK', 'data' => null]));
    }

    public function test_seeders_are_idempotent(): void
    {
        $permissionCount = Permission::count();
        $roleCount = Role::count();
        $pivotCount = DB::table('role_has_permissions')->count();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->assertSame(count(PermissionSeeder::PERMISSIONS), $permissionCount);
        $this->assertSame(6, $roleCount);
        $this->assertSame($permissionCount, Permission::count());
        $this->assertSame($roleCount, Role::count());
        $this->assertSame($pivotCount, DB::table('role_has_permissions')->count());
    }

    public function test_super_admin_passes_any_permission_check_without_explicit_assignment(): void
    {
        $superAdmin = User::factory()->create()->assignRole('super_admin');

        $this->assertSame(0, $superAdmin->permissions()->count());

        foreach (PermissionSeeder::PERMISSIONS as $permission) {
            $this->assertTrue($superAdmin->can($permission), "super_admin denied [{$permission}]");
        }
    }

    public function test_teacher_is_denied_on_branch_manage_guarded_route(): void
    {
        $this->defineGuardedRoute('branch.manage');

        $teacher = User::factory()->create()->assignRole('teacher');
        $token = $teacher->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/test/guarded')
            ->assertForbidden()
            ->assertExactJson([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ]);
    }

    public function test_super_admin_is_allowed_on_branch_manage_guarded_route(): void
    {
        $this->defineGuardedRoute('branch.manage');

        $superAdmin = User::factory()->create()->assignRole('super_admin');
        $token = $superAdmin->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/test/guarded')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_me_returns_roles_and_effective_permissions_for_teacher(): void
    {
        $teacher = User::factory()->create()->assignRole('teacher');
        $token = $teacher->createToken('web')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.roles', ['teacher'])
            ->assertJsonPath('data.permissions', collect(RoleSeeder::ROLES['teacher'])->sort()->values()->all());
    }

    public function test_me_returns_all_permissions_for_super_admin(): void
    {
        $superAdmin = User::factory()->create()->assignRole('super_admin');
        $token = $superAdmin->createToken('web')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.roles', ['super_admin'])
            ->assertJsonPath('data.permissions', collect(PermissionSeeder::PERMISSIONS)->sort()->values()->all());
    }

    public function test_user_without_roles_gets_empty_arrays_from_me(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.roles', [])
            ->assertJsonPath('data.permissions', []);
    }
}
