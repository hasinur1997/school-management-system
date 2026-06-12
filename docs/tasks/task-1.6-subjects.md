# Task 1.6 — Subjects CRUD + Cached Dropdown Reads

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `todo` |
| Depends on | 1.5 |
| Blocks | Marks entry (7.3), results (Phase 8) |
| Spec references | `docs/api/academic-structure.md`, schema → `subjects`, `CLAUDE.md` → Caching |
| Estimated size | One sitting |

## Background
Subjects belong to a class and define `full_marks`/`pass_marks` used by mark entry validation. Academic-structure reads happen on almost every screen, so this task also introduces the caching pattern from `CLAUDE.md` for classes/sections/subjects.

## Objective
Subjects CRUD (`subject.manage`), open reads, and server-side caching of academic-structure reads with invalidation on write.

## What To Implement
1. Migration: `class_id` FK, `name` VARCHAR(100), `code` VARCHAR(20) nullable, `full_marks` SMALLINT default 100, `pass_marks` SMALLINT default 33; unique (`class_id`,`name`).
2. Model/Resource/Requests/controller. Validation: `pass_marks < full_marks`.
3. Routes: `GET/POST /classes/{class}/subjects`, `GET/PUT/DELETE /subjects/{id}`.
4. Caching: `AcademicStructureService` caches class/section/subject reads (key per branch); model observers (or service writes) forget the keys on any class/section/subject write. Driver-agnostic (`Cache::` facade only).

## API Contract
### POST /api/v1/classes/{class}/subjects
Request: `{ "name": "Mathematics", "code": "MATH", "full_marks": 100, "pass_marks": 33 }`
Success — 201 subject object. Failures: duplicate name in class → 422; `pass_marks >= full_marks` → 422 `errors.pass_marks`.

### GET /classes/{class}/subjects — 200 list, any authenticated user, served from cache.
### DELETE /subjects/{id} — 409 once marks reference it.

## Success Criteria
- [ ] CRUD + validation correct; reads cached and invalidated on write (prove with a test: write → subsequent read reflects change)
- [ ] Tests green

## Required Tests
1. create happy + duplicate 422 + pass>=full 422
2. open read for student role; write 403
3. cache invalidation: update subject, GET returns new name
4. delete-in-use 409 (defer the marks reference using a fake dependent or skip-note until 7.3 — assert RESTRICT at DB level)

## Out of Scope
Per-exam subject configuration (not in spec) · marks logic (7.3).

## Completion Protocol
Set Status `done`, tick 1.6, log surprises.
