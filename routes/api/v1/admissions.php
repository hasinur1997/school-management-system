<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use Illuminate\Support\Facades\Route;

// Trash lifecycle (soft delete). Declared before the {admission} routes so the
// literal `trash` / `bulk-*` paths win over the public-id binding. Gated on
// admission.delete; out-of-branch ids 404/skip via BranchScope.
Route::middleware(['auth:sanctum', 'permission:admission.delete'])->group(function () {
    Route::get('admissions/trash', [AdmissionController::class, 'trash'])->name('admissions.trash');

    Route::post('admissions/bulk-delete', [AdmissionController::class, 'bulkDestroy'])->name('admissions.bulk-destroy');
    Route::post('admissions/bulk-restore', [AdmissionController::class, 'bulkRestore'])->name('admissions.bulk-restore');
    Route::post('admissions/bulk-force-delete', [AdmissionController::class, 'bulkForceDestroy'])->name('admissions.bulk-force-destroy');

    Route::delete('admissions/{admission}', [AdmissionController::class, 'destroy'])->name('admissions.destroy');

    // Restore + permanent delete operate on trashed rows, so bind withTrashed.
    Route::post('admissions/{admission}/restore', [AdmissionController::class, 'restore'])
        ->withTrashed()
        ->name('admissions.restore');
    Route::delete('admissions/{admission}/force', [AdmissionController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('admissions.force-destroy');
});

// Route::middleware(['auth:sanctum', 'permission:admission.view'])->group(function () {
Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
// });

Route::middleware(['auth:sanctum', 'permission:admission.approve'])->group(function () {
    Route::post('admissions/{admission}/approve', [AdmissionController::class, 'approve'])->name('admissions.approve');
    Route::post('admissions/{admission}/reject', [AdmissionController::class, 'reject'])->name('admissions.reject');
});
