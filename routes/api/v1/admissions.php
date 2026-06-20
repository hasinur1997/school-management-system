<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:admission.view'])->group(function () {
    Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
    Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
});

Route::middleware(['auth:sanctum', 'permission:admission.approve'])->group(function () {
    Route::post('admissions/{admission}/approve', [AdmissionController::class, 'approve'])->name('admissions.approve');
    Route::post('admissions/{admission}/reject', [AdmissionController::class, 'reject'])->name('admissions.reject');
});
