<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Promotion\BulkPromotionRequest;
use App\Http\Requests\Promotion\PreviewPromotionRequest;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;

class PromotionController extends ApiController
{
    public function __construct(private readonly PromotionService $promotions) {}

    /**
     * Preview who will be promoted for a (session, class): eligible students,
     * those held back (with reason), and the resolved next class. Requires the
     * class's annual results to be published (409 otherwise).
     */
    public function preview(PreviewPromotionRequest $request): JsonResponse
    {
        $data = $this->promotions->preview(
            $request->integer('session_id'),
            $request->integer('class_id'),
        );

        return $this->success($data, 'OK');
    }

    /**
     * Promote a whole class for the target session: passed students advance to
     * the next class, failed students are re-enrolled in the same class, all in
     * one transaction. Requires published annual results (409) and that the
     * class is not already promoted (409). Returns promoted/held counts.
     */
    public function bulk(BulkPromotionRequest $request): JsonResponse
    {
        $data = $this->promotions->bulk(
            $request->validated(),
            $request->user(),
        );

        return $this->success($data, 'OK');
    }
}
