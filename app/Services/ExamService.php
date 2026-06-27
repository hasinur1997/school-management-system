<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Mark;
use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamService
{
    /**
     * Browse exams in the caller's branch (scope is automatic via branch_id),
     * filtered by session/class/type/status. A `class_id` filter matches exams
     * that cover that class explicitly *or* via `all_classes`.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        return Exam::query()
            ->with(['session', 'classes'])
            ->when(isset($filters['session_id']), fn (Builder $query) => $query->where('session_id', $filters['session_id']))
            ->when(
                isset($filters['class_id']),
                fn (Builder $query) => $query->where(function (Builder $q) use ($filters): void {
                    $q->whereHas('classes', fn (Builder $c) => $c->where('school_classes.id', $filters['class_id']))
                        ->orWhere('all_classes', true);
                }),
            )
            ->when(isset($filters['type']), fn (Builder $query) => $query->where('type', $filters['type']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Create an exam over a set of classes (or all of a branch's classes). The
     * branch is taken from the targeted classes — they belong to exactly one
     * branch (enforced by the request) — so super admins (who carry no branch)
     * get a correct branch_id; for an all-classes exam it comes from the caller
     * or the super-admin-supplied branch_id. `all_classes` exams carry no pivot
     * rows; the effective set is resolved dynamically (see Exam::classIds()).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Exam
    {
        $allClasses = (bool) ($data['all_classes'] ?? false);
        $classIds = $allClasses ? [] : array_values($data['class_ids'] ?? []);

        return DB::transaction(function () use ($data, $allClasses, $classIds): Exam {
            $exam = new Exam([
                'session_id' => $data['session_id'],
                'type' => $data['type'],
                'name' => $data['name'],
                'all_classes' => $allClasses,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
            ]);

            $exam->branch_id = $this->resolveBranchId($data, $classIds);
            $exam->save();

            if ($classIds !== []) {
                $exam->classes()->sync($classIds);
            }

            return $exam->load(['session', 'classes']);
        });
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

        return $exam->load(['session', 'classes']);
    }

    /**
     * Delete an exam and its dependents (marks, per-exam results, and the
     * class pivot) in one transaction. Those rows restrict deletes at the DB
     * level, so they are cleared first.
     */
    public function delete(Exam $exam): void
    {
        DB::transaction(function () use ($exam): void {
            Mark::where('exam_id', $exam->id)->delete();
            ExamResult::where('exam_id', $exam->id)->delete();
            $exam->classes()->detach();
            $exam->delete();
        });
    }

    /**
     * Delete several exams by public id, resolved branch-scoped (ids outside the
     * caller's branch resolve to nothing and are skipped). Returns the count
     * actually deleted.
     *
     * @param  list<string>  $publicIds
     */
    public function bulkDelete(array $publicIds): int
    {
        $exams = Exam::query()->whereIn('public_id', $publicIds)->get();

        foreach ($exams as $exam) {
            $this->delete($exam);
        }

        return $exams->count();
    }

    /**
     * Resolve the branch_id an exam belongs to: the targeted classes' branch
     * (all share one), otherwise the caller's branch, otherwise the explicit
     * branch_id (super admin / console).
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $classIds
     */
    private function resolveBranchId(array $data, array $classIds): int
    {
        if ($classIds !== []) {
            return (int) SchoolClass::findOrFail($classIds[0])->branch_id;
        }

        $user = Auth::user();

        if ($user !== null && ! $user->isSuperAdmin() && $user->branch_id !== null) {
            return (int) $user->branch_id;
        }

        return (int) $data['branch_id'];
    }
}
