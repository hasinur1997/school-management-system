<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single key–value setting row. branch_id NULL means the value is global;
 * otherwise it is a per-branch override. Branch isolation is handled explicitly
 * by SettingService (not the BranchScope) because global rows must remain
 * visible to every branch and super admins manage other branches' rows.
 */
#[Fillable(['branch_id', 'key', 'value'])]
class Setting extends Model
{
    /**
     * Get the branch the setting belongs to (null for global settings).
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
