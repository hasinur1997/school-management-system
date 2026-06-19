# Task 15.1 — Access Control Management (Roles & Permissions API)

| Field | Value |
|---|---|
| Phase | 15 — Access Control Management |
| Status | `done` |
| Depends on | 1.2 (roles/permissions seeded), 2.1 (users exist) |
| Blocks | Frontend task F-6.6 (access control UI) |
| Spec references | `docs/api/access-control.md`, `docs/tasks/task-1.2-roles-permissions.md` |
| Estimated size | One sitting |

## Background
Task 1.2 installed spatie/laravel-permission and seeded six roles + the full permission list, but explicitly deferred any management API ("super admin manages via seeders for Phase 1; CRUD UI endpoints can be raised as an open question"). There is currently **no way** to assign a role to a user or change which permissions a role grants except by editing seeders. This task exposes that management surface so a super admin can do both from the app. Authorization elsewhere stays permission-based; this module only edits the bundles those `permission:` middleware checks already read.

## Objective
Add read + sync endpoints for permissions, roles, and user-role assignment, gated by a new super-admin-only `role.manage` permission, with lockout and immutability guards.

## What To Implement
1. **New permission `role.manage`** — append to `PermissionSeeder`'s list. Assign it to **no** role bundle (super admin passes via the existing `Gate::before`), exactly like `setting.manage`. Seeder stays idempotent.
2. **Permission registry source** — read the assignable list from spatie (`Permission::all()`); group by the substring before the first `.` for `GET /permissions`. A human `label` (e.g. `branch.manage` → "Branch manage") via `Str::headline`.
3. **Controllers (thin)** + services:
   - `RoleController` → `index`, `show`, `syncPermissions` backed by `RoleService`.
   - `PermissionController` → `index`.
   - `UserController` → `index`, `syncRoles` backed by `UserAccessService` (or extend an existing user service if present).
4. **Resources**: `RoleResource` (`id, name, is_protected, users_count, permissions[]`), `PermissionGroupResource`, `UserAccountResource` (`id, name, email, phone, is_active, roles[]`).
5. **Form Requests**: `SyncRolePermissionsRequest` (`permissions` = `array`, each `string|exists in registry`), `SyncUserRolesRequest` (`roles` = `array`, each `in:super_admin,admin,accountant,teacher,student,parent`). Authorization in `authorize()` is the route middleware's job; requests shape-validate only.
6. **Routes** under `auth:sanctum` + `permission:role.manage`:
   `GET /permissions`, `GET /roles`, `GET /roles/{id}`, `PUT /roles/{id}/permissions`, `GET /users`, `PUT /users/{id}/roles`.
7. **Guards (in the services, not the controllers):**
   - Editing the `super_admin` role's permissions → `abort(403, 'The super admin role cannot be modified')`.
   - Removing `super_admin` from the last active super admin → `ValidationException::withMessages(['roles' => 'At least one super admin is required'])` (422).
   - Flush spatie's permission cache after any sync so effective permissions update immediately.
8. `GET /users` is paginated (default 15, max 100), branch-scoped automatically (no manual `where('branch_id')`), with `?search=` and `?role=` filters.

## API Contract
Full request/response shapes, error codes, and the protected-role / last-super-admin rules are in **`docs/api/access-control.md`** — implement exactly against it. Envelope `{ success, message, data }` everywhere; `meta` on the paginated `GET /users`.

## Success Criteria
- [ ] `role.manage` seeded, in no bundle; super admin reaches every endpoint, a plain admin gets 403
- [ ] `GET /permissions` returns the full registry grouped by module
- [ ] `GET /roles` lists six roles with `permissions[]`, `users_count`, `is_protected` (super_admin only)
- [ ] `PUT /roles/{id}/permissions` syncs; unknown permission → 422; editing super_admin → 403
- [ ] `PUT /users/{id}/roles` syncs; unknown role → 422; stripping the last super admin → 422; unknown user → 404
- [ ] `GET /users` paginated + `search`/`role` filters, branch-scoped
- [ ] `php artisan test` green

## Required Tests
1. `role.manage` super-admin bypass; admin denied 403 on each route
2. permissions grouped output shape
3. role list shape (users_count, is_protected, permissions)
4. role permission sync happy path → effective permissions of an assigned user change; unknown permission 422; super_admin role 403
5. user role sync happy path; unknown role 422; last-super-admin lockout 422; unknown user 404
6. users list pagination + search + role filter; branch scoping (non-super sees own branch only)

## Out of Scope
Creating/deleting custom roles or permissions (registry + six roles stay seeder-defined) · per-user direct permissions (assignment is role-based only) · audit log of who changed what (raise separately if needed).

## Completion Protocol
Set Status `done`, tick 15.1 on the board, add `role.manage` note + any surprises to the Decisions Log.
