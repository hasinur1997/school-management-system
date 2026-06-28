<?php

use App\Http\Controllers\Api\V1\MarkController;
use Illuminate\Support\Facades\Route;

// Marks: per-subject entry sheet + bulk save (marks.entry) and browse
// (marks.view). Marks are branch-scoped through the enrollment and the
// {exam} binding 404s out-of-branch via BranchScope.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('exams/{exam}/marks/sheet', [MarkController::class, 'sheet'])
        ->middleware('permission:marks.entry')
        ->name('exams.marks.sheet');

    Route::get('exams/{exam}/marks/matrix', [MarkController::class, 'matrix'])
        ->middleware('permission:marks.entry')
        ->name('exams.marks.matrix');

    Route::post('exams/{exam}/marks', [MarkController::class, 'store'])
        ->middleware('permission:marks.entry')
        ->name('exams.marks.store');

    Route::post('exams/{exam}/marks/publish', [MarkController::class, 'publish'])
        ->middleware('permission:marks.entry')
        ->name('exams.marks.publish');

    Route::post('exams/{exam}/marks/unpublish', [MarkController::class, 'unpublish'])
        ->middleware('permission:marks.entry')
        ->name('exams.marks.unpublish');

    Route::get('exams/{exam}/marks', [MarkController::class, 'index'])
        ->middleware('permission:marks.view')
        ->name('exams.marks.index');
});
