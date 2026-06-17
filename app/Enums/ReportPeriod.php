<?php

namespace App\Enums;

/**
 * The shared report time window selector. Every report endpoint (Phase 13)
 * accepts one of these. `weekly` is the current ISO week (Mon–Sun), `monthly`
 * the current calendar month, `yearly` the current academic session (falling
 * back to the calendar year), and `custom` an explicit from/to range. Explicit
 * from/to always override the computed window, regardless of period.
 */
enum ReportPeriod: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';
}
