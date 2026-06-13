<?php

namespace App\Enums;

/**
 * Lifecycle status of a teacher profile. Stored as VARCHAR(20); an inactive
 * teacher's login is disabled (status changes land in Task 2.3).
 */
enum TeacherStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
