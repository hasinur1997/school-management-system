<?php

use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\SubjectController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Reads are open to every authenticated user (dropdown data).
    Route::get('classes', [ClassController::class, 'index'])->name('classes.index');
    Route::get('classes/{class}', [ClassController::class, 'show'])->name('classes.show');
    Route::get('classes/{class}/sections', [SectionController::class, 'index'])->name('classes.sections.index');
    Route::get('sections/{section}', [SectionController::class, 'show'])->name('sections.show');
    Route::get('classes/{class}/subjects', [SubjectController::class, 'index'])->name('classes.subjects.index');
    Route::get('subjects/{subject}', [SubjectController::class, 'show'])->name('subjects.show');

    Route::middleware('permission:class.manage')->group(function () {
        Route::post('classes', [ClassController::class, 'store'])->name('classes.store');
        Route::put('classes/{class}', [ClassController::class, 'update'])->name('classes.update');
        Route::delete('classes/{class}', [ClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('classes/{class}/sections', [SectionController::class, 'store'])->name('classes.sections.store');
        Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
        Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');
    });

    Route::middleware('permission:subject.manage')->group(function () {
        Route::post('classes/{class}/subjects', [SubjectController::class, 'store'])->name('classes.subjects.store');
        Route::put('subjects/{subject}', [SubjectController::class, 'update'])->name('subjects.update');
        Route::delete('subjects/{subject}', [SubjectController::class, 'destroy'])->name('subjects.destroy');
    });
});
