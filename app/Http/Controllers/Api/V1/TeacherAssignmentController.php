<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\TeacherAssignment\ListTeacherAssignmentsRequest;
use App\Http\Requests\TeacherAssignment\StoreTeacherAssignmentRequest;
use App\Http\Requests\TeacherAssignment\UpdateTeacherAssignmentRequest;
use App\Http\Resources\TeacherAssignmentResource;
use App\Models\TeacherAssignment;
use App\Services\TeacherAssignmentService;
use Illuminate\Http\JsonResponse;

class TeacherAssignmentController extends ApiController
{
    public function __construct(private readonly TeacherAssignmentService $assignments) {}

    /**
     * Display a listing of the resource.
     */
    public function index(ListTeacherAssignmentsRequest $request): JsonResponse
    {
        $assignments = $this->assignments->list(
            $request->only(['teacher_id', 'class_id', 'session_id']),
            $request->integer('per_page', 15),
        );

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => TeacherAssignmentResource::collection($assignments)->resolve($request),
            'meta' => [
                'current_page' => $assignments->currentPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
                'last_page' => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeacherAssignmentRequest $request): JsonResponse
    {
        $assignment = $this->assignments->create($request->validated());

        return $this->success(TeacherAssignmentResource::make($assignment), 'Teacher assignment created', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TeacherAssignment $teacherAssignment): JsonResponse
    {
        return $this->success(TeacherAssignmentResource::make($this->assignments->loadRelations($teacherAssignment)));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeacherAssignmentRequest $request, TeacherAssignment $teacherAssignment): JsonResponse
    {
        $assignment = $this->assignments->update($teacherAssignment, $request->validated());

        return $this->success(TeacherAssignmentResource::make($assignment), 'Teacher assignment updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TeacherAssignment $teacherAssignment): JsonResponse
    {
        $this->assignments->delete($teacherAssignment);

        return $this->success(null, 'Teacher assignment deleted');
    }
}
