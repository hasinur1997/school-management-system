# Task 1.3 — Branches CRUD

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `done` |
| Depends on | 1.2 |
| Blocks | 1.7 and every branch-scoped module |
| Spec references | `docs/api/academic-structure.md`, `docs/database-schema.md` → `branches` |
| Estimated size | One sitting |

## Background
Every later table carries `branch_id`. This task creates the branches themselves (e.g., "Madani PathShala", "Jabed Ali"). Scoping enforcement comes in 1.7 — here we only manage the branch records, super admin only.

## Objective
Full branches CRUD guarded by `branch.manage`, plus a seeder with the two known branches.

## What To Implement
1. Migration per schema: `name` VARCHAR(150), `code` VARCHAR(20) unique, `address` VARCHAR(255) nullable, `phone` VARCHAR(20) nullable, `email` VARCHAR(150) nullable, `is_active` BOOLEAN default true.
2. Model, `BranchResource`, `StoreBranchRequest`/`UpdateBranchRequest`, thin `BranchController`.
3. Routes: `GET/POST /branches`, `GET/PUT/DELETE /branches/{id}` — all `auth:sanctum` + `permission:branch.manage`.
4. `BranchSeeder` with the two real branches.

## API Contract
### POST /api/v1/branches
Request: `{ "name": "Madani PathShala", "code": "MP", "address": "...", "phone": "01712345678", "email": null }`
Success — 201:
```json
{ "success": true, "message": "Branch created", "data": { "id": 1, "name": "Madani PathShala", "code": "MP", "address": "...", "phone": "01712345678", "email": null, "is_active": true } }
```
Failures: duplicate `code` → 422 `errors.code`; missing name/code → 422; non-super-admin → 403.

### GET /api/v1/branches — 200 paginated list (meta), filter `is_active`, search by name/code.
### GET /branches/{id} — 200 single | 404 unknown id.
### PUT /branches/{id} — same body/validation as create; 200.
### DELETE /branches/{id} — 200 `{ "data": null }` | **409** if any record references the branch:
```json
{ "success": false, "message": "Branch is in use and cannot be deleted" }
```

## Success Criteria
- [x] CRUD complete with envelope responses and pagination meta
- [x] `code` uniqueness enforced; delete-in-use → 409
- [x] Only super admin can access (teacher → 403)
- [x] Seeder creates both real branches
- [x] Tests green

## Required Tests
1. create happy path; duplicate code 422
2. list pagination + is_active filter
3. update, show, delete happy paths; delete-in-use 409 (seed a dependent row)
4. teacher denied 403

## Out of Scope
Branch scoping middleware/scope (1.7) · per-branch settings (14.1).

## Completion Protocol
Set Status `done`, tick 1.3 on the board, log surprises.
