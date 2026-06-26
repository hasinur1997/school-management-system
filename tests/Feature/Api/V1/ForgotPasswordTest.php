<?php

namespace Tests\Feature\Api\V1;

use App\Jobs\SendPasswordResetCode;
use App\Jobs\SendPasswordResetCodeSms;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function flushAuthState(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_forgot_password_stores_a_hashed_code_and_dispatches_email_and_sms(): void
    {
        Queue::fake();
        $user = User::factory()->create([
            'email' => 'user@school.com',
            'phone' => '01712345678',
        ]);

        $code = null;
        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'user@school.com'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'If an account matches, a reset code has been sent.',
                'data' => null,
            ]);

        $record = PasswordResetCode::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($record);
        $this->assertSame(0, $record->attempts);

        Queue::assertPushed(SendPasswordResetCode::class, function (SendPasswordResetCode $job) use (&$code, $user, $record): bool {
            $code = $job->code;

            return $job->user->is($user) && Hash::check($job->code, $record->code_hash);
        });
        Queue::assertPushed(SendPasswordResetCodeSms::class, fn (SendPasswordResetCodeSms $job): bool => $job->user->is($user) && $job->code === $code);

        // The plaintext code is never stored.
        $this->assertNotSame($code, $record->code_hash);
    }

    public function test_forgot_password_by_phone_resolves_with_country_code(): void
    {
        Queue::fake();
        $user = User::factory()->create(['phone' => '01712345678']);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => '+8801712345678'])
            ->assertOk();

        $this->assertDatabaseHas('password_reset_codes', ['user_id' => $user->id]);
    }

    public function test_forgot_password_for_email_only_user_skips_sms(): void
    {
        Queue::fake();
        User::factory()->create(['email' => 'noemail@school.com', 'phone' => null]);

        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'noemail@school.com'])
            ->assertOk();

        Queue::assertPushed(SendPasswordResetCode::class);
        Queue::assertNotPushed(SendPasswordResetCodeSms::class);
    }

    public function test_forgot_password_for_unknown_account_is_silent(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/auth/forgot-password', ['login' => 'nobody@school.com'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account matches, a reset code has been sent.');

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_forgot_password_for_inactive_account_is_silent(): void
    {
        Queue::fake();
        $user = User::factory()->inactive()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->email])
            ->assertOk();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('password_reset_codes', 0);
    }

    public function test_forgot_password_replaces_any_outstanding_code(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->email])->assertOk();
        $first = PasswordResetCode::query()->where('user_id', $user->id)->value('code_hash');

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->email])->assertOk();

        $this->assertSame(1, PasswordResetCode::query()->where('user_id', $user->id)->count());
        $this->assertNotSame($first, PasswordResetCode::query()->where('user_id', $user->id)->value('code_hash'));
    }

    public function test_forgot_password_requires_a_login(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['login']);
    }

    public function test_forgot_password_is_throttled_after_five_attempts(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->email])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['login' => $user->email])->assertStatus(429);
    }
}
