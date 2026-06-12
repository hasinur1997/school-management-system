<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ClassService
{
    /**
     * List active classes with their sections, ordered by level, cached
     * per branch (academic structure changes rarely but is read on every
     * dropdown).
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
            $this->listCacheKey($branchId),
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
     * List the sections of a class, ordered by name.
     *
     * @return Collection<int, Section>
     */
    public function listSections(SchoolClass $class): Collection
    {
        return $class->sections()->orderBy('name')->get();
    }

    /**
     * Ensure a class belongs to the caller's branch until BranchScope lands.
     */
    public function assertClassVisibleTo(SchoolClass $class, User $user): SchoolClass
    {
        if (! $user->isSuperAdmin() && $class->branch_id !== $user->branch_id) {
            abort(404, 'Resource not found.');
        }

        return $class;
    }

    /**
     * Ensure a section's parent class belongs to the caller's branch.
     */
    public function assertSectionVisibleTo(Section $section, User $user): Section
    {
        $section->loadMissing('schoolClass');

        $this->assertClassVisibleTo($section->schoolClass, $user);

        return $section;
    }

    /**
     * Create a class in the given branch.
     *
     * @param  array<string, mixed>  $data
     */
    public function createClass(array $data, int $branchId): SchoolClass
    {
        $class = SchoolClass::create([...$data, 'branch_id' => $branchId]);

        $this->forgetListCache($class->branch_id);

        return $class;
    }

    /**
     * Update a class; its branch never changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateClass(SchoolClass $class, array $data): SchoolClass
    {
        $class->update($data);

        $this->forgetListCache($class->branch_id);

        return $class;
    }

    /**
     * Delete a class. A restrict FK violation propagates as QueryException.
     */
    public function deleteClass(SchoolClass $class): void
    {
        $class->delete();

        $this->forgetListCache($class->branch_id);
    }

    /**
     * Create a section under the given class.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSection(SchoolClass $class, array $data): Section
    {
        $section = $class->sections()->create($data);

        $this->forgetListCache($class->branch_id);

        return $section;
    }

    /**
     * Update a section; its class never changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSection(Section $section, array $data): Section
    {
        $section->update($data);

        $this->forgetListCache($section->loadMissing('schoolClass')->schoolClass->branch_id);

        return $section;
    }

    /**
     * Delete a section. A restrict FK violation propagates as QueryException.
     */
    public function deleteSection(Section $section): void
    {
        $section->loadMissing('schoolClass');

        $section->delete();

        $this->forgetListCache($section->schoolClass->branch_id);
    }

    /**
     * Drop the cached class list for the branch and the cross-branch list.
     */
    private function forgetListCache(int $branchId): void
    {
        Cache::forget($this->listCacheKey($branchId));
        Cache::forget($this->listCacheKey(null));
    }

    private function listCacheKey(?int $branchId): string
    {
        return 'academic.classes.'.($branchId ?? 'all');
    }
}
