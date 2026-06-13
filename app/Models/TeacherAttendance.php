<?php

namespace App\Models;

use App\Enums\TeacherAttendanceStatus;
use Database\Factories\TeacherAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher's daily attendance record, created on self check-in and optionally
 * corrected by an admin. Branch is derived through the teacher relation, so the
 * model carries no branch_id of its own. Check-in/check-out logic lands in 6.2.
 */
#[Fillable([
    'teacher_id', 'date', 'check_in_at', 'check_out_at',
    'check_in_ip', 'status', 'corrected_by',
])]
class TeacherAttendance extends Model
{
    /** @use HasFactory<TeacherAttendanceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => TeacherAttendanceStatus::class,
        ];
    }

    /**
     * The teacher this record belongs to.
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * The admin who corrected this record, if any.
     */
    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }
}
