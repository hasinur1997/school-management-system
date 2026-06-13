<?php

namespace App\Models;

use App\Enums\TeacherStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\TeacherFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A teacher profile attached one-to-one to a login (users row). Branch is
 * stamped automatically from the creator via BelongsToBranch.
 */
#[Fillable(['user_id', 'name', 'email', 'phone', 'designation', 'joining_date', 'status'])]
class Teacher extends Model implements HasMedia
{
    /** @use HasFactory<TeacherFactory> */
    use BelongsToBranch, HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'status' => TeacherStatus::class,
        ];
    }

    /**
     * The profile photo: a single file, replaced on each upload.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);
    }

    /**
     * The public URL of the profile photo, or null when none is set.
     */
    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photo') ?: null;
    }

    /**
     * Get the login the teacher authenticates with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the teacher's session assignments (class/section/subject duties).
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
