<?php

namespace App\Services;

use App\Models\AcademicSession;
use Illuminate\Support\Facades\DB;

class SessionService
{
    /**
     * Create a session, promoting it to current when requested.
     *
     * The very first session always becomes current — exactly one session
     * must be current after any write (the request layer rejects an explicit
     * `is_current: false` when no other current session exists).
     */
    public function create(array $data): AcademicSession
    {
        return DB::transaction(function () use ($data): AcademicSession {
            $makeCurrent = filter_var($data['is_current'] ?? false, FILTER_VALIDATE_BOOL)
                || ! AcademicSession::query()->where('is_current', true)->exists();

            $session = AcademicSession::create([...$data, 'is_current' => false]);

            if ($makeCurrent) {
                $this->setCurrent($session);
            }

            return $session;
        });
    }

    /**
     * Update a session, switching the current flag atomically when requested.
     */
    public function update(AcademicSession $session, array $data): AcademicSession
    {
        return DB::transaction(function () use ($session, $data): AcademicSession {
            $makeCurrent = filter_var($data['is_current'] ?? $session->is_current, FILTER_VALIDATE_BOOL);

            $session->fill([...$data, 'is_current' => $session->is_current])->save();

            if ($makeCurrent) {
                $this->setCurrent($session);
            }

            return $session;
        });
    }

    /**
     * Make the given session the single current one, unsetting all others atomically.
     */
    public function setCurrent(AcademicSession $session): void
    {
        DB::transaction(function () use ($session): void {
            AcademicSession::query()
                ->whereKeyNot($session->getKey())
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $session->forceFill(['is_current' => true])->save();
        });
    }
}
