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

                return [
                    'enrollment_id' => $enrollment->public_id,
                    'roll_no' => $enrollment->roll_no,
                    'name_en' => $student->name_en,
                    'photo_url' => $student->photoUrl(),
                    'status' => $records->get($enrollment->id)?->status->value,
                ];
            })->all(),
        ];
    }
}
