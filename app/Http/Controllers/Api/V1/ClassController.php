<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\SchoolClass\ListClassesRequest;
use App\Http\Requests\SchoolClass\StoreClassRequest;
use App\Http\Requests\SchoolClass\UpdateClassRequest;
use App\Http\Resources\ClassResource;
use App\Models\SchoolClass;
use App\Services\ClassService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class ClassController extends ApiController
{
    public function __construct(private readonly ClassService $classes) {}

    /**
     * Display a listing of the resource.
     */
    public function index(ListClassesRequest $request): JsonResponse
    {
        $classes = $this->classes->listClasses($request->user(), $request->branchFilter());

        return $this->success(ClassResource::collection($classes));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreClassRequest $request): JsonResponse
    {
        $class = $this->classes->createClass(
            $request->safe()->except(['branch_id']),
            $request->targetBranchId(),
        );

        return $this->success(ClassResource::make($class->load('sections')), 'Class created', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SchoolClass $class): JsonResponse
    {
        $class->load(['sections' => fn ($query) => $query->orderBy('name')]);

        return $this->success(ClassResource::make($class));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClassRequest $request, SchoolClass $class): JsonResponse
    {
        $class = $this->classes->updateClass($class, $request->validated());

        return $this->success(ClassResource::make($class->load('sections')), 'Class updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SchoolClass $class): JsonResponse
    {
        try {
            $this->classes->deleteClass($class);
        } catch (QueryException) {
            return $this->error('Class is in use and cannot be deleted', 409);
        }

        return $this->success(null, 'Class deleted');
    }
}
