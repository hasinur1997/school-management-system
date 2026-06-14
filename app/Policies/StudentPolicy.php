<?php

namespace App\Policies;

use App\Models\ParentProfile;
use App\Models\Student;
use App\Models\User;

/**
 * Record-level access for student profiles — the template every personal-data
 * module (attendance, results, fees) reuses: staff with the relevant view
 * permission see any student in their branch, a student sees only their own
 * record, and a parent sees only their linked children. Branch isolation is
 * enforced upstream by the global scope, so a cross-branch student never
 * reaches this policy (it 404s at route binding).
 */
class StudentPolicy
{
    /**
     * A user may view a student if they hold student.view, if they are that
     * student, or if they are a parent linked to that student. (Super admins
     * bypass via Gate::before.)
     */
    public function view(User $user, Student $student): bool
    {
        if ($user->can('student.view') || $user->id === $student->user_id) {
            return true;
        }

        return ParentProfile::where('user_id', $user->id)
            ->first()
            ?->isLinkedTo($student->id) ?? false;
    }

    /**
     * A user may view a student's attendance if they hold attendance.view, if
     * they are that student, or if they are a parent linked to that student.
     * (Super admins bypass via Gate::before.)
     */
    public function viewAttendance(User $user, Student $student): bool
    {
        if ($user->can('attendance.view') || $user->id === $student->user_id) {
            return true;
        }

        return ParentProfile::where('user_id', $user->id)
            ->first()
            ?->isLinkedTo($student->id) ?? false;
    }

    /**
     * A user may view a student's results if they hold result.view (staff, who
     * also see unpublished previews), if they are that student, or if they are a
     * parent linked to that student. (Super admins bypass via Gate::before.)
     */
    public function viewResults(User $user, Student $student): bool
    {
        if ($user->can('result.view') || $user->id === $student->user_id) {
            return true;
        }

        return ParentProfile::where('user_id', $user->id)
            ->first()
            ?->isLinkedTo($student->id) ?? false;
    }

    /**
     * A user may view a student's invoices if they hold invoice.view (staff), if
     * they are that student, or if they are a parent linked to that student.
     * (Super admins bypass via Gate::before.)
     */
    public function viewInvoices(User $user, Student $student): bool
    {
        if ($user->can('invoice.view') || $user->id === $student->user_id) {
            return true;
        }

        return ParentProfile::where('user_id', $user->id)
            ->first()
            ?->isLinkedTo($student->id) ?? false;
    }
}
