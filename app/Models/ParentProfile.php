<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\HasPublicId;
use Database\Factories\ParentProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A parent/guardian profile attached one-to-one to a login (users row) and
 * linked many-to-many to students via parent_student. Named ParentProfile to
 * avoid clashing with the PHP `parent` keyword; the underlying table is
 * `parents`. Branch is stamped automatically via BelongsToBranch.
 */
#[Fillable(['user_id', 'name', 'phone', 'relation'])]
class ParentProfile extends Model
{
    /** @use HasFactory<ParentProfileFactory> */
    use BelongsToBranch, HasFactory, HasPublicId, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'parents';

    /**
     * Get the login the parent authenticates with.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the students linked to this parent.
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'parent_student', 'parent_id', 'student_id');
    }

    /**
     * Whether the given student is linked to this parent. The project-wide
     * gate every "parent sees own children" endpoint (attendance/results/fees)
     * reuses. The students relation carries BranchScope, so a cross-branch
     * student id is never reported as linked.
     */
    public function isLinkedTo(int $studentId): bool
    {
        return $this->students()->whereKey($studentId)->exists();
    }
}
