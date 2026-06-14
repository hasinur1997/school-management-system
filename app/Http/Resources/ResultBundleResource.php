<?php

namespace App\Http\Resources;

use App\Models\ExamResult;
use App\Models\Mark;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A student's full result bundle for one enrollment: the student header, each
 * per-exam result with its subject marks, and the annual result. GPAs, marks
 * and grade points are decimal-cast, so they serialize as fixed-precision
 * strings ("4.50", "78.50", "4.00"); is_passed/published are real booleans.
 *
 * Wraps the array ResultService::bundle() returns.
 */
class ResultBundleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $enrollment = $this->resource['enrollment'];
        $student = $enrollment->student;
        $marksByExam = $this->resource['marks_by_exam'];
        $annual = $this->resource['annual'];

        return [
            'student' => [
                'id' => $student->id,
                'name_en' => $student->name_en,
                'admission_no' => $student->admission_no,
                'class' => $enrollment->schoolClass?->name,
                'section' => $enrollment->section?->name,
                'roll_no' => $enrollment->roll_no,
            ],
            'exams' => $this->resource['exam_results']->map(function (ExamResult $result) use ($marksByExam): array {
                $marks = $marksByExam->get($result->exam_id) ?? collect();

                return [
                    'type' => $result->exam->type->value,
                    'published' => $result->published_at !== null,
                    'gpa' => $result->gpa,
                    'grade' => $result->grade,
                    'is_passed' => $result->is_passed,
                    'subjects' => $marks->map(fn (Mark $mark): array => [
                        'name' => $mark->subject->name,
                        'obtained_marks' => $mark->obtained_marks,
                        'grade' => $mark->grade,
                        'grade_point' => $mark->grade_point,
                    ])->values()->all(),
                ];
            })->all(),
            'annual' => $annual === null ? null : [
                'first_semester_gpa' => $annual->first_semester_gpa,
                'second_semester_gpa' => $annual->second_semester_gpa,
                'final_exam_gpa' => $annual->final_exam_gpa,
                'annual_gpa' => $annual->annual_gpa,
                'grade' => $annual->grade,
                'is_passed' => $annual->is_passed,
                'published' => $annual->published_at !== null,
            ],
        ];
    }
}
