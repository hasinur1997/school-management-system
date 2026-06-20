<?php

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

// Dashboard (14.2): one role-aware summary endpoint — the shape of `data`
// depends on the caller's role. Authenticated only; no permission gate.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});
