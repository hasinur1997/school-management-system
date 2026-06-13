<?php

namespace App\Http\Requests\TeacherAssignment;

class StoreTeacherAssignmentRequest extends TeacherAssignmentRequest
{
    /**
     * Nothing to ignore when creating.
     */
    protected function ignoredId(): ?int
    {
        return null;
    }
}
