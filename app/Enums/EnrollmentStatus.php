<?php

namespace App\Enums;

/**
 * Status of a student's enrollment in a class+section for an academic session.
 * Stored as VARCHAR(20). An enrollment is `active` while current; promotion
 * closes it as `promoted` or `failed`, and a transfer certificate sets `tc`.
 */
enum EnrollmentStatus: string
{
    case Active = 'active';
    case Promoted = 'promoted';
    case Failed = 'failed';
    case Tc = 'tc';
}
