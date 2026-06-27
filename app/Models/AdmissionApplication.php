<?php

namespace App\Models;

use App\Enums\AdmissionStatus;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Database\Factories\AdmissionApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * A public admission form submission, kept separate from student data as an
 * audit record. Branch is stamped automatically via BelongsToBranch. The
 * applicant photo and supporting documents are held in medialibrary
 * collections; previous schooling is a child table.
 */
#[Fillable([
    'application_no', 'desired_class_id',
    'name_bn', 'name_en',
    'father_name_bn', 'father_name_en', 'father_nid',
    'mother_name_bn', 'mother_name_en', 'mother_nid',
    'present_village', 'present_post_office', 'present_upazila', 'present_district', 'present_division', 'father_mobile',
    'permanent_village', 'permanent_post_office', 'permanent_upazila', 'permanent_district', 'permanent_division', 'mother_mobile',
    'father_email', 'mother_email',
    'birth_reg_no', 'date_of_birth', 'religion', 'nationality', 'caste',
    'status', 'rejection_reason', 'reviewed_by', 'reviewed_at',
])]
class AdmissionApplication extends Model implements HasMedia
{
    /** @use HasFactory<AdmissionApplicationFactory> */
    use BelongsToBranch, HasFactory, HasPublicId, InteractsWithMedia, SoftDeletes;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
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
            'reviewed_at' => 'datetime',
            'status' => AdmissionStatus::class,
        ];
    }

    /**
     * The applicant photo (single file) and supporting documents (many).
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);

        $this->addMediaCollection('documents')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf']);
    }

    /**
     * The public URL of the applicant photo, or null when none is set.
     */
    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photo') ?: null;
    }

    /**
     * Get the class the applicant is applying to.
     */
    public function desiredClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'desired_class_id');
    }

    /**
     * Get the admin who reviewed the application.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the applicant's previous schooling rows (form item 13).
     */
    public function previousEducations(): HasMany
    {
        return $this->hasMany(AdmissionPreviousEducation::class, 'application_id');
    }

    /**
     * Get the student created from this application, if it has been approved.
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class, 'application_id');
    }
}
