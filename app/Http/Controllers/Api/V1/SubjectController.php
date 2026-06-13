<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Subject\StoreSubjectRequest;
use App\Http\Requests\Subject\UpdateSubjectRequest;
use App\Http\Resources\SubjectResource;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\AcademicStructureService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class SubjectController extends ApiController
{
    public function __construct(private readonly AcademicStructureService $structure) {}

    /**
     * Display a listing of the resource.
     */
    public function index(SchoolClass $class): JsonResponse
    {
        return $this->success(SubjectResource::collection($this->structure->listSubjects($class)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSubjectRequest $request, SchoolClass $class): JsonResponse
    {
        $subject = $this->structure->createSubject($class, $request->validated());

        return $this->success(SubjectResource::make($subject), 'Subject created', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Subject $subject): JsonResponse
    {
        return $this->success(SubjectResource::make($subject));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $subject = $this->structure->updateSubject($subject, $request->validated());

        return $this->success(SubjectResource::make($subject), 'Subject updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Subject $subject): JsonResponse
    {
        try {
            $this->structure->deleteSubject($subject);
        } catch (QueryException) {
            return $this->error('Subject is in use and cannot be deleted', 409);
        }

        return $this->success(null, 'Subject deleted');
    }
}
