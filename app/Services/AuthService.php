<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Attempt to authenticate by email or phone and issue a Sanctum token.
     *
     * @return array{token: string, user: User}
     *
     * @throws ValidationException
     */
    public function login(string $login, string $password, string $deviceName): array
    {
        $user = User::query()
            ->where(fn (Builder $query) => $query->where('email', $login)->orWhere('phone', $login))
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            abort(403, 'This account is inactive. Contact the administration.');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $user->createToken($deviceName)->plainTextToken,
            'user' => $user,
        ];
    }

    /**
     * Update the user's password and revoke every token except the current one.
     */
    public function changePassword(User $user, string $password): void
    {
        $user->forceFill(['password' => $password])->save();

        $user->tokens()
            ->whereKeyNot($user->currentAccessToken()->id)
            ->delete();
    }
}
