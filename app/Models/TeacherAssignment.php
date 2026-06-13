<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranchThroughClass;
use Database\Factories\TeacherAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Links a teacher to what they teach this session: class, optional section,
 * optional subject. A null subject means a class duty (e.g. attendance only).
 *
 * The model carries no branch_id of its own — branch isolation derives from
 * the class via BelongsToBranchThroughClass.
 */
#[Fillable(['teacher_id', 'session_id', 'class_id', 'section_id', 'subject_id'])]
class TeacherAssignment extends Model
{
    /** @use HasFactory<TeacherAssignmentFactory> */
    use BelongsToBranchThroughClass, HasFactory;

    /**
     * Get the class the assignment belongs to. Required by
     * BelongsToBranchThroughClass for branch scoping.
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * Get the academic session of the assignment.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /**
     * Get the optional section of the assignment.
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * Get the optional subject of the assignment.
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }

    // The teacher() relation and its eager-loaded nested name land in Task 2.1
    // when the teachers table exists.
}
