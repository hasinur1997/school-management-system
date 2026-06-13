<?php

namespace App\Http\Requests\TeacherAssignment;

use App\Models\TeacherAssignment;

class UpdateTeacherAssignmentRequest extends TeacherAssignmentRequest
{
    /**
     * Exclude the assignment being updated from the duplicate-tuple check.
     */
    protected function ignoredId(): ?int
    {
        /** @var TeacherAssignment $assignment */
        $assignment = $this->route('teacherAssignment');

        return $assignment->id;
    }
}
