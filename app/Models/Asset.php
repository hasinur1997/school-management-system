<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Asset register entry (name, value, optional description + purchase date, and
 * a lifecycle status). Branch is stamped/scoped automatically via BelongsToBranch.
 */
#[Fillable([
    'branch_id', 'name', 'description',
    'value', 'purchase_date', 'status', 'created_by',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use BelongsToBranch, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'purchase_date' => 'date',
            'status' => AssetStatus::class,
        ];
    }
}
