<?php

namespace App\Console\Commands;

use App\Services\InvoiceService;
use Illuminate\Console\Command;

/**
 * Generates the monthly fee invoices for all active students. Runs from the
 * scheduler on the 1st (no arguments → current month) and is also available
 * manually. Idempotent: re-running for a period skips invoices that already
 * exist rather than duplicating them.
 */
class GenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:generate {month? : 1-12, defaults to current} {year? : defaults to current}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly fee invoices for active students';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceService $invoices): int
    {
        $month = (int) ($this->argument('month') ?? now()->month);
        $year = (int) ($this->argument('year') ?? now()->year);

        if ($month < 1 || $month > 12) {
            $this->error('Month must be between 1 and 12.');

            return self::FAILURE;
        }

        $result = $invoices->generate($month, $year);

        $this->info(sprintf(
            'Invoices for %02d/%d — created: %d, skipped existing: %d, missing fee structure: %d.',
            $month,
            $year,
            $result['created'],
            $result['skipped_existing'],
            count($result['missing_fee_structure']),
        ));

        foreach ($result['missing_fee_structure'] as $missing) {
            $this->warn("No fee structure for class {$missing['class_id']} — its students were skipped.");
        }

        return self::SUCCESS;
    }
}
