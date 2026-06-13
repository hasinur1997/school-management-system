<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\ClassController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\PublicAdmissionController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\SessionController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\SubjectController;
use App\Http\Controllers\Api\V1\TeacherAssignmentController;
use App\Http\Controllers\Api\V1\TeacherController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    // Public, unauthenticated surface. Submission is rate-limited to
    // 10 requests/hour/IP; both endpoints handle branches explicitly.
    Route::prefix('public')->name('public.')->group(function () {
        Route::post('admissions', [PublicAdmissionController::class, 'store'])
            ->middleware('throttle:10,60')
            ->name('admissions.store');

        Route::get('admissions/{application_no}/status', [PublicAdmissionController::class, 'status'])
            ->name('admissions.status');
    });

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
        });
    });

    Route::middleware(['auth:sanctum', 'permission:session.manage'])->group(function () {
        Route::get('sessions', [SessionController::class, 'index'])->name('sessions.index');
        Route::post('sessions', [SessionController::class, 'store'])->name('sessions.store');
        Route::get('sessions/{session}', [SessionController::class, 'show'])->name('sessions.show');
        Route::put('sessions/{session}', [SessionController::class, 'update'])->name('sessions.update');
        Route::delete('sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
    });

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

    Route::middleware('auth:sanctum')->group(function () {
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

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('students', [StudentController::class, 'index'])
            ->middleware('permission:student.view')
            ->name('students.index');

        // show authorizes via StudentPolicy::view — staff or the student itself.
        Route::get('students/{student}', [StudentController::class, 'show'])
            ->name('students.show');

        // enrollments authorizes via StudentPolicy::view — staff, the student, or a linked parent.
        Route::get('students/{student}/enrollments', [StudentController::class, 'enrollments'])
            ->name('students.enrollments');

        Route::put('students/{student}', [StudentController::class, 'update'])
            ->middleware('permission:student.update')
            ->name('students.update');

        Route::patch('students/{student}/status', [StudentController::class, 'updateStatus'])
            ->middleware('permission:student.update')
            ->name('students.status');

        Route::post('students/{student}/photo', [StudentController::class, 'photo'])
            ->middleware('permission:student.update')
            ->name('students.photo');
    });

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

    Route::middleware(['auth:sanctum', 'permission:admission.view'])->group(function () {
        Route::get('admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
    });

    Route::middleware(['auth:sanctum', 'permission:admission.approve'])->group(function () {
        Route::post('admissions/{admission}/approve', [AdmissionController::class, 'approve'])->name('admissions.approve');
        Route::post('admissions/{admission}/reject', [AdmissionController::class, 'reject'])->name('admissions.reject');
    });

    Route::middleware(['auth:sanctum', 'permission:teacher.update'])->group(function () {
        Route::get('teacher-assignments', [TeacherAssignmentController::class, 'index'])->name('teacher-assignments.index');
        Route::post('teacher-assignments', [TeacherAssignmentController::class, 'store'])->name('teacher-assignments.store');
        Route::get('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'show'])->name('teacher-assignments.show');
        Route::put('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'update'])->name('teacher-assignments.update');
        Route::delete('teacher-assignments/{teacherAssignment}', [TeacherAssignmentController::class, 'destroy'])->name('teacher-assignments.destroy');
    });

    Route::middleware(['auth:sanctum', 'permission:attendance.create'])->group(function () {
        Route::get('attendance/sheet', [AttendanceController::class, 'sheet'])->name('attendance.sheet');
        Route::post('attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    });

    Route::middleware(['auth:sanctum', 'permission:attendance.view'])->group(function () {
        Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    });

    Route::middleware(['auth:sanctum', 'permission:attendance.update'])->group(function () {
        Route::put('attendance/{attendance}', [AttendanceController::class, 'update'])->name('attendance.update');
    });

    Route::middleware(['auth:sanctum', 'permission:branch.manage'])->group(function () {
        Route::get('branches', [BranchController::class, 'index'])->name('branches.index');
        Route::post('branches', [BranchController::class, 'store'])->name('branches.store');
        Route::get('branches/{branch}', [BranchController::class, 'show'])->name('branches.show');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->name('branches.destroy');
    });
});
