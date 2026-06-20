<?php

use App\Http\Controllers\Api\V1\AssetController;
use Illuminate\Support\Facades\Route;

// Assets (11.4): asset register CRUD plus an at-a-glance summary
// (total_value = in_use + damaged; disposed excluded). Guarded by
// asset.manage; out-of-branch {asset} bindings 404 via BranchScope.
// Filters status/search; sorts value/purchase_date.
Route::middleware(['auth:sanctum', 'permission:asset.manage'])->group(function () {
    Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
    Route::get('assets/summary', [AssetController::class, 'summary'])->name('assets.summary');
    Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
    Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
    Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
});
