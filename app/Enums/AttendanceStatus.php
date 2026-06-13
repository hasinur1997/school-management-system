<?php

namespace App\Enums;

/**
 * A student's attendance mark for a single day, stored as VARCHAR(10) on
 * student_attendances. A missing row (no enum value) means attendance has not
 * yet been taken for that enrollment on that date.
 */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Late = 'late';
    case Leave = 'leave';
}
