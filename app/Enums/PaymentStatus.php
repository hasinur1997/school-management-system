<?php

namespace App\Enums;

/**
 * Settlement status of a payment. Stored as VARCHAR(20). A cash payment is
 * created and moved to `paid` in one shot (Task 10.3); an online payment starts
 * `pending` and is settled to `paid` (or marked `failed`) by the IPN (10.5).
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
