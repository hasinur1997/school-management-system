<?php

namespace App\Enums;

/**
 * Whether a category groups income or expense rows. Stored as VARCHAR(10);
 * the (branch, name, type) tuple is unique, so the same name may exist once
 * per type.
 */
enum CategoryType: string
{
    case Income = 'income';
    case Expense = 'expense';
}
