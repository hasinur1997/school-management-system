<?php

namespace App\Http\Resources;

use App\Models\Teacher;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Teacher
 */
class TeacherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * photo_url resolves from the medialibrary photo collection (null when
     * unset). assignments is only present on show (eager loaded for the
     * current session) and carries each class/section/subject duty.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'user_id' => $this->whenLoaded('user', fn () => $this->user->public_id),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'designation' => $this->designation,
            'joining_date' => $this->joining_date?->toDateString(),
            'status' => $this->status->value,
            'photo_url' => $this->photoUrl(),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn (TeacherAssignment $assignment): array => [
                'class' => $assignment->schoolClass === null ? null : [
                    'id' => $assignment->schoolClass->public_id,
                    'name' => $assignment->schoolClass->name,
                ],
                'section' => $assignment->section === null ? null : [
                    'id' => $assignment->section->public_id,
                    'name' => $assignment->section->name,
                ],
                'subject' => $assignment->subject === null ? null : [
                    'id' => $assignment->subject->public_id,
                    'name' => $assignment->subject->name,
                ],
            ])->all()),
        ];
    }
}
