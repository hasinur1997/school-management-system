<?php

namespace App\Services;

use App\Jobs\SendPasswordResetCode;
use App\Jobs\SendPasswordResetCodeSms;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        $user = $this->resolveByLogin($login);

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
            'user' => $user->load(['branch', 'media']),
        ];
    }

    /**
     * Build the set of phone formats a login string could match. Phones are
     * stored in local form (01XXXXXXXXX); strip separators and fold the
     * +880/880 country code down to the leading 0 so a user can type any of
     * "+8801712345678", "880 1712-345678" or "01712345678".
     *
     * @return array<int, string>
     */
    private function phoneCandidates(string $login): array
    {
        $digits = preg_replace('/[\s\-()]/', '', $login);

        $local = $digits;

        if (str_starts_with($local, '+880')) {
            $local = '0'.substr($local, 4);
        } elseif (str_starts_with($local, '880')) {
            $local = '0'.substr($local, 3);
        }

        return array_values(array_unique([$login, $digits, $local]));
    }

    /**
     * Resolve a user from an email or phone login string, matching the same
     * phone formats accepted at sign-in.
     */
    private function resolveByLogin(string $login): ?User
    {
        $phones = $this->phoneCandidates($login);

        return User::query()
            ->where(fn (Builder $query) => $query->where('email', $login)->orWhereIn('phone', $phones))
            ->first();
    }

    /**
     * Issue a one-time reset code for the account matching the login string and
     * deliver it over email and/or SMS. Silent by design: unknown or inactive
     * accounts produce no observable difference, preventing enumeration. A new
     * request replaces any outstanding code for the user.
     */
    public function sendPasswordResetCode(string $login): void
    {
        $user = $this->resolveByLogin($login);

        if (! $user || ! $user->is_active) {
            return;
        }

        $length = (int) config('auth.reset_code.length', 6);
        $ttl = (int) config('auth.reset_code.ttl', 15);

        $code = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);

        PasswordResetCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash' => Hash::make($code),
                'reset_token_hash' => null,
                'attempts' => 0,
                'expires_at' => now()->addMinutes($ttl),
                'verified_at' => null,
                'reset_token_expires_at' => null,
            ],
        );

        if ($user->email !== null) {
            SendPasswordResetCode::dispatch($user, $code, $ttl)->afterCommit();
        }

        if ($user->phone !== null) {
            SendPasswordResetCodeSms::dispatch($user, $code, $ttl)->afterCommit();
        }
    }

    /**
     * Check a reset code without consuming it, then issue a temporary reset
     * token so the UI can open the new-password step without asking for the
     * login/code again. A wrong but live code still burns one of the attempts.
     *
     * @throws ValidationException
     */
    public function verifyResetCode(string $login, string $code): string
    {
        $record = $this->validateResetCode($this->resolveByLogin($login), $code);
        $token = Str::random(64);
        $ttl = (int) config('auth.reset_code.ttl', 15);

        $record->forceFill([
            'reset_token_hash' => Hash::make($token),
            'verified_at' => now(),
            'reset_token_expires_at' => now()->addMinutes($ttl),
        ])->save();

        return $record->id.'|'.$token;
    }

    /**
     * Set a new password with a token produced by verifyResetCode(). On success
     * the reset record is consumed and every existing token is revoked, forcing
     * re-login.
     *
     * @throws ValidationException
     */
    public function resetPassword(string $resetToken, string $password): void
    {
        $record = $this->validateResetToken($resetToken);
        $user = $record->user;

        DB::transaction(function () use ($user, $record, $password): void {
            $user->forceFill(['password' => $password])->save();
            $user->tokens()->delete();
            $record->delete();
        });
    }

    /**
     * Resolve and validate the outstanding reset code for a user, returning the
     * record when the supplied code is correct, unexpired, and within the
     * attempt ceiling. Throws the generic failure otherwise — deleting a spent
     * (expired/exhausted) code and burning an attempt on a wrong-but-live code.
     *
     * @throws ValidationException
     */
    private function validateResetCode(?User $user, string $code): PasswordResetCode
    {
        $record = $user
            ? PasswordResetCode::query()->where('user_id', $user->id)->first()
            : null;

        $maxAttempts = (int) config('auth.reset_code.max_attempts', 5);

        if (! $user || ! $user->is_active || ! $record || $record->isExpired() || $record->attempts >= $maxAttempts) {
            $record?->delete();

            $this->failReset();
        }

        if (! Hash::check($code, $record->code_hash)) {
            $record->increment('attempts');

            $this->failReset();
        }

        return $record;
    }

    /**
     * Validate the temporary token created after a successful reset-code
     * verification. The token carries the record id as a selector, while the
     * random secret remains hashed at rest.
     *
     * @throws ValidationException
     */
    private function validateResetToken(string $resetToken): PasswordResetCode
    {
        [$id, $token] = array_pad(explode('|', $resetToken, 2), 2, null);

        if (! is_numeric($id) || ! is_string($token) || $token === '') {
            $this->failReset('reset_token');
        }

        $record = PasswordResetCode::query()
            ->with('user')
            ->find((int) $id);

        if (
            ! $record
            || ! $record->user?->is_active
            || $record->isResetTokenExpired()
            || $record->reset_token_hash === null
        ) {
            $record?->delete();

            $this->failReset('reset_token');
        }

        if (! Hash::check($token, $record->reset_token_hash)) {
            $this->failReset('reset_token');
        }

        return $record;
    }

    /**
     * Raise the shared, deliberately vague reset failure. Kept generic so a
     * caller cannot distinguish a wrong code from an unknown account.
     *
     * @throws ValidationException
     */
    private function failReset(string $field = 'code'): never
    {
        throw ValidationException::withMessages([
            $field => ['The reset code is invalid or has expired.'],
        ]);
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

    /**
     * Update the authenticated user's account details. Teacher and parent
     * profiles duplicate these simple contact fields, so keep them in sync.
     *
     * @param  array{name: string, email: string|null, phone: string}  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'],
            ])->save();

            $teacherData = [
                'name' => $data['name'],
                'phone' => $data['phone'],
            ];

            if (($data['email'] ?? null) !== null) {
                $teacherData['email'] = $data['email'];
            }

            $user->teacher()->withoutGlobalScopes()->update($teacherData);
            $user->parentProfile()->withoutGlobalScopes()->update([
                'name' => $data['name'],
                'phone' => $data['phone'],
            ]);

            return $user->load(['branch', 'media']);
        });
    }

    /**
     * Store/replace the authenticated user's account photo.
     */
    public function setPhoto(User $user, UploadedFile $photo): User
    {
        $user->addMedia($photo)->toMediaCollection('photo');

        return $user->load(['branch', 'media']);
    }
}
