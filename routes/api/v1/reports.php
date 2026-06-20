<?php

use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

// Finance reports (13.2): income / expense / profit-loss, all SQL-aggregated
// over the shared report filter (period/from/to + super-admin branch_id,
// `all` = consolidated). Guarded by report.view. Series granularity switches
// from daily to monthly at 62 days; consolidated views add a by_branch list.
Route::middleware(['auth:sanctum', 'permission:report.view'])->group(function () {
    Route::get('reports/income', [ReportController::class, 'income'])->name('reports.income');
    Route::get('reports/expense', [ReportController::class, 'expense'])->name('reports.expense');
    Route::get('reports/profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit-loss');

    // Entity reports (13.3): students / teachers / assets / fees, same
    // filter + SQL-aggregation conventions. Fees figures (invoiced from
    // invoice amounts, collected from paid_amount) reconcile with the
    // invoice/payment fixtures; asset total_value follows the 11.4 rule
    // (in_use + damaged, disposed excluded).
    Route::get('reports/students', [ReportController::class, 'students'])->name('reports.students');
    Route::get('reports/teachers', [ReportController::class, 'teachers'])->name('reports.teachers');
    Route::get('reports/assets', [ReportController::class, 'assets'])->name('reports.assets');
    Route::get('reports/fees', [ReportController::class, 'fees'])->name('reports.fees');

    // Report PDF exports (13.4): any of the seven reports as a streamed PDF
    // over the same filter contract. The {type} constraint rejects unknown
    // types with a 404; data comes from the same 13.2/13.3 services.
    Route::get('reports/{type}/pdf', [ReportController::class, 'pdf'])
        ->where('type', 'income|expense|profit-loss|students|teachers|assets|fees')
        ->name('reports.pdf');
});
