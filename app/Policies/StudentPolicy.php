<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

/**
 * Record-level access for student profiles — the template every personal-data
 * module (attendance, results, fees) reuses: staff with the relevant view
 * permission see any student in their branch, while a student sees only their
 * own record. Branch isolation is enforced upstream by the global scope, so a
 * cross-branch student never reaches this policy (it 404s at route binding).
 */
class StudentPolicy
{
    /**
     * A user may view a student if they hold student.view, or if they are that
     * student. (Super admins bypass via Gate::before.)
     */
    public function view(User $user, Student $student): bool
    {
        return $user->can('student.view') || $user->id === $student->user_id;
    }
}
