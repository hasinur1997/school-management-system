<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Mark\ListMarksRequest;
use App\Http\Requests\Mark\MarkSheetRequest;
use App\Http\Requests\Mark\StoreMarksRequest;
use App\Http\Resources\MarkResource;
use App\Http\Resources\MarkSheetResource;
use App\Models\Exam;
use App\Services\MarkService;
use Illuminate\Http\JsonResponse;

class MarkController extends ApiController
{
    public function __construct(private readonly MarkService $marks) {}

    /**
     * Return the marks entry sheet for one subject+section of an exam: the
     * active roster in roll order with any marks already entered.
     */
    public function sheet(MarkSheetRequest $request, Exam $exam): JsonResponse
    {
        $sheet = $this->marks->sheet(
            $exam,
            $request->integer('subject_id'),
            $request->integer('section_id'),
        );

        return $this->success(MarkSheetResource::make($sheet));
    }

    /**
     * Bulk-save marks for one subject of an exam (idempotent upsert with grade
     * snapshots).
     */
    public function store(StoreMarksRequest $request, Exam $exam): JsonResponse
    {
        $saved = $this->marks->saveBulk(
            $exam,
            $request->integer('subject_id'),
            $request->validated('marks'),
            $request->user(),
        );

        return $this->success(['saved' => $saved]);
    }

    /**
     * Display a paginated, filterable listing of an exam's marks.
     */
    public function index(ListMarksRequest $request, Exam $exam): JsonResponse
    {
        $marks = $this->marks->list(
            $exam,
            $request->only(['subject_id', 'section_id']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => MarkResource::collection($marks)->resolve($request),
            'meta' => [
                'current_page' => $marks->currentPage(),
                'per_page' => $marks->perPage(),
                'total' => $marks->total(),
                'last_page' => $marks->lastPage(),
            ],
        ]);
    }
}
