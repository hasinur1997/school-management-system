<?php

use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PublicAdmissionController;
use App\Http\Controllers\Api\V1\SettingController;
use Illuminate\Support\Facades\Route;

// Public, unauthenticated surface. Submission is rate-limited to
// 10 requests/hour/IP; both endpoints handle branches explicitly.
Route::prefix('public')->name('public.')->group(function () {
    Route::post('admissions', [PublicAdmissionController::class, 'store'])
        ->middleware('throttle:10,60')
        ->name('admissions.store');

    Route::get('admissions/{application_no}/status', [PublicAdmissionController::class, 'status'])
        ->name('admissions.status');

    // Safe subset for the public admission page: school name, logo URL,
    // active branches and their open classes. Never exposes secrets.
    Route::get('settings', [SettingController::class, 'publicSettings'])->name('settings');
});

// SSLCommerz callbacks (10.5) — public surface, no auth. The IPN is the
// server-to-server source of truth (idempotent settlement); the landing
// routes are browser redirects that only report the payment's status and
// change no state. Paths must match the callback URLs built in
// SslCommerzGateway (/api/v1/payments/sslcommerz/*).
Route::prefix('payments/sslcommerz')->name('payments.sslcommerz.')->group(function () {
    Route::post('ipn', [PaymentController::class, 'ipn'])->name('ipn');

    Route::get('{result}', [PaymentController::class, 'landing'])
        ->whereIn('result', ['success', 'fail', 'cancel'])
        ->name('landing');
});
