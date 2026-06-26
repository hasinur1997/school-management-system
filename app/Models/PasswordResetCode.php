<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'code_hash',
    'reset_token_hash',
    'attempts',
    'expires_at',
    'verified_at',
    'reset_token_expires_at',
])]
class PasswordResetCode extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the code's validity window has elapsed.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Whether the verified reset-token validity window has elapsed.
     */
    public function isResetTokenExpired(): bool
    {
        return $this->reset_token_expires_at === null || $this->reset_token_expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
        ];
    }
}
