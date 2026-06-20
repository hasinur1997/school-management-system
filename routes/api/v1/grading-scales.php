<?php

use App\Http\Controllers\Api\V1\GradingScaleController;
use Illuminate\Support\Facades\Route;

// Grading scale: a single global scale. Reads are open to any authenticated
// user (cached); the full-replace write requires setting.manage.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('grading-scales', [GradingScaleController::class, 'index'])->name('grading-scales.index');

    Route::put('grading-scales', [GradingScaleController::class, 'update'])
        ->middleware('permission:setting.manage')
        ->name('grading-scales.update');
});
