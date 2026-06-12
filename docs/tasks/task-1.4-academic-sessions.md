# Task 1.4 — Academic Sessions CRUD

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `done` |
| Depends on | 1.3 |
| Blocks | Enrollments, exams, fee structures (Phases 3+) |
| Spec references | `docs/api/academic-structure.md`, schema → `academic_sessions` |
| Estimated size | One sitting |

## Background
Sessions ("2026") anchor enrollments, exams, and fees. Exactly one session is current at any time; switching `is_current` must atomically unset the others.

## Objective
Sessions CRUD (`session.manage`) with enforced single-current-session logic.

## What To Implement
1. Migration: `name` VARCHAR(20), `start_date` DATE, `end_date` DATE, `is_current` BOOLEAN default false. Unique `name`.
2. Model, Resource, Requests (end_date after start_date), thin controller, `SessionService::setCurrent()` wrapping the unset-others + set in a transaction.
3. Routes: standard CRUD at `/sessions` + the `is_current: true` path handled inside update.
4. Seeder: session "2026" (current).

## API Contract
### POST /api/v1/sessions
Request: `{ "name": "2026", "start_date": "2026-01-01", "end_date": "2026-12-31", "is_current": true }`
Success — 201, session object. Side effect: if `is_current` true, all other sessions become false (single transaction).
Failures: duplicate name → 422; `end_date <= start_date` → 422 `errors.end_date`; 403 without permission.

### GET /sessions — 200 list (no pagination needed; small table, return all, newest first).
### PUT /sessions/{id} — same rules; setting `is_current: false` on the only current session → 422 ("One session must be current").
### DELETE /sessions/{id} — 409 if referenced by enrollments/exams/fee structures.

## Success Criteria
- [x] Exactly one current session is guaranteed after any write
- [x] Date-order validation; duplicate-name 422; delete-in-use 409
- [x] Tests green

## Required Tests
1. create as current unsets previous current (assert DB)
2. cannot unset the only current session → 422
3. date validation 422; duplicate name 422
4. delete-in-use 409

## Out of Scope
`current_session_id` setting key (14.1) · session auto-rollover.

## Completion Protocol
Set Status `done`, tick 1.4, log surprises.
