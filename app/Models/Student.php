<?php

namespace App\Models;

use App\Enums\StudentStatus;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A student profile created at admission approval, attached one-to-one to a
 * login (users row) and optionally linked back to the originating
 * admission_application. Branch is stamped automatically via BelongsToBranch.
 * The student photo lives in a medialibrary collection.
 */
#[Fillable([
    'user_id', 'application_id', 'admission_no',
    'name_bn', 'name_en',
    'father_name_bn', 'father_name_en', 'father_nid',
    'mother_name_bn', 'mother_name_en', 'mother_nid',
    'present_village', 'present_post_office', 'present_upazila', 'present_district',
    'permanent_village_bn', 'permanent_post_office_bn', 'permanent_upazila_bn', 'permanent_district_bn',
    'permanent_village_en', 'permanent_post_office_en', 'permanent_upazila_en', 'permanent_district_en',
    'father_mobile', 'mother_mobile',
    'birth_reg_no', 'date_of_birth', 'religion', 'nationality', 'caste',
    'status', 'admitted_at',
])]
class Student extends Model implements HasMedia
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToBranch, HasFactory, InteractsWithMedia, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'nationality' => 'Bangladeshi',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'admitted_at' => 'date',
            'status' => StudentStatus::class,
        ];
    }

    /**
     * The student photo: a single file, replaced on each upload.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);
    }

    /**
     * The public URL of the student photo, or null when none is set.
     */
    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photo') ?: null;
    }

    /**
     * Get the login the student authenticates with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admission application this student was created from, if any.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AdmissionApplication::class, 'application_id');
    }

    /**
     * Get the student's enrollment rows (one per academic session).
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the student's enrollment for the current academic session.
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)
            ->whereHas('session', fn ($query) => $query->where('is_current', true));
    }

    /**
     * Get the parents/guardians linked to this student.
     */
    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentProfile::class, 'parent_student', 'student_id', 'parent_id');
    }

    /**
     * Get the student's transfer certificate, if one has been issued (at most
     * one — the student_id column is unique).
     */
    public function transferCertificate(): HasOne
    {
        return $this->hasOne(TransferCertificate::class);
    }
}
