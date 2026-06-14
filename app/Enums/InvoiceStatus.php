<?php

namespace App\Enums;

/**
 * Settlement status of a monthly invoice. Stored as VARCHAR(10). A fresh
 * invoice is `unpaid`; a payment that covers part of it makes it `partial` and
 * one that covers the balance makes it `paid` (payments arrive in Task 10.3+).
 */
enum InvoiceStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
}
