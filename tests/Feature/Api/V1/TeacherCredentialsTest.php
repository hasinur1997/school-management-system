<?php

namespace Tests\Feature\Api\V1;

use App\Enums\TeacherStatus;
use App\Jobs\SendCredentials;
use App\Mail\CredentialsMail;
use App\Models\Branch;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TeacherCredentialsTest extends TestCase
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

    private function tokenForRole(string $role): string
    {
        $user = User::factory()
            ->create(['branch_id' => $role === 'super_admin' ? null : $this->branch->id])
            ->assignRole($role);

        return $user->createToken('web')->plainTextToken;
    }

    /**
     * @return array{0: Teacher, 1: User}
     */
    private function makeTeacher(TeacherStatus $status = TeacherStatus::Active): array
    {
        $user = User::factory()->create([
            'branch_id' => $this->branch->id,
            'password' => Hash::make('original-password'),
        ]);
        $user->assignRole('teacher');

        $teacher = Teacher::factory()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $user->id,
            'status' => $status,
        ]);

        return [$teacher, $user];
    }

    public function test_credentials_mailable_queued_with_recipient_on_create(): void
    {
        Mail::fake();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson('/api/v1/teachers', [
                'name' => 'Rahim Uddin',
                'email' => 'rahim@school.com',
                'phone' => '01712345678',
                'designation' => 'Assistant Teacher',
            ])
            ->assertCreated();

        // Queue is sync in tests, so the job runs and the mailable is sent.
        Mail::assertSent(CredentialsMail::class, function (CredentialsMail $mail): bool {
            return $mail->hasTo('rahim@school.com')
                && $mail->identifier === 'rahim@school.com'
                && $mail->role === 'Teacher'
                && $mail->password !== '';
        });
    }

    public function test_resend_queues_credentials_with_recipient(): void
    {
        Mail::fake();
        [$teacher] = $this->makeTeacher();
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->public_id}/resend-credentials")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'New credentials are being sent to the teacher.')
            ->assertJsonPath('data', null);

        Mail::assertSent(CredentialsMail::class, fn (CredentialsMail $mail): bool => $mail->hasTo($teacher->user->email));
    }

    public function test_resend_revokes_old_tokens_and_new_password_logs_in(): void
    {
        [$teacher, $user] = $this->makeTeacher();
        $oldToken = $user->createToken('web')->plainTextToken;

        // Old token works before the resend.
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertOk();
        $this->app['auth']->forgetGuards();

        $adminToken = $this->tokenForRole('admin');

        // Capture the freshly generated password off the dispatched job.
        $newPassword = null;
        Queue::fake();
        $this->withToken($adminToken)
            ->postJson("/api/v1/teachers/{$teacher->public_id}/resend-credentials")
            ->assertOk();
        Queue::assertPushed(SendCredentials::class, function (SendCredentials $job) use (&$newPassword, $user): bool {
            $newPassword = $job->password;

            return $job->user->is($user);
        });
        $this->app['auth']->forgetGuards();

        // Old token is now revoked.
        $this->withToken($oldToken)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->app['auth']->forgetGuards();

        // The new password authenticates.
        $login = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => $newPassword,
            'device_name' => 'web',
        ])->assertOk();

        // Plaintext password is never persisted.
        $this->assertDatabaseMissing('users', ['password' => $newPassword]);
        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertNotEmpty($login->json('data.token'));
    }

    public function test_resend_to_inactive_teacher_returns_409(): void
    {
        Queue::fake();
        [$teacher] = $this->makeTeacher(TeacherStatus::Inactive);
        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->public_id}/resend-credentials")
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Teacher is inactive.');

        Queue::assertNotPushed(SendCredentials::class);
    }

    public function test_resend_out_of_branch_returns_404(): void
    {
        $otherBranch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $otherBranch->id]);
        $teacher = Teacher::factory()->create(['branch_id' => $otherBranch->id, 'user_id' => $user->id]);

        $token = $this->tokenForRole('admin');

        $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->id}/resend-credentials")
            ->assertStatus(404);
    }

    public function test_resend_without_permission_returns_403(): void
    {
        [$teacher] = $this->makeTeacher();
        $token = $this->tokenForRole('accountant');

        $this->withToken($token)
            ->postJson("/api/v1/teachers/{$teacher->public_id}/resend-credentials")
            ->assertStatus(403);
    }

    public function test_job_retry_and_backoff_configured(): void
    {
        $job = new SendCredentials(User::factory()->make(), 'secret', 'Teacher');

        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff);
    }

    public function test_stale_queued_credentials_are_not_sent(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        (new SendCredentials($user, 'old-password', 'Teacher'))->handle();

        Mail::assertNothingSent();
    }
}
