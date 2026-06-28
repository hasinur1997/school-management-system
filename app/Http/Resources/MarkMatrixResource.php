<?php

namespace App\Http\Resources;

use App\Models\Enrollment;
use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The multi-subject marks matrix: the exam (with its lock/published status), the
 * grid's subject columns (each with its marking bounds), and the roster of
 * active students in roll order. Every student carries one mark cell per
 * subject — the mark already entered (with its absent flag), or null when none
 * yet. The client previews totals/GPA from the server-resolved grade points but
 * never invents grades.
 *
 * Wraps the array MarkService::matrix() returns.
 */
class MarkMatrixResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $marks = $this->resource['marks'];

        return [
            'exam' => [
                'id' => $this->resource['exam']->public_id,
                'name' => $this->resource['exam']->name,
                'status' => $this->resource['exam']->status->value,
            ],
            'subjects' => $this->resource['subjects']->map(fn (Subject $subject): array => [
                'id' => $subject->public_id,
                'code' => $subject->code,
                'name' => $subject->name,
                'full_marks' => $subject->full_marks,
                'pass_marks' => $subject->pass_marks,
            ])->all(),
            'students' => $this->resource['enrollments']->map(function (Enrollment $enrollment) use ($marks): array {
                return [
                    'enrollment_id' => $enrollment->public_id,
                    'roll_no' => $enrollment->roll_no,
                    'name_en' => $enrollment->student->name_en,
                    'sid' => $enrollment->student->admission_no,
                    'marks' => $this->resource['subjects']->map(function (Subject $subject) use ($marks, $enrollment): array {
                        $mark = $marks->get($enrollment->id.':'.$subject->id);

                        return [
                            'subject_id' => $subject->public_id,
                            'obtained_marks' => $mark !== null ? (float) $mark->obtained_marks : null,
                            'is_absent' => $mark !== null ? (bool) $mark->is_absent : false,
                        ];
                    })->all(),
                ];
            })->all(),
        ];
    }
}
