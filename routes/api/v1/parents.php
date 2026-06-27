<?php

use App\Http\Controllers\Api\V1\ParentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:parent.manage'])->group(function () {
    Route::get('parents/trash', [ParentController::class, 'trash'])->name('parents.trash');
    Route::post('parents/bulk-delete', [ParentController::class, 'bulkDestroy'])->name('parents.bulk-destroy');
    Route::post('parents/bulk-restore', [ParentController::class, 'bulkRestore'])->name('parents.bulk-restore');
    Route::post('parents/bulk-force-delete', [ParentController::class, 'bulkForceDestroy'])->name('parents.bulk-force-destroy');

    Route::get('parents', [ParentController::class, 'index'])->name('parents.index');
    Route::post('parents', [ParentController::class, 'store'])->name('parents.store');
    Route::post('parents/{parent}/resend-credentials', [ParentController::class, 'resendCredentials'])->name('parents.resend-credentials');
    Route::post('parents/{parent}/students', [ParentController::class, 'linkStudent'])->name('parents.students.link');
    Route::delete('parents/{parent}/students/{student}', [ParentController::class, 'unlinkStudent'])->name('parents.students.unlink');
    Route::delete('parents/{parent}', [ParentController::class, 'destroy'])->name('parents.destroy');
    Route::post('parents/{parent}/restore', [ParentController::class, 'restore'])
        ->withTrashed()
        ->name('parents.restore');
    Route::delete('parents/{parent}/force', [ParentController::class, 'forceDestroy'])
        ->withTrashed()
        ->name('parents.force-destroy');
});

// Parent self-service: linked children. Role-gated in the controller
// (parents hold no staff permissions).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me/students', [ParentController::class, 'meStudents'])->name('me.students');
});
