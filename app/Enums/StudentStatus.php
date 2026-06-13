<?php

namespace App\Enums;

/**
 * Lifecycle status of a student. Stored as VARCHAR(20). A `tc` student has been
 * issued a transfer certificate and is excluded from attendance, invoicing and
 * promotion; `inactive` is a soft administrative hold.
 */
enum StudentStatus: string
{
    case Active = 'active';
    case Tc = 'tc';
    case Inactive = 'inactive';
}
