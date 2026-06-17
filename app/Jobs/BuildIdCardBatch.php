<?php

namespace App\Jobs;

use App\Enums\IdCardBatchStatus;
use App\Models\IdCardBatch;
use App\Services\IdCardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Builds a class's merged ID card PDF off-request (the no-bulk-PDF-in-request
 * rule). Idempotent: re-running simply re-renders the same cohort and overwrites
 * the stored file, so a retry after a partial failure is safe.
 */
class BuildIdCardBatch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The seconds to wait before retrying, escalating per attempt.
     *
     * @var array<int, int>
     */
    public array $backoff = [60, 300];

    public function __construct(public readonly IdCardBatch $batch) {}

    /**
     * Execute the job: render and store the merged PDF, marking the batch done.
     */
    public function handle(IdCardService $idCards): void
    {
        $idCards->buildBatch($this->batch);
    }

    /**
     * Mark the batch failed once all attempts are exhausted so the poll endpoint
     * can report it instead of leaving the caller polling forever.
     */
    public function failed(Throwable $exception): void
    {
        $this->batch->update([
            'status' => IdCardBatchStatus::Failed,
            'error' => 'ID card batch generation failed',
        ]);
    }
}
