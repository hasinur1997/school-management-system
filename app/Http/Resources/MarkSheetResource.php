<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The marks entry sheet: the exam, the subject's full/pass marks, and the
 * roster of active students in roll order. Each student's `obtained_marks` is
 * the mark already entered for this exam+subject, or null when none yet.
 *
 * Wraps the array MarkService::sheet() returns.
 */
class MarkSheetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $marks = $this->resource['marks'];
        $subject = $this->resource['subject'];

        return [
            'exam' => [
                'id' => $this->resource['exam']->id,
                'name' => $this->resource['exam']->name,
            ],
            'subject' => [
                'full_marks' => $subject->full_marks,
                'pass_marks' => $subject->pass_marks,
            ],
            'students' => $this->resource['enrollments']->map(function (Enrollment $enrollment) use ($marks): array {
                $mark = $marks->get($enrollment->id);

                return [
                    'enrollment_id' => $enrollment->id,
                    'roll_no' => $enrollment->roll_no,
                    'name_en' => $enrollment->student->name_en,
                    'obtained_marks' => $mark !== null ? (float) $mark->obtained_marks : null,
                ];
            })->all(),
        ];
    }
}
