<?php

use App\Http\Controllers\Api\V1\FeeStructureController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use Illuminate\Support\Facades\Route;

// Fee structures (10.1): the monthly fee per (branch, session, class) that
// invoices copy at generation time. CRUD guarded by fee.manage; no DELETE
// (history matters). Out-of-branch {fee_structure} bindings 404 via
// BranchScope; fee structures carry their own branch_id so list/show are
// branch-isolated automatically.
Route::middleware(['auth:sanctum', 'permission:fee.manage'])->group(function () {
    Route::get('fee-structures', [FeeStructureController::class, 'index'])
        ->name('fee-structures.index');

    Route::post('fee-structures', [FeeStructureController::class, 'store'])
        ->name('fee-structures.store');

    Route::put('fee-structures/{feeStructure}', [FeeStructureController::class, 'update'])
        ->name('fee-structures.update');
});

// Invoices (10.2): monthly fee invoices generated from the class fee
// structure. Manual generation is fee.manage; it normally runs from the
// scheduler on the 1st. The list is staff-only (invoice.view). Show carries
// no permission middleware — it authorizes via StudentPolicy::viewInvoices
// (staff/self/linked parent, 404 hiding) so students/parents, who hold no
// invoice.view, can read their own. /me/invoices is student/parent
// self-service. Out-of-branch {invoice} ids 404 via BranchScope.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('invoices/generate', [InvoiceController::class, 'generate'])
        ->middleware('permission:fee.manage')
        ->name('invoices.generate');

    Route::get('invoices', [InvoiceController::class, 'index'])
        ->middleware('permission:invoice.view')
        ->name('invoices.index');

    Route::get('me/invoices', [InvoiceController::class, 'me'])
        ->name('me.invoices');

    Route::get('invoices/{id}', [InvoiceController::class, 'show'])
        ->name('invoices.show');
});

// Payments (10.3): counter (cash) collection settles an invoice through the
// PaymentService pipeline (payment → invoice → income → receipt_no), guarded
// by fee.collect; out-of-branch {invoice} ids 404 via BranchScope binding.
// The receipt PDF carries no permission middleware — it authorizes via
// StudentPolicy::viewInvoices (staff/self/linked parent, 404 hiding) and only
// for a paid payment; out-of-branch {id} 404s via BranchScope.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('invoices/{invoice}/payments/local', [PaymentController::class, 'local'])
        ->middleware('permission:fee.collect')
        ->name('payments.local');

    // Online checkout init (10.4): no permission middleware — students and
    // linked parents initiate too; StudentPolicy::payOnline authorizes
    // (staff fee.collect / self / linked parent, 404 hiding).
    Route::post('invoices/{invoice}/payments/online', [PaymentController::class, 'online'])
        ->name('payments.online');

    Route::get('payments/{id}/receipt', [PaymentController::class, 'receipt'])
        ->name('payments.receipt');
});
