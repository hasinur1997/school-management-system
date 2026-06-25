<?php

namespace Tests\Feature\Api\V1;

use App\Models\Branch;
use App\Models\ParentProfile;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Reset resolved guards so the next request re-authenticates from its token.
     */
    private function flushAuthState(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_login_with_email_succeeds(): void
    {
        $user = User::factory()->create(['email' => 'teacher@school.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'teacher@school.com',
            'password' => 'password',
            'device_name' => 'web',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => 'teacher@school.com',
                        'phone' => $user->phone,
                        'branch_id' => null,
                        'is_active' => true,
                        'roles' => [],
                        'permissions' => [],
                    ],
                ],
            ])
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_with_phone_succeeds(): void
    {
        $user = User::factory()->create(['phone' => '01712345678']);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => '01712345678',
            'password' => 'password',
            'device_name' => 'mobile',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_login_with_phone_normalizes_country_code_and_separators(): void
    {
        $user = User::factory()->create(['phone' => '01712345678']);

        foreach (['+8801712345678', '8801712345678', '+880 1712-345678', '017 1234 5678'] as $login) {
            $this->postJson('/api/v1/auth/login', [
                'login' => $login,
                'password' => 'password',
                'device_name' => 'mobile',
            ])->assertOk()
                ->assertJsonPath('data.user.id', $user->public_id);
        }
    }

    public function test_login_updates_last_login_at(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->last_login_at);

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'web',
        ])->assertOk();

        $this->assertNotNull($user->refresh()->last_login_at);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'web',
        ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'The provided credentials are incorrect.',
                'errors' => [
                    'login' => ['The provided credentials are incorrect.'],
                ],
            ]);
    }

    public function test_login_with_unknown_user_fails(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'login' => 'nobody@school.com',
            'password' => 'password',
            'device_name' => 'web',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_login_with_missing_fields_returns_per_field_errors(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['login', 'password', 'device_name']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create();

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'web',
        ]);

        $response->assertForbidden()
            ->assertJson([
                'success' => false,
                'message' => 'This account is inactive. Contact the administration.',
            ]);
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/login', [
                'login' => $user->email,
                'password' => 'wrong-password',
                'device_name' => 'web',
            ])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'password',
            'device_name' => 'web',
        ])->assertStatus(429);
    }

    public function test_me_returns_current_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'branch_id' => null,
                    'is_active' => true,
                    'roles' => [],
                    'permissions' => [],
                ],
            ]);
    }

    public function test_me_without_token_returns_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);
    }

    public function test_update_profile_changes_current_user_details(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '01710000000',
        ]);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/auth/profile', [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'phone' => '01719999999',
        ])->assertOk()
            ->assertJsonPath('message', 'Profile updated')
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.email', 'new@example.com')
            ->assertJsonPath('data.phone', '01719999999')
            ->assertJsonPath('data.photo_url', null);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'phone' => '01719999999',
        ]);
    }

    public function test_update_profile_rejects_duplicate_email_and_phone(): void
    {
        $existing = User::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/auth/profile', [
            'name' => 'New Name',
            'email' => $existing->email,
            'phone' => $existing->phone,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_update_profile_keeps_teacher_contact_fields_in_sync(): void
    {
        $this->flushAuthState();

        $teacherBranch = Branch::factory()->create();
        $teacherUser = User::factory()->create([
            'branch_id' => $teacherBranch->id,
            'name' => 'Old Teacher',
            'email' => 'old.teacher@example.com',
            'phone' => '01710000001',
        ]);
        $teacher = Teacher::factory()->create([
            'user_id' => $teacherUser->id,
            'branch_id' => $teacherBranch->id,
            'name' => 'Old Teacher',
            'email' => 'old.teacher@example.com',
            'phone' => '01710000001',
        ]);
        $teacherToken = $teacherUser->createToken('web')->plainTextToken;

        $this->withToken($teacherToken)->putJson('/api/v1/auth/profile', [
            'name' => 'New Teacher',
            'email' => 'new.teacher@example.com',
            'phone' => '01710000002',
        ])->assertOk();

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'name' => 'New Teacher',
            'email' => 'new.teacher@example.com',
            'phone' => '01710000002',
        ]);
    }

    public function test_update_profile_keeps_parent_contact_fields_in_sync(): void
    {
        $this->flushAuthState();
        $parentBranch = Branch::factory()->create();
        $parentUser = User::factory()->create([
            'branch_id' => $parentBranch->id,
            'name' => 'Old Parent',
            'email' => null,
            'phone' => '01710000003',
        ]);
        $parent = ParentProfile::factory()->create([
            'user_id' => $parentUser->id,
            'branch_id' => $parentBranch->id,
            'name' => 'Old Parent',
            'phone' => '01710000003',
        ]);
        $parentToken = $parentUser->createToken('web')->plainTextToken;

        $this->withToken($parentToken)->putJson('/api/v1/auth/profile', [
            'name' => 'New Parent',
            'email' => null,
            'phone' => '01710000004',
        ])->assertOk();

        $this->assertDatabaseHas('parents', [
            'id' => $parent->id,
            'name' => 'New Parent',
            'phone' => '01710000004',
        ]);
    }

    public function test_teacher_profile_update_requires_email(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'email' => 'teacher@example.com',
        ]);
        Teacher::factory()->create([
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'email' => 'teacher@example.com',
        ]);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/auth/profile', [
            'name' => 'New Teacher',
            'email' => null,
            'phone' => '01712222222',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_profile_photo_upload_then_replacement(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $first = $this->withToken($token)->postJson('/api/v1/auth/photo', [
            'photo' => UploadedFile::fake()->image('first.jpg'),
        ])->assertOk()
            ->assertJsonPath('message', 'Profile photo updated');

        $this->assertNotNull($first->json('data.photo_url'));
        $this->assertSame(1, $user->fresh()->getMedia('photo')->count());

        $this->withToken($token)->postJson('/api/v1/auth/photo', [
            'photo' => UploadedFile::fake()->image('second.png'),
        ])->assertOk();

        $this->assertSame(1, $user->fresh()->getMedia('photo')->count());
    }

    public function test_profile_photo_rejects_wrong_type_and_oversize(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/photo', [
            'photo' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);

        $this->withToken($token)->postJson('/api/v1/auth/photo', [
            'photo' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_change_password_succeeds_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('web')->plainTextToken;
        $otherToken = $user->createToken('mobile')->plainTextToken;

        $response = $this->withToken($currentToken)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => 'newSecret456',
            'password_confirmation' => 'newSecret456',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully',
                'data' => null,
            ]);

        $this->assertTrue(Hash::check('newSecret456', $user->refresh()->password));
        $this->assertSame(1, $user->tokens()->count());

        $this->flushAuthState();
        $this->withToken($currentToken)->getJson('/api/v1/auth/me')->assertOk();

        $this->flushAuthState();
        $this->withToken($otherToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_change_password_with_wrong_current_password_fails(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'wrong-password',
            'password' => 'newSecret456',
            'password_confirmation' => 'newSecret456',
        ])->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['current_password']);

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_change_password_must_differ_from_current(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/change-password', [
            'current_password' => 'password',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $currentToken = $user->createToken('web')->plainTextToken;
        $otherToken = $user->createToken('mobile')->plainTextToken;

        $this->withToken($currentToken)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Logged out',
                'data' => null,
            ]);

        $this->flushAuthState();
        $this->withToken($currentToken)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->flushAuthState();
        $this->withToken($otherToken)->getJson('/api/v1/auth/me')->assertOk();
    }
}
