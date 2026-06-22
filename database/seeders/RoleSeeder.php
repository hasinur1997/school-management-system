<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Roles and their permission bundles. `super_admin` holds no explicit
     * permissions — it bypasses every check via Gate::before. Students and
     * parents hold no staff permissions; their access comes from policies
     * on their own records.
     *
     * @var array<string, list<string>>
     */
    public const ROLES = [
        'super_admin' => [],
        'admin' => [
            'session.manage',
            'class.manage',
            'subject.manage',
            'teacher.view',
            'teacher.create',
            'teacher.update',
            'admission.view',
            'admission.approve',
            'student.view',
            'student.update',
            'parent.manage',
            'attendance.create',
            'attendance.update',
            'attendance.view',
            'teacher_attendance.view',
            'teacher_attendance.manage',
            'exam.view',
            'exam.manage',
            'marks.view',
            'result.generate',
            'result.view',
            'promotion.view',
            'promotion.execute',
            'promotion.override',
            'fee.manage',
            'fee.collect',
            'invoice.view',
            'idcard.generate',
            'tc.issue',
            'tc.view',
            'report.view',
        ],
        'accountant' => [
            'income.manage',
            'expense.manage',
            'asset.manage',
            'fee.collect',
            'invoice.view',
            'report.view',
        ],
        'teacher' => [
            'attendance.create',
            'attendance.view',
            'marks.entry',
            'marks.view',
            'result.view',
            'student.view',
        ],
        'student' => [],
        'parent' => [],
    ];

    /**
     * Seed the six roles with their permission bundles. Safe to re-run.
     * Requires PermissionSeeder to have run first.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role)->syncPermissions($permissions);
        }
    }
}
