<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasPublicId
{
    /**
     * Boot the trait.
     */
    protected static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            $model->public_id ??= static::newPublicId();
        });
    }

    /**
     * Generate a new non-sequential public identifier.
     */
    public static function newPublicId(): string
    {
        return (string) Str::ulid();
    }

    /**
     * Use public ids for implicit route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
