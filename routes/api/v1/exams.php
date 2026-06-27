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

    // Bulk delete (declared before the {exam} routes so the literal path wins).
    Route::post('exams/bulk-delete', [ExamController::class, 'bulkDestroy'])
        ->middleware('permission:exam.manage')
        ->name('exams.bulk-destroy');

    Route::put('exams/{exam}', [ExamController::class, 'update'])
        ->middleware('permission:exam.manage')
        ->name('exams.update');

    Route::delete('exams/{exam}', [ExamController::class, 'destroy'])
        ->middleware('permission:exam.manage')
        ->name('exams.destroy');
});
