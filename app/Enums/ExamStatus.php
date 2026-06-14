<?php

namespace App\Enums;

/**
 * An exam's lifecycle stage, stored as VARCHAR(20) on exams. The status only
 * ever moves forward: upcoming → ongoing → completed → published. Publishing
 * is a dedicated flow (Task 8.1); here `published` is only guarded against —
 * the generic update endpoint cannot set or modify a published exam.
 */
enum ExamStatus: string
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Published = 'published';

    /**
     * The lifecycle position used to reject status regressions (a higher rank
     * may never move to a lower one).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Upcoming => 0,
            self::Ongoing => 1,
            self::Completed => 2,
            self::Published => 3,
        };
    }
}
