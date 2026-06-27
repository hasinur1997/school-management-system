<?php

use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Trash and bulk routes sit before {teacher} so literal paths win over
    // public-id route binding. Gated on teacher.delete.
    Route::middleware('permission:teacher.delete')->group(function () {
        Route::get('teachers/trash', [TeacherController::class, 'trash'])
            ->name('teachers.trash');
        Route::post('teachers/bulk-delete', [TeacherController::class, 'bulkDestroy'])
            ->name('teachers.bulk-destroy');
        Route::post('teachers/bulk-restore', [TeacherController::class, 'bulkRestore'])
            ->name('teachers.bulk-restore');
        Route::post('teachers/bulk-force-delete', [TeacherController::class, 'bulkForceDestroy'])
            ->name('teachers.bulk-force-destroy');
        Route::delete('teachers/{teacher}', [TeacherController::class, 'destroy'])
            ->name('teachers.destroy');
        Route::post('teachers/{teacher}/restore', [TeacherController::class, 'restore'])
            ->withTrashed()
            ->name('teachers.restore');
        Route::delete('teachers/{teacher}/force', [TeacherController::class, 'forceDestroy'])
            ->withTrashed()
            ->name('teachers.force-destroy');
    });

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
