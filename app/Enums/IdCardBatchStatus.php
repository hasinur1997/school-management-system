<?php

namespace App\Enums;

/**
 * Lifecycle of a queued ID card batch. Stored as VARCHAR(20), default
 * processing. The job moves it to done (PDF stored) or failed (error set).
 */
enum IdCardBatchStatus: string
{
    case Processing = 'processing';
    case Done = 'done';
    case Failed = 'failed';
}
