<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Owns the academic-structure read cache (classes with sections, subjects).
 * These reads back dropdowns on almost every screen while writes are rare,
 * so reads are cached and every write — here or in ClassService — forgets
 * the affected keys.
 */
class AcademicStructureService
{
    /**
     * List active classes with their sections, ordered by level, cached
     * per branch.
     *
     * Branch filtering is explicit here until the BranchScope global scope
     * lands in Task 1.7. Super admins see every branch and may narrow to one.
     *
     * @return Collection<int, SchoolClass>
     */
    public function listClasses(User $user, ?int $branchId = null): Collection
    {
        $branchId = $user->isSuperAdmin() ? $branchId : $user->branch_id;

        return Cache::remember(
            $this->classListKey($branchId),
            now()->addHour(),
            fn (): Collection => SchoolClass::query()
                ->where('is_active', true)
                ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->with(['sections' => fn ($query) => $query->orderBy('name')])
                ->orderBy('numeric_level')
                ->get(),
        );
    }

    /**
     * List the subjects of a class, ordered by name, cached per class.
     *
     * @return Collection<int, Subject>
     */
    public function listSubjects(SchoolClass $class): Collection
    {
        return Cache::remember(
            $this->subjectListKey($class->id),
            now()->addHour(),
            fn (): Collection => $class->subjects()->orderBy('name')->get(),
        );
    }

    /**
     * Create a subject under the given class.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSubject(SchoolClass $class, array $data): Subject
    {
        $subject = $class->subjects()->create($data);

        $this->forgetSubjectList($class->id);

        return $subject;
    }

    /**
     * Update a subject; its class never changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSubject(Subject $subject, array $data): Subject
    {
        $subject->update($data);

        $this->forgetSubjectList($subject->class_id);

        return $subject;
    }

    /**
     * Delete a subject. A restrict FK violation propagates as QueryException.
     */
    public function deleteSubject(Subject $subject): void
    {
        $subject->delete();

        $this->forgetSubjectList($subject->class_id);
    }

    /**
     * Drop the cached class list for the branch and the cross-branch list.
     */
    public function forgetClassLists(int $branchId): void
    {
        Cache::forget($this->classListKey($branchId));
        Cache::forget($this->classListKey(null));
    }

    /**
     * Drop the cached subject list for the class.
     */
    public function forgetSubjectList(int $classId): void
    {
        Cache::forget($this->subjectListKey($classId));
    }

    private function classListKey(?int $branchId): string
    {
        return 'academic.classes.'.($branchId ?? 'all');
    }

    private function subjectListKey(int $classId): string
    {
        return 'academic.subjects.'.$classId;
    }
}
