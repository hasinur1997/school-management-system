<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\SchoolClass\StoreClassRequest;
use App\Http\Requests\SchoolClass\UpdateClassRequest;
use App\Http\Resources\ClassResource;
use App\Models\SchoolClass;
use App\Services\ClassService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends ApiController
{
    public function __construct(private readonly ClassService $classes) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $classes = $this->classes->listClasses(
            $request->user(),
            $request->filled('branch_id') ? $request->integer('branch_id') : null,
        );

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
    public function show(Request $request, SchoolClass $class): JsonResponse
    {
        $this->classes->assertClassVisibleTo($class, $request->user());

        $class->load(['sections' => fn ($query) => $query->orderBy('name')]);

        return $this->success(ClassResource::make($class));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateClassRequest $request, SchoolClass $class): JsonResponse
    {
        $this->classes->assertClassVisibleTo($class, $request->user());

        $class = $this->classes->updateClass($class, $request->validated());

        return $this->success(ClassResource::make($class->load('sections')), 'Class updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, SchoolClass $class): JsonResponse
    {
        $this->classes->assertClassVisibleTo($class, $request->user());

        try {
            $this->classes->deleteClass($class);
        } catch (QueryException) {
            return $this->error('Class is in use and cannot be deleted', 409);
        }

        return $this->success(null, 'Class deleted');
    }
}
