<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $phones = $this->phoneCandidates($login);

        $user = User::query()
            ->where(fn (Builder $query) => $query->where('email', $login)->orWhereIn('phone', $phones))
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
