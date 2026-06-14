<?php

namespace App\Models;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\ExamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An exam held for one class in one session. Branch is stamped/scoped
 * automatically via BelongsToBranch; the (session, class, type) tuple is
 * unique. Status only moves forward through the ExamStatus lifecycle.
 */
#[Fillable(['branch_id', 'session_id', 'class_id', 'type', 'name', 'start_date', 'end_date', 'status'])]
class Exam extends Model
{
    /** @use HasFactory<ExamFactory> */
    use BelongsToBranch, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ExamStatus::Upcoming->value,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ExamType::class,
            'status' => ExamStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * The session this exam belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /**
     * The class this exam is held for.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
