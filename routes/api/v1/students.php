<?php

use App\Http\Controllers\Api\V1\IdCardController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\TransferCertificateController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('students', [StudentController::class, 'index'])
        ->middleware('permission:student.view')
        ->name('students.index');

    // Direct student creation (office path) — distinct from admission approval.
    Route::post('students', [StudentController::class, 'store'])
        ->middleware('permission:student.create')
        ->name('students.store');

    // show authorizes via StudentPolicy::view — staff or the student itself.
    Route::get('students/{student}', [StudentController::class, 'show'])
        ->name('students.show');

    // enrollments authorizes via StudentPolicy::view — staff, the student, or a linked parent.
    Route::get('students/{student}/enrollments', [StudentController::class, 'enrollments'])
        ->name('students.enrollments');

    // Edit one enrollment row (class history). student.update gates it; the
    // controller confirms the enrollment belongs to the in-branch student.
    Route::put('students/{student}/enrollments/{enrollment}', [StudentController::class, 'updateEnrollment'])
        ->middleware('permission:student.update')
        ->name('students.enrollments.update');

    Route::put('students/{student}', [StudentController::class, 'update'])
        ->middleware('permission:student.update')
        ->name('students.update');

    Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])
        ->middleware('permission:student.update')
        ->name('students.status');

    Route::post('students/{student}/photo', [StudentController::class, 'photo'])
        ->middleware('permission:student.update')
        ->name('students.photo');

    // Single ID card PDF (12.1): streamed on demand from live enrollment
    // data — no table. Out-of-branch {student} ids 404 via BranchScope.
    Route::get('students/{student}/id-card', [IdCardController::class, 'show'])
        ->middleware('permission:idcard.generate')
        ->name('students.id-card');

    // Batch ID cards (12.2): queued merged-PDF build + poll + download.
    // Foreign {batch} ids 404 via BranchScope route binding.
    Route::middleware('permission:idcard.generate')->group(function () {
        Route::post('id-cards/batch', [IdCardController::class, 'batch'])
            ->name('id-cards.batch');
        Route::get('id-cards/batch/{batch}', [IdCardController::class, 'batchStatus'])
            ->name('id-cards.batch.status');
        Route::get('id-cards/batch/{batch}/download', [IdCardController::class, 'download'])
            ->name('id-cards.batch.download');
    });

    // Transfer certificates (12.3): issuing retires a student (status → tc)
    // and stores the one persisted legal PDF. Out-of-branch {student}/{tc}
    // ids 404 via BranchScope binding.
    Route::post('students/{student}/tc', [TransferCertificateController::class, 'store'])
        ->middleware('permission:tc.issue')
        ->name('students.tc');

    Route::middleware('permission:tc.view')->group(function () {
        Route::get('tcs', [TransferCertificateController::class, 'index'])->name('tcs.index');
        Route::get('tcs/{tc}', [TransferCertificateController::class, 'show'])->name('tcs.show');
        Route::get('tcs/{tc}/pdf', [TransferCertificateController::class, 'pdf'])->name('tcs.pdf');
    });
});
