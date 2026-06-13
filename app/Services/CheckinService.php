<?php

namespace App\Services;

use App\Enums\TeacherAttendanceStatus;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Support\IpMatcher;
use App\Support\SettingsRepository;
use Illuminate\Support\Carbon;

class CheckinService
{
    public function __construct(
        private readonly WhitelistService $whitelist,
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * Check a teacher in from the given request IP: the IP must match an active
     * whitelist entry of the teacher's branch, and the teacher must not already
     * have a record for today. Status is `late` when check-in is past the
     * branch late threshold, otherwise `present`.
     */
    public function checkIn(Teacher $teacher, string $ip): TeacherAttendance
    {
        $patterns = $this->whitelist->activeFor($teacher->branch)->pluck('ip_address');

        if (! IpMatcher::matchesAny($ip, $patterns)) {
            abort(403, 'Check-in is not permitted from this network');
        }

        $today = Carbon::today();

        if ($this->todayRecord($teacher, $today) !== null) {
            abort(409, 'Already checked in today');
        }

        $now = Carbon::now();

        return TeacherAttendance::create([
            'teacher_id' => $teacher->id,
            'date' => $today->toDateString(),
            'check_in_at' => $now,
            'check_in_ip' => $ip,
            'status' => $this->statusFor($now),
        ]);
    }

    /**
     * Stamp check-out on the teacher's record for today. Fails if there is no
     * check-in today, or if check-out was already recorded.
     */
    public function checkOut(Teacher $teacher): TeacherAttendance
    {
        $record = $this->todayRecord($teacher, Carbon::today());

        if ($record === null) {
            abort(409, 'Not checked in');
        }

        if ($record->check_out_at !== null) {
            abort(409, 'Already checked out today');
        }

        $record->update(['check_out_at' => Carbon::now()]);

        return $record;
    }

    /**
     * The teacher's attendance record for the given day, if any.
     */
    private function todayRecord(Teacher $teacher, Carbon $date): ?TeacherAttendance
    {
        return TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->whereDate('date', $date)
            ->first();
    }

    /**
     * `late` when the moment is past today's late threshold, else `present`.
     */
    private function statusFor(Carbon $now): TeacherAttendanceStatus
    {
        $threshold = $now->copy()->setTimeFromTimeString($this->settings->teacherLateThreshold());

        return $now->greaterThan($threshold)
            ? TeacherAttendanceStatus::Late
            : TeacherAttendanceStatus::Present;
    }
}
