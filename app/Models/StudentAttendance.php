<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Database\Factories\StudentAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single student's attendance mark for one date, tied to an enrollment. The
 * branch derives through the enrollment's student (no local branch_id). Unique
 * per (enrollment_id, date), so re-taking a day's attendance updates the row.
 */
#[Fillable(['enrollment_id', 'date', 'status', 'recorded_by'])]
class StudentAttendance extends Model
{
    /** @use HasFactory<StudentAttendanceFactory> */
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
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * Get the enrollment this attendance mark belongs to.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Get the user (teacher/admin) who recorded this mark.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
