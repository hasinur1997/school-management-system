<?php

namespace App\Http\Resources;

use App\Models\Mark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public semester-result view. It intentionally omits internal/public ids and
 * unpublished result state; callers only receive the published GPA and subject
 * marks for an exact roll/class/year/semester match.
 */
class PublicResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->resource['enrollment'];
        $student = $enrollment->student;
        $examResult = $this->resource['exam_result'];

        return [
            'student_information' => [
                'roll_no' => $enrollment->roll_no,
                'student_name' => $student->name_en,
                'father_name' => $student->father_name_en,
                'mother_name' => $student->mother_name_en,
                'class' => $enrollment->schoolClass?->name,
                'section' => $enrollment->section?->name,
                'session' => $enrollment->session?->name,
                'semester' => $examResult->exam->type->value,
                'date_of_birth' => $student->date_of_birth?->toDateString(),
                'result' => $examResult->gpa,
            ],
            'subjects' => $this->resource['marks']->map(fn (Mark $mark): array => [
                'subject_code' => $mark->subject->code,
                'subject_name' => $mark->subject->name,
                'marks' => $mark->obtained_marks,
                'grade' => $mark->grade,
            ])->values()->all(),
        ];
    }
}
