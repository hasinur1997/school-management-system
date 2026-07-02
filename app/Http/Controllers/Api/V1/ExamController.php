<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Exam\BulkDeleteExamsRequest;
use App\Http\Requests\Exam\ListExamsRequest;
use App\Http\Requests\Exam\StoreExamRequest;
use App\Http\Requests\Exam\UpdateExamRequest;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;

class ExamController extends ApiController
{
    public function __construct(private readonly ExamService $exams) {}

    /**
     * Browse exams in the caller's branch, filtered and paginated.
     */
    public function index(ListExamsRequest $request): JsonResponse
    {
        $exams = $this->exams->list(
            $request->withBranchFilter($request->only(['session_id', 'class_id', 'type', 'status'])),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ExamResource::collection($exams)->resolve($request),
            'meta' => [
                'current_page' => $exams->currentPage(),
                'per_page' => $exams->perPage(),
                'total' => $exams->total(),
                'last_page' => $exams->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new exam.
     */
    public function store(StoreExamRequest $request): JsonResponse
    {
        $exam = $this->exams->create($request->validated());

        return $this->success(ExamResource::make($exam), 'Exam created', 201);
    }

    /**
     * Display a single exam. Out-of-branch ids 404 via BranchScope binding.
     */
    public function show(Exam $exam): JsonResponse
    {
        return $this->success(ExamResource::make($exam->load(['session', 'classes', 'branch'])));
    }

    /**
     * Update an exam's name/dates/status.
     */
    public function update(UpdateExamRequest $request, Exam $exam): JsonResponse
    {
        $exam = $this->exams->update($exam, $request->validated());

        return $this->success(ExamResource::make($exam), 'Exam updated');
    }

    /**
     * Delete an exam (and its marks/results). Out-of-branch ids 404 via the
     * BranchScope binding.
     */
    public function destroy(Exam $exam): JsonResponse
    {
        $this->exams->delete($exam);

        return $this->success(null, 'Exam deleted');
    }

    /**
     * Delete several exams by public id (branch-scoped; foreign ids skipped).
     */
    public function bulkDestroy(BulkDeleteExamsRequest $request): JsonResponse
    {
        $deleted = $this->exams->bulkDelete($request->validated('ids'));

        return $this->success(['deleted' => $deleted], 'Exams deleted');
    }
}
