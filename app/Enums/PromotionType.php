<?php

namespace App\Enums;

/**
 * How a promotion was performed. Stored as VARCHAR(10). `bulk` is the
 * one-click promote of a whole class; `individual` is a single-student action
 * (which may override a fail via promotion.override).
 */
enum PromotionType: string
{
    case Bulk = 'bulk';
    case Individual = 'individual';
}
