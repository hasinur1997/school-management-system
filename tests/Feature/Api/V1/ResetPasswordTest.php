<?php

namespace Tests\Feature\Api\V1;

use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function flushAuthState(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /**
     * Seed a known reset code for a user, returning the plaintext.
     */
    private function seedCode(User $user, string $code = '123456', array $overrides = []): string
    {
        PasswordResetCode::query()->create(array_merge([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(15),
        ], $overrides));

        return $code;
    }

    private function verifyCode(User $user, string $code = '123456'): string
    {
        return $this->postJson('/api/v1/auth/verify-reset-code', [
            'login' => $user->email,
            'code' => $code,
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Reset code verified.',
            ])
            ->json('data.reset_token');
    }

    public function test_verify_reset_code_returns_a_temporary_reset_token(): void
    {
        $user = User::factory()->create(['email' => 'user@school.com']);
        $code = $this->seedCode($user);

        $resetToken = $this->verifyCode($user, $code);

        $this->assertIsString($resetToken);
        $this->assertStringContainsString('|', $resetToken);

        $record = PasswordResetCode::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->verified_at);
        $this->assertNotNull($record->reset_token_hash);
        $this->assertNotNull($record->reset_token_expires_at);
        $this->assertSame(0, $record->attempts);
    }

    public function test_reset_password_with_verified_token_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'user@school.com']);
        $token = $user->createToken('web')->plainTextToken;
        $code = $this->seedCode($user);
        $resetToken = $this->verifyCode($user, $code);

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'newSecret456',
            'password_confirmation' => 'newSecret456',
        ])->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password has been reset. Please log in.',
                'data' => null,
            ]);

        $this->assertTrue(Hash::check('newSecret456', $user->refresh()->password));
        $this->assertDatabaseMissing('password_reset_codes', ['user_id' => $user->id]);
        $this->assertSame(0, $user->tokens()->count());

        // The revoked token no longer authenticates.
        $this->flushAuthState();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

        // The new password authenticates.
        $this->postJson('/api/v1/auth/login', [
            'login' => 'user@school.com',
            'password' => 'newSecret456',
            'device_name' => 'web',
        ])->assertOk();
    }

    public function test_verify_reset_code_works_by_phone(): void
    {
        $user = User::factory()->create(['phone' => '01712345678']);
        $code = $this->seedCode($user);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'login' => '+8801712345678',
            'code' => $code,
        ])->assertOk()
            ->assertJsonStructure(['data' => ['reset_token']]);
    }

    public function test_verify_reset_code_with_wrong_code_fails_and_counts_an_attempt(): void
    {
        $user = User::factory()->create();
        $this->seedCode($user, '123456');

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'login' => $user->email,
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertSame(1, PasswordResetCode::query()->where('user_id', $user->id)->value('attempts'));
        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_verify_reset_code_burns_the_code_after_max_attempts(): void
    {
        $user = User::factory()->create();
        $this->seedCode($user, '123456', ['attempts' => 5]);

        // Even the correct code is rejected once the attempt ceiling is hit, and
        // the spent code is deleted.
        $this->postJson('/api/v1/auth/verify-reset-code', [
            'login' => $user->email,
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseMissing('password_reset_codes', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_verify_reset_code_with_expired_code_fails_and_deletes_it(): void
    {
        $user = User::factory()->create();
        $this->seedCode($user, '123456', ['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'login' => $user->email,
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseMissing('password_reset_codes', ['user_id' => $user->id]);
    }

    public function test_verify_reset_code_for_unknown_account_fails_generically(): void
    {
        $this->postJson('/api/v1/auth/verify-reset-code', [
            'login' => 'nobody@school.com',
            'code' => '123456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_reset_password_with_expired_reset_token_fails_and_deletes_it(): void
    {
        $user = User::factory()->create();
        $this->seedCode($user);
        $resetToken = $this->verifyCode($user);

        PasswordResetCode::query()
            ->where('user_id', $user->id)
            ->update(['reset_token_expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => $resetToken,
            'password' => 'newSecret456',
            'password_confirmation' => 'newSecret456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reset_token']);

        $this->assertDatabaseMissing('password_reset_codes', ['user_id' => $user->id]);
        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_reset_password_with_invalid_reset_token_fails_generically(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => 'not-a-real-token',
            'password' => 'newSecret456',
            'password_confirmation' => 'newSecret456',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['reset_token']);
    }

    public function test_reset_password_requires_confirmation_and_min_length(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [
            'reset_token' => '1|token',
            'password' => 'short',
            'password_confirmation' => 'mismatch',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_reset_password_requires_all_fields(): void
    {
        $this->postJson('/api/v1/auth/reset-password', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reset_token', 'password']);
    }
}
