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
use Illuminate\Http\Request;

class SectionController extends ApiController
{
    public function __construct(private readonly ClassService $classes) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, SchoolClass $class): JsonResponse
    {
        $this->classes->assertClassVisibleTo($class, $request->user());

        return $this->success(SectionResource::collection($this->classes->listSections($class)));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSectionRequest $request, SchoolClass $class): JsonResponse
    {
        $this->classes->assertClassVisibleTo($class, $request->user());

        $section = $this->classes->createSection($class, $request->validated());

        return $this->success(SectionResource::make($section), 'Section created', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Section $section): JsonResponse
    {
        $this->classes->assertSectionVisibleTo($section, $request->user());

        return $this->success(SectionResource::make($section));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateSectionRequest $request, Section $section): JsonResponse
    {
        $this->classes->assertSectionVisibleTo($section, $request->user());

        $section = $this->classes->updateSection($section, $request->validated());

        return $this->success(SectionResource::make($section), 'Section updated');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Section $section): JsonResponse
    {
        $this->classes->assertSectionVisibleTo($section, $request->user());

        try {
            $this->classes->deleteSection($section);
        } catch (QueryException) {
            return $this->error('Section is in use and cannot be deleted', 409);
        }

        return $this->success(null, 'Section deleted');
    }
}
