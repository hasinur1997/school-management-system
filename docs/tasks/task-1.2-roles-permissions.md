# Task 1.2 — Roles & Permissions (spatie)

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `done` |
| Depends on | 1.1 |
| Blocks | Every permission-protected endpoint (all later tasks) |
| Spec references | `docs/architecture-context.md` → Auth model, `docs/api-spec.md` |
| Estimated size | One sitting |

## Background
Task 1.1 delivered authentication (identity). This task delivers authorization: the six roles and the granular permission list every later endpoint will check. Per the architecture, code always checks **permissions** (e.g. `attendance.create`), never role names; roles are just permission bundles.

## Objective
Install spatie/laravel-permission, seed all roles and permissions, give super admin a global bypass, and make `/auth/me` return real roles/permissions.

## What To Implement
1. Install spatie/laravel-permission; publish its migrations unchanged.
2. `PermissionSeeder`: create the full permission list — `branch.manage, session.manage, class.manage, subject.manage, teacher.view, teacher.create, teacher.update, admission.view, admission.approve, student.view, student.update, parent.manage, attendance.create, attendance.update, attendance.view, teacher_attendance.view, teacher_attendance.manage, exam.view, exam.manage, marks.entry, marks.view, result.generate, result.view, promotion.view, promotion.execute, promotion.override, fee.manage, fee.collect, invoice.view, income.manage, expense.manage, asset.manage, idcard.generate, tc.issue, tc.view, report.view, setting.manage`.
3. `RoleSeeder`: roles `super_admin, admin, accountant, teacher, student, parent` with sensible bundles per `project-overview.md` (admin: students/teachers/admissions/payments/idcards/promotion/tc/reports; accountant: finance + fee.collect + report.view; teacher: attendance.create/view, marks.entry/view, result.view, student.view; student/parent: no staff permissions — their access is via policies on own records).
4. `Gate::before` in `AuthServiceProvider`: super_admin role passes every check.
5. `UserResource`: populate `roles` (names) and `permissions` (effective, via spatie).
6. Register spatie middleware aliases (`permission`) for route use in later tasks.

## API Contract
No new endpoints. `GET /api/v1/auth/me` now returns:
```json
{ "success": true, "message": "OK", "data": { "id": 1, "name": "Super Admin", "roles": ["super_admin"], "permissions": ["branch.manage", "..."] } }
```
Permission failure on any future protected endpoint — 403:
```json
{ "success": false, "message": "This action is unauthorized." }
```

## Success Criteria
- [ ] Seeders create 6 roles and the full permission list; re-running seeders is idempotent
- [ ] Super admin passes any permission check without explicit assignment
- [ ] `/auth/me` returns real roles + effective permissions
- [ ] A route guarded with `permission:branch.manage` returns 403 (envelope) for a teacher, 200 for super admin
- [ ] `php artisan test` green

## Required Tests
1. seeder idempotency (run twice, counts stable)
2. super admin bypass on an arbitrary permission
3. teacher denied on `branch.manage`-guarded test route → 403 envelope
4. `/auth/me` shape includes roles + permissions arrays

## Out of Scope
Role/permission management API endpoints (super admin manages via seeders for Phase 1; CRUD UI endpoints can be raised as an open question) · policies (introduced with their modules).

## Completion Protocol
Set Status `done`, tick 1.2 on the board, log surprises in the Decisions Log.
