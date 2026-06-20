<?php

use App\Http\Controllers\Api\V1\ExamController;
use Illuminate\Support\Facades\Route;

// Exams: branch-scoped CRUD. Reads need exam.view, writes exam.manage;
// out-of-branch {exam} bindings 404 via BranchScope.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('exams', [ExamController::class, 'index'])
        ->middleware('permission:exam.view')
        ->name('exams.index');

    Route::get('exams/{exam}', [ExamController::class, 'show'])
        ->middleware('permission:exam.view')
        ->name('exams.show');

    Route::post('exams', [ExamController::class, 'store'])
        ->middleware('permission:exam.manage')
        ->name('exams.store');

    Route::put('exams/{exam}', [ExamController::class, 'update'])
        ->middleware('permission:exam.manage')
        ->name('exams.update');
});
