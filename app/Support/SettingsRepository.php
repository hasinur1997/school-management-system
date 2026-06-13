<?php

namespace App\Support;

/**
 * Stub source for tunable settings. Reads config defaults today; Task 14.1
 * replaces the body with a database-backed lookup (the settings table) without
 * touching callers.
 */
class SettingsRepository
{
    /**
     * The time of day (HH:MM, app timezone) after which a teacher check-in is
     * marked `late` rather than `present`. Defaults to 09:00.
     */
    public function teacherLateThreshold(): string
    {
        return config('attendance.teacher_late_threshold', '09:00');
    }
}
