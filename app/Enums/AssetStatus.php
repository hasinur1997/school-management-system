<?php

namespace App\Enums;

/**
 * Lifecycle status of an asset. Stored as VARCHAR(20), default in_use.
 * Disposed assets are excluded from the summary's total_value.
 */
enum AssetStatus: string
{
    case InUse = 'in_use';
    case Damaged = 'damaged';
    case Disposed = 'disposed';
}
