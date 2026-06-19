# Access Control API — Phase 15

Role & permission **management** (not just consumption). Lets a super admin see every role's permission bundle, edit which permissions a role grants, and assign roles to user accounts. Authorization stays permission-based everywhere else; this module only edits the bundles those checks read.

Backend task 1.2 deferred these endpoints ("super admin manages via seeders for Phase 1; CRUD UI endpoints can be raised as an open question"). This module delivers them.

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /permissions | role.manage | All assignable permissions, grouped by module |
| GET | /roles | role.manage | List roles with their permissions + user counts |
| GET | /roles/{id} | role.manage | Single role with its permissions |
| PUT | /roles/{id}/permissions | role.manage | Replace (sync) the permission set on a role |
| GET | /users | role.manage | List user accounts with their roles (for assignment) |
| PUT | /users/{id}/roles | role.manage | Replace (sync) the role set on a user |

`role.manage` is **super-admin-only** — seeded but assigned to no role bundle; super admin passes via `Gate::before` (same precedent as `setting.manage`). The permission list and the six roles are fixed by the seeders; this module assigns existing permissions/roles, it does not create new ones.

## GET /permissions
Returns the full registry so the UI can render a checklist. Grouped by the prefix before the dot.
Response `data`:
```json
{
  "groups": [
    { "module": "branch", "permissions": [ { "name": "branch.manage", "label": "Branch manage" } ] },
    { "module": "attendance", "permissions": [ { "name": "attendance.create" }, { "name": "attendance.update" }, { "name": "attendance.view" } ] }
  ]
}
```

## GET /roles
Response `data` (not paginated — fixed small set):
```json
[
  { "id": 1, "name": "super_admin", "is_protected": true, "users_count": 1, "permissions": ["branch.manage", "..."] },
  { "id": 2, "name": "admin", "is_protected": false, "users_count": 4, "permissions": ["student.view", "..."] }
]
```
`is_protected` is `true` for `super_admin` only.

## GET /roles/{id}
200 single role (same shape as a list element) | 404 unknown id.

## PUT /roles/{id}/permissions
Replace the role's entire permission set (sync semantics — send the full desired list).
Request: `{ "permissions": ["student.view", "student.update", "attendance.view"] }`
Success — 200: the updated role object (as in GET /roles/{id}).
Failures:
- Any name not in the registry → 422 `errors.permissions`.
- `super_admin` role → **403** `{ "success": false, "message": "The super admin role cannot be modified" }` (it bypasses checks anyway; editing it is meaningless and unsafe).
- Effective permissions of users already assigned this role update immediately (spatie cache flushed on write).

## GET /users
Staff/account list for role assignment. Branch-scoped automatically (super admin sees all; super admin is the only caller anyway since `role.manage` is super-admin-only — but the scope stays correct if the permission is ever delegated).
Query: `?search=` (name/email/phone), `?role=` (filter by role name), pagination (`page`, `per_page` default 15, max 100).
Response — 200 paginated (`meta`):
```json
{ "data": [ { "id": 5, "name": "Karim", "email": "karim@...", "phone": "0171...", "is_active": true, "roles": ["accountant"] } ] }
```

## PUT /users/{id}/roles
Replace the user's role set (sync). The system is effectively single-role per account, but the endpoint accepts an array for forward-compatibility.
Request: `{ "roles": ["accountant"] }`
Success — 200: the updated user object (`{ id, name, email, phone, is_active, roles: [] }`).
Failures:
- Any name not among the six seeded roles → 422 `errors.roles`.
- Removing the `super_admin` role from the **last** active super admin → **422** `errors.roles` `"At least one super admin is required"` (lockout guard).
- Assigning `super_admin` is allowed only by an existing super admin (always true here) → otherwise 403.
- Unknown user id → 404.

## Out of scope
Creating/deleting custom roles or permissions (the registry + six roles stay seeder-defined); per-user direct permissions (`givePermissionTo` on a user) — assignment is role-based only.
