<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Result\ListExamResultsRequest;
use App\Http\Resources\ExamResultResource;
use App\Models\Exam;
use App\Services\ResultService;
use Illuminate\Http\JsonResponse;

class ResultController extends ApiController
{
    public function __construct(private readonly ResultService $results) {}

    /**
     * (Re)generate the exam's results for every enrollment with a complete set
     * of marks, reporting any skipped enrollments and their missing subjects.
     */
    public function generate(Exam $exam): JsonResponse
    {
        $result = $this->results->generateExamResults($exam);

        return $this->success($result, 'Results generated');
    }

    /**
     * Freeze the exam's results: stamp published_at and move the exam to
     * published status.
     */
    public function publish(Exam $exam): JsonResponse
    {
        $result = $this->results->publishExamResults($exam);

        return $this->success($result, 'Results published');
    }

    /**
     * Display a paginated, filterable listing of an exam's results, ordered by
     * GPA descending.
     */
    public function index(ListExamResultsRequest $request, Exam $exam): JsonResponse
    {
        $filters = $request->only(['section_id']);

        if ($request->has('is_passed')) {
            $filters['is_passed'] = $request->boolean('is_passed');
        }

        $results = $this->results->listExamResults(
            $exam,
            $filters,
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => ExamResultResource::collection($results)->resolve($request),
            'meta' => [
                'current_page' => $results->currentPage(),
                'per_page' => $results->perPage(),
                'total' => $results->total(),
                'last_page' => $results->lastPage(),
            ],
        ]);
    }
}
