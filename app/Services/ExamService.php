<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ExamService
{
    /**
     * Browse exams in the caller's branch (scope is automatic via branch_id),
     * filtered by session/class/type/status.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return Exam::query()
            ->with(['session', 'schoolClass'])
            ->when(isset($filters['session_id']), fn (Builder $query) => $query->where('session_id', $filters['session_id']))
            ->when(isset($filters['class_id']), fn (Builder $query) => $query->where('class_id', $filters['class_id']))
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create an exam. The branch is taken from the (already branch-validated)
     * class — a class belongs to exactly one branch — so super admins (who
     * carry no branch of their own) get a correct branch_id and the
     * BelongsToBranch stamp stays consistent for everyone else.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Exam
    {
        $class = SchoolClass::findOrFail($data['class_id']);

        return Exam::create([...$data, 'branch_id' => $class->branch_id])
            ->load(['session', 'schoolClass']);
    }

    /**
     * Update an exam's editable fields (name/dates/status). Immutability and
     * the published-freeze / status-regression guards are enforced by
     * UpdateExamRequest.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Exam $exam, array $data): Exam
    {
        $exam->fill(array_intersect_key($data, array_flip(['name', 'start_date', 'end_date', 'status'])));
        $exam->save();

        return $exam->load(['session', 'schoolClass']);
    }
}
