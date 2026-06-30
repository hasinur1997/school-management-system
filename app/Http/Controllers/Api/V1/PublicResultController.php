<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Result\PublicResultRequest;
use App\Http\Resources\PublicResultResource;
use App\Services\ResultService;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated lookup for published semester results.
 */
class PublicResultController extends ApiController
{
    public function __construct(private readonly ResultService $results) {}

    /**
     * Return a published semester result by roll/class/year/semester.
     */
    public function show(PublicResultRequest $request): JsonResponse
    {
        $result = $this->results->publicResult($request->criteria());

        return $this->success(PublicResultResource::make($result));
    }
}
