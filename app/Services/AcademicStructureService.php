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
     * Super admin filtering convention: BranchScope does not constrain
     * super admins, so they pass an explicit `branch_id` to narrow to one
     * branch; null (`all` or an omitted filter) returns every branch.
     * Everyone else is constrained by BranchScope and the filter is ignored.
     *
     * @return Collection<int, SchoolClass>
     */
    public function listClasses(User $user, ?int $branchId = null): Collection
    {
        // A branchless non-super-admin sees nothing; skip the cache so the
        // empty result can never land under the cross-branch "all" key.
        if (! $user->isSuperAdmin() && $user->branch_id === null) {
            return new Collection;
        }

        $filter = $user->isSuperAdmin() ? $branchId : null;

        return Cache::remember(
            $this->classListKey($user->isSuperAdmin() ? $filter : $user->branch_id),
            now()->addHour(),
            fn (): Collection => SchoolClass::query()
                ->where('is_active', true)
                ->when($filter !== null, fn (Builder $query) => $query->where('branch_id', $filter))
                ->with(['branch', 'sections' => fn ($query) => $query->with('schoolClass')->orderBy('name')])
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
            fn (): Collection => $class->subjects()->with('schoolClass')->orderBy('name')->get(),
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

        return $subject->load('schoolClass');
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

        return $subject->load('schoolClass');
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
