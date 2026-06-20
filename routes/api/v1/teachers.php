<?php

use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('teachers', [TeacherController::class, 'index'])
        ->middleware('permission:teacher.view')
        ->name('teachers.index');

    Route::get('teachers/{teacher}', [TeacherController::class, 'show'])
        ->middleware('permission:teacher.view')
        ->name('teachers.show');

    Route::post('teachers', [TeacherController::class, 'store'])
        ->middleware('permission:teacher.create')
        ->name('teachers.store');

    Route::put('teachers/{teacher}', [TeacherController::class, 'update'])
        ->middleware('permission:teacher.update')
        ->name('teachers.update');

    Route::patch('teachers/{teacher}/status', [TeacherController::class, 'updateStatus'])
        ->middleware('permission:teacher.update')
        ->name('teachers.status');

    Route::post('teachers/{teacher}/photo', [TeacherController::class, 'photo'])
        ->middleware('permission:teacher.update')
        ->name('teachers.photo');

    Route::post('teachers/{teacher}/resend-credentials', [TeacherController::class, 'resendCredentials'])
        ->middleware('permission:teacher.create')
        ->name('teachers.resend-credentials');
});
