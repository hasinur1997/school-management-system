<?php

namespace App\Enums;

/**
 * A teacher's attendance mark for a single day, stored as VARCHAR(10) on
 * teacher_attendances. Set to present|late on self check-in (per the branch
 * late-threshold setting) and may be corrected by an admin to absent|leave.
 */
enum TeacherAttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case Leave = 'leave';
}
