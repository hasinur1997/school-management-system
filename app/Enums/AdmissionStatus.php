<?php

namespace App\Enums;

/**
 * Review lifecycle of an admission application. Stored as VARCHAR(20).
 * Applications start pending; the review flow (Task 3.5) transitions them to
 * approved (creating the student) or rejected (with a reason).
 */
enum AdmissionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
