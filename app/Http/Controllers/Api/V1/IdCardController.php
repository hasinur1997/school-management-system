<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IdCardBatchStatus;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\IdCard\BatchIdCardRequest;
use App\Models\IdCardBatch;
use App\Models\Student;
use App\Services\IdCardService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class IdCardController extends ApiController
{
    public function __construct(private readonly IdCardService $idCards) {}

    /**
     * Stream a single student's ID card PDF (inline). Guarded by
     * idcard.generate; out-of-branch {student} ids 404 via BranchScope binding.
     * 422 when the student has no active enrollment (incl. TC / inactive).
     */
    public function show(Student $student): Response
    {
        return $this->idCards->render($student);
    }

    /**
     * Queue a whole-class (optionally single-section) batch build. Returns 202
     * with the batch id to poll; empty cohort → 422.
     */
    public function batch(BatchIdCardRequest $request): JsonResponse
    {
        $batch = $this->idCards->queueBatch(
            $request->integer('class_id'),
            $request->filled('section_id') ? $request->integer('section_id') : null,
            $request->integer('session_id'),
        );

        return $this->success(
            ['batch_id' => $batch->public_id, 'status' => $batch->status->value],
            'ID card batch queued',
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * Poll a batch's status. When done, includes the authenticated download URL;
     * when failed, the reason. Foreign batch ids 404 via BranchScope binding.
     */
    public function batchStatus(IdCardBatch $batch): JsonResponse
    {
        $data = ['status' => $batch->status->value];

        if ($batch->status === IdCardBatchStatus::Done) {
            $data['url'] = route('v1.id-cards.batch.download', $batch, absolute: false);
        } elseif ($batch->status === IdCardBatchStatus::Failed) {
            $data['message'] = $batch->error ?? 'ID card batch generation failed';
        }

        return $this->success($data);
    }

    /**
     * Stream a finished batch's merged PDF. 409 while not yet done.
     */
    public function download(IdCardBatch $batch): Response
    {
        return $this->idCards->downloadBatch($batch);
    }
}
