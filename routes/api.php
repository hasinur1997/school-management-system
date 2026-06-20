<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    require __DIR__.'/api/v1/public.php';
    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/sessions.php';
    require __DIR__.'/api/v1/classes.php';
    require __DIR__.'/api/v1/teachers.php';
    require __DIR__.'/api/v1/students.php';
    require __DIR__.'/api/v1/parents.php';
    require __DIR__.'/api/v1/admissions.php';
    require __DIR__.'/api/v1/teacher-assignments.php';
    require __DIR__.'/api/v1/attendance.php';
    require __DIR__.'/api/v1/grading-scales.php';
    require __DIR__.'/api/v1/exams.php';
    require __DIR__.'/api/v1/marks.php';
    require __DIR__.'/api/v1/results.php';
    require __DIR__.'/api/v1/promotions.php';
    require __DIR__.'/api/v1/fees.php';
    require __DIR__.'/api/v1/accounting.php';
    require __DIR__.'/api/v1/assets.php';
    require __DIR__.'/api/v1/reports.php';
    require __DIR__.'/api/v1/dashboard.php';
    require __DIR__.'/api/v1/settings.php';
    require __DIR__.'/api/v1/access-control.php';
    require __DIR__.'/api/v1/branches.php';
});
