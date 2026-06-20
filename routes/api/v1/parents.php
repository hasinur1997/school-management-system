<?php

use App\Http\Controllers\Api\V1\ParentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:parent.manage'])->group(function () {
    Route::get('parents', [ParentController::class, 'index'])->name('parents.index');
    Route::post('parents', [ParentController::class, 'store'])->name('parents.store');
    Route::post('parents/{parent}/students', [ParentController::class, 'linkStudent'])->name('parents.students.link');
    Route::delete('parents/{parent}/students/{student}', [ParentController::class, 'unlinkStudent'])->name('parents.students.unlink');
});

// Parent self-service: linked children. Role-gated in the controller
// (parents hold no staff permissions).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('me/students', [ParentController::class, 'meStudents'])->name('me.students');
});
