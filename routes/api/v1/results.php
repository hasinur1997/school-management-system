<?php

use App\Http\Controllers\Api\V1\AnnualResultController;
use App\Http\Controllers\Api\V1\ResultController;
use Illuminate\Support\Facades\Route;

// Per-exam results: generate/publish (result.generate) and browse
// (result.view). Results are branch-scoped through the enrollment and the
// {exam} binding 404s out-of-branch via BranchScope.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('exams/{exam}/results/generate', [ResultController::class, 'generate'])
        ->middleware('permission:result.generate')
        ->name('exams.results.generate');

    Route::post('exams/{exam}/results/publish', [ResultController::class, 'publish'])
        ->middleware('permission:result.generate')
        ->name('exams.results.publish');

    Route::get('exams/{exam}/results', [ResultController::class, 'index'])
        ->middleware('permission:result.view')
        ->name('exams.results.index');
});

// Annual results: 25/25/50 weighted generate/publish for a (session, class)
// tuple (result.generate). Branch isolation comes through the class (the
// tuple is validated branch-scoped) and the enrollment chain.
Route::middleware(['auth:sanctum', 'permission:result.generate'])->group(function () {
    Route::post('annual-results/generate', [AnnualResultController::class, 'generate'])
        ->name('annual-results.generate');

    Route::post('annual-results/publish', [AnnualResultController::class, 'publish'])
        ->name('annual-results.publish');
});

// Result reads (8.3). Search is staff-only (result.view, staff see
// unpublished flagged). The enrollment + me reads carry no permission
// middleware: enrollmentResults authorizes via StudentPolicy::viewResults
// in the controller (staff/self/linked parent, 404 hiding), and meResults
// is student/parent self-service — so students/parents, who hold no
// result.view, can read their own published results.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('results/search', [ResultController::class, 'search'])
        ->middleware('permission:result.view')
        ->name('results.search');

    Route::get('enrollments/{id}/results', [ResultController::class, 'enrollmentResults'])
        ->name('enrollments.results');

    Route::get('me/results', [ResultController::class, 'meResults'])
        ->name('me.results');
});
