<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Generate every active student's monthly fee invoices on the 1st of the month.
// No arguments → the current month/year. Idempotent, so a missed/retried run is
// safe.
Schedule::command('invoices:generate')->monthlyOn(1, '00:00');

// Prune batch ID card PDFs older than 7 days — they are transient and
// re-buildable on demand, so they are not kept indefinitely.
Schedule::command('idcards:prune-batches')->dailyAt('01:00');
