<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Section\StoreSectionRequest;
use App\Http\Requests\Section\UpdateSectionRequest;
use App\Http\Resources\SectionResource;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Services\ClassService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

class SectionController extends ApiController
{
    public function __construct(private readonly ClassService $classes) {}

    /**
     * Display a listing of the resource.
     */
    public function index(SchoolClass $class): JsonResponse
    {
        return $this->success(SectionResource::collection($this->classes->listSections($class)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSectionRequest $request, SchoolClass $class): JsonResponse
    {
        $section = $this->classes->createSection($class, $request->validated());

        return $this->success(SectionResource::make($section), 'Section created', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Section $section): JsonResponse
    {
        return $this->success(SectionResource::make($section));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSectionRequest $request, Section $section): JsonResponse
    {
        $section = $this->classes->updateSection($section, $request->validated());

        return $this->success(SectionResource::make($section), 'Section updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Section $section): JsonResponse
    {
        try {
            $this->classes->deleteSection($section);
        } catch (QueryException) {
            return $this->error('Section is in use and cannot be deleted', 409);
        }

        return $this->success(null, 'Section deleted');
    }
}
