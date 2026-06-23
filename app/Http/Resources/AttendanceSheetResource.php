<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The attendance entry sheet: the date, the class and section names, and the
 * roster of active students in roll order. Each student's `status` is the
 * day's existing mark, or null when attendance has not yet been taken.
 *
 * Wraps the array AttendanceService::sheet() returns.
 */
class AttendanceSheetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $records = $this->resource['records'];

        return [
            'date' => $this->resource['date'],
            'class' => $this->resource['section']->schoolClass->name,
            'section' => $this->resource['section']->name,
            'students' => $this->resource['enrollments']->map(function (Enrollment $enrollment) use ($records): array {
                $student = $enrollment->student;
                $record = $records->get($enrollment->id);

                return [
                    'enrollment_id' => $enrollment->public_id,
                    // The student's public id, so the roster row can link to the
                    // student detail page.
                    'student_id' => $student->public_id,
                    'roll_no' => $enrollment->roll_no,
                    'name_en' => $student->name_en,
                    'photo_url' => $student->photoUrl(),
                    'status' => $record?->status->value,
                    // When the mark was last recorded and by whom; all null until
                    // attendance is taken. Drive the "recorded at" / "recorded
                    // by" columns. The recorder ids let the row link to the
                    // teacher profile (when the recorder is a teacher) or the
                    // user profile otherwise.
                    'recorded_at' => $record?->updated_at?->toIso8601String(),
                    'recorded_by' => $record?->recorder?->name,
                    'recorded_by_teacher_id' => $record?->recorder?->teacher?->public_id,
                    'recorded_by_user_id' => $record?->recorder?->public_id,
                ];
            })->all(),
        ];
    }
}
