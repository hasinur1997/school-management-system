<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\GradingScale\UpdateGradingScaleRequest;
use App\Http\Resources\GradingScaleResource;
use App\Services\GradeResolver;
use Illuminate\Http\JsonResponse;

class GradingScaleController extends ApiController
{
    public function __construct(private readonly GradeResolver $resolver) {}

    /**
     * Return the current grading scale (cached), highest band first.
     */
    public function index(): JsonResponse
    {
        return $this->success(
            GradingScaleResource::collection($this->resolver->all()),
        );
    }

    /**
     * Replace the entire grading scale and refresh the cache.
     */
    public function update(UpdateGradingScaleRequest $request): JsonResponse
    {
        $scale = $this->resolver->replace($request->validated('scale'));

        return $this->success(GradingScaleResource::collection($scale), 'Grading scale updated');
    }
}
