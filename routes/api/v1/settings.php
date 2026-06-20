<?php

use App\Http\Controllers\Api\V1\SettingController;
use Illuminate\Support\Facades\Route;

// Settings (14.1): global + per-branch key/value store. Secrets are
// write-only (masked on read). Super admins target another branch via
// branch_id; the cache is invalidated on every write.
Route::middleware(['auth:sanctum', 'permission:setting.manage'])->group(function () {
    Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
});
