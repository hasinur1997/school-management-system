<?php

namespace App\Http\Resources;

use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TeacherAssignment
 */
class TeacherAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Nested class/section/subject names are eager loaded by the service; a
     * null subject means a class duty. The nested teacher object lands in
     * Task 2.1 when the teachers table exists.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'teacher_id' => $this->teacher_id,
            'session_id' => $this->session_id,
            'class_id' => $this->class_id,
            'section_id' => $this->section_id,
            'subject_id' => $this->subject_id,
            'teacher' => $this->whenLoaded('teacher', fn (): array => [
                'id' => $this->teacher->id,
                'name' => $this->teacher->name,
            ]),
            'class' => $this->whenLoaded('schoolClass', fn (): array => [
                'id' => $this->schoolClass->id,
                'name' => $this->schoolClass->name,
            ]),
            'session' => $this->whenLoaded('session', fn (): array => [
                'id' => $this->session->id,
                'name' => $this->session->name,
            ]),
            'section' => $this->whenLoaded('section', fn () => $this->section === null ? null : [
                'id' => $this->section->id,
                'name' => $this->section->name,
            ]),
            'subject' => $this->whenLoaded('subject', fn () => $this->subject === null ? null : [
                'id' => $this->subject->id,
                'name' => $this->subject->name,
            ]),
        ];
    }
}
