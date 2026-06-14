<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Result\AnnualResultRequest;
use App\Services\AnnualResultService;
use Illuminate\Http\JsonResponse;

class AnnualResultController extends ApiController
{
    public function __construct(private readonly AnnualResultService $results) {}

    /**
     * (Re)generate the annual results for a (session, class), reporting any
     * enrollments skipped for missing per-exam results. Requires all three
     * exams of the tuple to be published.
     */
    public function generate(AnnualResultRequest $request): JsonResponse
    {
        $result = $this->results->generate(
            $request->integer('session_id'),
            $request->integer('class_id'),
        );

        return $this->success($result, 'Annual results generated');
    }

    /**
     * Freeze the (session, class) annual results: stamp published_at on every
     * row.
     */
    public function publish(AnnualResultRequest $request): JsonResponse
    {
        $result = $this->results->publish(
            $request->integer('session_id'),
            $request->integer('class_id'),
        );

        return $this->success($result, 'Annual results published');
    }
}
