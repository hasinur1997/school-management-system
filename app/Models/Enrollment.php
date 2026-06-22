<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's membership in a class+section for an academic session. The
 * branch derives through the student (no local branch_id). Promotion closes
 * one enrollment and opens the next, giving full class history per student.
 */
#[Fillable(['student_id', 'session_id', 'class_id', 'section_id', 'roll_no', 'status'])]
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory, HasPublicId;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roll_no' => 'integer',
            'status' => EnrollmentStatus::class,
        ];
    }

    /**
     * Get the student this enrollment belongs to.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the academic session this enrollment is for.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /**
     * Get the class this enrollment places the student in.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the section this enrollment places the student in.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }
}
