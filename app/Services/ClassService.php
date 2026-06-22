<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ClassService
{
    public function __construct(private readonly AcademicStructureService $structure) {}

    /**
     * List active classes with their sections, ordered by level, served
     * from the academic-structure cache.
     *
     * @return Collection<int, SchoolClass>
     */
    public function listClasses(User $user, ?int $branchId = null): Collection
    {
        return $this->structure->listClasses($user, $branchId);
    }

    /**
     * List the sections of a class, ordered by name.
     *
     * @return Collection<int, Section>
     */
    public function listSections(SchoolClass $class): Collection
    {
        return $class->sections()->with('schoolClass')->orderBy('name')->get();
    }

    /**
     * Create a class in the given branch.
     *
     * @param  array<string, mixed>  $data
     */
    public function createClass(array $data, int $branchId): SchoolClass
    {
        $class = SchoolClass::create([...$data, 'branch_id' => $branchId]);

        $this->structure->forgetClassLists($class->branch_id);

        return $class->load(['branch', 'sections.schoolClass']);
    }

    /**
     * Update a class; its branch never changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateClass(SchoolClass $class, array $data): SchoolClass
    {
        $class->update($data);

        $this->structure->forgetClassLists($class->branch_id);

        return $class->load(['branch', 'sections.schoolClass']);
    }

    /**
     * Delete a class. A restrict FK violation propagates as QueryException.
     */
    public function deleteClass(SchoolClass $class): void
    {
        $class->delete();

        $this->structure->forgetClassLists($class->branch_id);
    }

    /**
     * Create a section under the given class.
     *
     * @param  array<string, mixed>  $data
     */
    public function createSection(SchoolClass $class, array $data): Section
    {
        $section = $class->sections()->create($data);

        $this->structure->forgetClassLists($class->branch_id);

        return $section->load('schoolClass');
    }

    /**
     * Update a section; its class never changes.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSection(Section $section, array $data): Section
    {
        $section->update($data);

        $this->structure->forgetClassLists($section->loadMissing('schoolClass')->schoolClass->branch_id);

        return $section->load('schoolClass');
    }

    /**
     * Delete a section. A restrict FK violation propagates as QueryException.
     */
    public function deleteSection(Section $section): void
    {
        $section->loadMissing('schoolClass');

        $section->delete();

        $this->structure->forgetClassLists($section->schoolClass->branch_id);
    }
}
