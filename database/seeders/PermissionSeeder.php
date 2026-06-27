<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * The full granular permission list. Endpoints always check these,
     * never role names.
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        'branch.manage',
        'session.manage',
        'class.manage',
        'subject.manage',
        'teacher.view',
        'teacher.create',
        'teacher.update',
        'admission.view',
        'admission.approve',
        'admission.delete',
        'student.view',
        'student.create',
        'student.update',
        'student.delete',
        'parent.manage',
        'attendance.create',
        'attendance.update',
        'attendance.view',
        'teacher_attendance.view',
        'teacher_attendance.manage',
        'exam.view',
        'exam.manage',
        'marks.entry',
        'marks.view',
        'result.generate',
        'result.view',
        'promotion.view',
        'promotion.execute',
        'promotion.override',
        'fee.manage',
        'fee.collect',
        'invoice.view',
        'income.manage',
        'expense.manage',
        'asset.manage',
        'idcard.generate',
        'tc.issue',
        'tc.view',
        'report.view',
        'setting.manage',
        'role.manage',
    ];

    /**
     * Seed every permission. Safe to re-run.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission);
        }
    }
}
