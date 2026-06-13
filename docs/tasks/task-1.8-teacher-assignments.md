# Task 1.8 — Teacher Assignments CRUD

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `done` |
| Depends on | 1.7 (and structurally on teachers existing — see note) |
| Blocks | Attendance permission checks (5.2), marks entry checks (7.3) |
| Spec references | `docs/api/academic-structure.md`, schema → `teacher_assignments` |
| Estimated size | One sitting |

## Background
Assignments answer "which teacher teaches what, where, this session" — attendance and marks entry validate against them. **Note:** the `teachers` table arrives in 2.1; this task creates the `teacher_assignments` migration with an unconstrained `teacher_id` (FK added in 2.1's migration) OR is executed immediately after 2.1 — the agent should create the migration here and add the FK constraint in 2.1. Endpoints here can be fully tested once 2.1 lands; write tests using a minimal teachers factory stub if running before 2.1 is impossible — preferred order: finish 1.8 schema + code, run its tests right after 2.1.

## Objective
CRUD for assignments (`teacher.manage` → use `teacher.create`/`teacher.update`? — **decision: guard with `teacher.update`**) linking teacher × session × class × section? × subject?.

## What To Implement
1. Migration per schema: `teacher_id`, `session_id`, `class_id`, `section_id` nullable, `subject_id` nullable; unique across all five.
2. Model (BelongsToBranch via class relation? — assignments derive branch from class; store no branch_id, scope through `class`), Resource, Requests, controller.
3. Routes: `GET/POST /teacher-assignments`, `GET/PUT/DELETE /teacher-assignments/{id}`; filters `teacher_id, class_id, session_id`.
4. Helper on Teacher model (lands 2.1): `isAssignedTo(class, section?, subject?)` used later by attendance/marks.

## API Contract
### POST /api/v1/teacher-assignments
Request: `{ "teacher_id": 4, "session_id": 1, "class_id": 7, "section_id": 12, "subject_id": 31 }` (`section_id`/`subject_id` nullable — null subject = class duty such as attendance).
Success — 201 assignment with nested teacher/class/section/subject names.
Failures: duplicate tuple → 422; class/section mismatch (section not in class) → 422 `errors.section_id`; ids not found → 422.

### GET /teacher-assignments?teacher_id=&class_id=&session_id= — 200 paginated.
### DELETE — 200; no RESTRICT dependents expected.

## Success Criteria
- [x] Duplicate-tuple and section-belongs-to-class validation
- [x] Filters work; nested names in resource (eager loaded — no N+1)
- [x] Tests green (teacher_id unconstrained; full teacher-FK testing in 2.1)

## Required Tests
1. create happy; duplicate 422; section/class mismatch 422
2. filters by teacher and class
3. N+1 guard: list with relations under strict mode

## Out of Scope
Enforcement at attendance/marks time (5.2, 7.3) · class_teacher on sections (set via PUT /sections in 1.5 once teachers exist).

## Completion Protocol
Set Status `done`, tick 1.8, log surprises.
