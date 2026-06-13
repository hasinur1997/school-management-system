<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use Database\Factories\CheckinIpWhitelistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A per-branch IP whitelist entry (exact IP or CIDR range) consulted on every
 * teacher check-in. Branch is stamped/scoped automatically via BelongsToBranch.
 */
#[Fillable(['branch_id', 'ip_address', 'label', 'is_active'])]
class CheckinIpWhitelist extends Model
{
    /** @use HasFactory<CheckinIpWhitelistFactory> */
    use BelongsToBranch, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
