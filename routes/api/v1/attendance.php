<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\CheckinIpController;
use App\Http\Controllers\Api\V1\TeacherAttendanceController;
use Illuminate\Support\Facades\Route;

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

// Monthly attendance reads. studentMonthly authorizes via
// StudentPolicy::viewAttendance in the controller (staff/self/linked
// parent, 404 hiding); meMonthly is role-gated to the student itself.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('students/{student}/attendance', [AttendanceController::class, 'studentMonthly'])->name('students.attendance');
    Route::get('me/attendance', [AttendanceController::class, 'meMonthly'])->name('me.attendance');
});

// Teacher self check-in / check-out. Teacher role only; the request IP is
// matched against the branch whitelist in the service.
Route::middleware(['auth:sanctum', 'role:teacher'])->group(function () {
    Route::post('teacher-attendance/check-in', [TeacherAttendanceController::class, 'checkIn'])->name('teacher-attendance.check-in');
    Route::post('teacher-attendance/check-out', [TeacherAttendanceController::class, 'checkOut'])->name('teacher-attendance.check-out');
    Route::get('me/teacher-attendance', [TeacherAttendanceController::class, 'me'])->name('me.teacher-attendance');
});

// Admin browse + correction. Records are branch-scoped through the teacher,
// so out-of-branch {teacherAttendance} bindings 404 automatically.
Route::middleware('auth:sanctum')->group(function () {
    Route::get('teacher-attendance', [TeacherAttendanceController::class, 'index'])
        ->middleware('permission:teacher_attendance.view')
        ->name('teacher-attendance.index');

    Route::put('teacher-attendance/{teacherAttendance}', [TeacherAttendanceController::class, 'update'])
        ->middleware('permission:teacher_attendance.manage')
        ->name('teacher-attendance.update');
});

// Teacher check-in IP whitelist management. Entries are branch-scoped via
// BranchScope, so out-of-branch {checkinIp} bindings 404 automatically.
Route::middleware(['auth:sanctum', 'permission:teacher_attendance.manage'])->group(function () {
    Route::get('checkin-ips', [CheckinIpController::class, 'index'])->name('checkin-ips.index');
    Route::post('checkin-ips', [CheckinIpController::class, 'store'])->name('checkin-ips.store');
    Route::put('checkin-ips/{checkinIp}', [CheckinIpController::class, 'update'])->name('checkin-ips.update');
    Route::delete('checkin-ips/{checkinIp}', [CheckinIpController::class, 'destroy'])->name('checkin-ips.destroy');
});
