<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendCredentials;
use App\Models\Branch;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class TeacherCreateTest extends TestCase
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

    /**
     * Authenticate a user with the given role and return a bearer token.
     * Super admins get no branch (per schema); everyone else gets the test branch.
     */
    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Rahim Uddin',
            'email' => 'rahim@school.com',
            'phone' => '01712345678',
            'designation' => 'Assistant Teacher',
            'joining_date' => '2026-01-15',
        ], $overrides);
    }

    public function test_create_teacher_happy_path(): void
    {
        Queue::fake();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload())
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Teacher created. Credentials are being sent.')
            ->assertJsonPath('data.name', 'Rahim Uddin')
            ->assertJsonPath('data.email', 'rahim@school.com')
            ->assertJsonPath('data.phone', '01712345678')
            ->assertJsonPath('data.designation', 'Assistant Teacher')
            ->assertJsonPath('data.joining_date', '2026-01-15')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.photo_url', null);

        // Both rows exist, branch stamped from the creator.
        $this->assertDatabaseHas('teachers', [
            'email' => 'rahim@school.com',
            'phone' => '01712345678',
            'designation' => 'Assistant Teacher',
            'status' => 'active',
            'branch_id' => $this->branch->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'rahim@school.com',
            'phone' => '01712345678',
            'branch_id' => $this->branch->id,
        ]);

        // The login carries the teacher role and links one-to-one to the profile.
        $teacher = Teacher::withoutGlobalScopes()->where('email', 'rahim@school.com')->first();
        $user = User::find($teacher->user_id);
        $this->assertTrue($user->hasRole('teacher'));
    }

    public function test_duplicate_email_returns_422_and_persists_nothing(): void
    {
        Queue::fake();
        User::factory()->create(['email' => 'rahim@school.com', 'branch_id' => $this->branch->id]);
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('teachers', 0);
        Queue::assertNotPushed(SendCredentials::class);
    }

    public function test_duplicate_phone_returns_422(): void
    {
        Queue::fake();
        User::factory()->create(['phone' => '01712345678', 'branch_id' => $this->branch->id]);
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload(['email' => 'other@school.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_missing_required_field_returns_422(): void
    {
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload(['designation' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['designation']);
    }

    public function test_without_permission_returns_403(): void
    {
        $token = $this->tokenForRole('accountant');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload())
            ->assertStatus(403);
    }

    public function test_exception_mid_transaction_rolls_back_both_rows(): void
    {
        $token = $this->tokenForRole('admin');

        // Force a failure after the user is created but before commit.
        Teacher::creating(function (): void {
            throw new RuntimeException('boom');
        });

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload())
            ->assertStatus(500);

        $this->assertDatabaseMissing('users', ['email' => 'rahim@school.com']);
        $this->assertDatabaseCount('teachers', 0);
    }

    public function test_created_teacher_can_login_and_me_shows_teacher_role(): void
    {
        Queue::fake();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload())
            ->assertCreated();

        // The plaintext password is only available on the dispatched job.
        $password = null;
        Queue::assertPushed(SendCredentials::class, function (SendCredentials $job) use (&$password): bool {
            $password = $job->password;

            return $job->user->email === 'rahim@school.com';
        });

        $login = $this->postJson('/api/v1/auth/login', [
            'login' => 'rahim@school.com',
            'password' => $password,
            'device_name' => 'web',
        ])->assertOk();

        // Drop the cached guard user so /auth/me resolves the new bearer token.
        $this->app['auth']->forgetGuards();

        $this->withToken($login->json('data.token'))
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.roles', ['teacher']);
    }

    public function test_send_credentials_dispatched_after_commit(): void
    {
        Queue::fake();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', $this->payload())
            ->assertCreated();

        Queue::assertPushed(SendCredentials::class, 1);
    }
}
