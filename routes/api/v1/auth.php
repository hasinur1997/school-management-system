<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::post('photo', [AuthController::class, 'photo'])->name('photo');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
    });
});
