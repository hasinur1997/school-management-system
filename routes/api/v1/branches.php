<?php

use App\Http\Controllers\Api\V1\BranchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:branch.manage'])->group(function () {
    Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
    Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
    Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
});
