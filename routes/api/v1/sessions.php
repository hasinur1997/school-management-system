<?php

use App\Http\Controllers\Api\V1\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:session.manage'])->group(function () {
    Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::post('sessions', [SessionController::class, 'store'])->name('sessions.store');
    Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
    Route::put('sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
    Route::delete('sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
});
