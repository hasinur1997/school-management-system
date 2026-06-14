<?php

namespace App\Services;

use App\Models\TeacherAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TeacherAssignmentService
{
    /**
     * The relations every read eager loads so Resources never lazy load.
     *
     * @var array<int, string>
     */
    private const RELATIONS = ['teacher', 'schoolClass', 'session', 'section', 'subject'];

    /**
     * List assignments, filtered by teacher/class/session, paginated.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return TeacherAssignment::query()
            ->with(self::RELATIONS)
            ->when(isset($filters['teacher_id']), fn (Builder $query) => $query->where('teacher_id', $filters['teacher_id']))
            ->when(isset($filters['class_id']), fn (Builder $query) => $query->where('class_id', $filters['class_id']))
            ->when(isset($filters['session_id']), fn (Builder $query) => $query->where('session_id', $filters['session_id']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create an assignment and return it with relations loaded.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): TeacherAssignment
    {
        $assignment = TeacherAssignment::create($data);

        return $assignment->load(self::RELATIONS);
    }

    /**
     * Update an assignment and return it with relations loaded.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(TeacherAssignment $assignment, array $data): TeacherAssignment
    {
        $assignment->update($data);

        return $assignment->load(self::RELATIONS);
    }

    /**
     * Delete an assignment. No dependents restrict it.
     */
    public function delete(TeacherAssignment $assignment): void
    {
        $assignment->delete();
    }

    /**
     * Load the standard relations onto an assignment for output.
     */
    public function loadRelations(TeacherAssignment $assignment): TeacherAssignment
    {
        return $assignment->load(self::RELATIONS);
    }
}
