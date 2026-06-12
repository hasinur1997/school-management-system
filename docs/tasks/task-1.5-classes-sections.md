# Task 1.5 — Classes & Sections CRUD

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `todo` |
| Depends on | 1.4 |
| Blocks | Subjects (1.6), admissions, enrollments, promotion |
| Spec references | `docs/api/academic-structure.md`, schema → `school_classes`, `sections` |
| Estimated size | One sitting |

## Background
Classes carry `numeric_level` (1–12) which later drives promotion ("next class" = level + 1). Sections belong to classes; `class_teacher_id` stays nullable until teachers exist (Phase 2).

## Objective
CRUD for classes and nested sections, guarded by `class.manage`; read endpoints open to all authenticated users (dropdown data).

## What To Implement
1. Migrations per schema. `school_classes`: `branch_id` FK, `name` VARCHAR(50), `numeric_level` TINYINT UNSIGNED, `is_active`; unique (`branch_id`,`numeric_level`). `sections`: `class_id` FK, `name` VARCHAR(30), `class_teacher_id` nullable FK (constrained in Phase 2 migration if needed — make it unsignedBigInteger nullable now, add FK in 2.1); unique (`class_id`,`name`).
2. Models with relationships; Resources; Requests; thin controllers.
3. Routes: `GET/POST /classes`, `GET/PUT/DELETE /classes/{id}`; `GET/POST /classes/{class}/sections`, `GET/PUT/DELETE /sections/{id}`. Writes: `permission:class.manage`. Reads: any authenticated user.
4. Seeder: classes 1–10 with sections A for the first branch.

## API Contract
### POST /api/v1/classes
Request: `{ "name": "Class 7", "numeric_level": 7 }` (branch from auth user; super admin may pass `branch_id`).
Success — 201 class object. Failures: duplicate level in branch → 422 `errors.numeric_level`; level outside 1–12 → 422.

### POST /api/v1/classes/{class}/sections
Request: `{ "name": "A" }` → 201 `{ "id": 3, "class_id": 7, "name": "A", "class_teacher": null }`.
Duplicate name in class → 422.

### GET /classes — 200 all active classes for the user's branch, ordered by numeric_level (include sections). Any authenticated user.
### DELETE class/section — 409 when in use.

## Success Criteria
- [ ] Unique constraints enforced; reads open to all roles, writes only `class.manage`
- [ ] Classes ordered by level; sections nested in class responses
- [ ] Delete-in-use 409 · Tests green

## Required Tests
1. create class happy + duplicate-level 422
2. section create + duplicate-name 422
3. student-role user can GET /classes but POST → 403
4. delete-in-use 409

## Out of Scope
Branch scope enforcement (1.7 — until then queries filter by the user's branch_id explicitly in the service, replaced in 1.7) · class_teacher assignment endpoint (2.3 / 1.8).

## Completion Protocol
Set Status `done`, tick 1.5, log surprises.
