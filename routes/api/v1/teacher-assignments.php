<?php

use App\Http\Controllers\Api\V1\TeacherAssignmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:teacher.update'])->group(function () {
    Route::get('teacher-assignments', [TeacherAssignmentController::class, 'index'])->name('teacher-assignments.index');
    Route::post('teacher-assignments', [TeacherAssignmentController::class, 'store'])->name('teacher-assignments.store');
    Route::get('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'show'])->name('teacher-assignments.show');
    Route::put('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'update'])->name('teacher-assignments.update');
    Route::delete('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy'])->name('teacher-assignments.destroy');
});
