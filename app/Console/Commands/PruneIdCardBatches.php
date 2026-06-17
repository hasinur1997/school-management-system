<?php

namespace App\Console\Commands;

use App\Models\IdCardBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Prunes ID card batches older than 7 days, deleting both the stored merged PDF
 * and the tracking row. Runs daily from the scheduler — batches are transient,
 * re-buildable on demand, so keeping them indefinitely just wastes disk. No auth
 * context here, so the BranchScope is inert and all branches are swept.
 */
class PruneIdCardBatches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'idcards:prune-batches {--days=7 : Age in days beyond which batches are pruned}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete ID card batches (and their PDFs) older than the retention window';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));
        $disk = Storage::disk('local');
        $pruned = 0;

        IdCardBatch::query()
            ->where('created_at', '<', $cutoff)
            ->chunkById(500, function ($batches) use ($disk, &$pruned): void {
                foreach ($batches as $batch) {
                    if ($batch->file_path !== null) {
                        $disk->delete($batch->file_path);
                    }

                    $batch->delete();
                    $pruned++;
                }
            });

        $this->info("Pruned {$pruned} ID card batch(es).");

        return self::SUCCESS;
    }
}
