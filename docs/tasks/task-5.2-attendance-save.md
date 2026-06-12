# Task 5.2 — Student Attendance: Bulk Save & Correction

| Field | Value |
|---|---|
| Phase | 5 — Student Attendance |
| Status | `todo` |
| Depends on | 5.1 |
| Blocks | 5.3 |
| Spec references | `docs/api/student-attendance.md` |
| Estimated size | One sitting |

## Background
The write side. A teacher must be assigned to the class (1.8) unless they hold blanket permission; saves are bulk upserts so re-posting the same date edits rather than errors.

## Objective
`POST /attendance` (bulk upsert) and `PUT /attendance/{id}` (single correction).

## What To Implement
1. `AttendanceService::saveBulk()` — validates: section belongs to user's branch; user is assigned to the class via teacher_assignments (skip check for non-teacher staff holding `attendance.create`); date not in future; every enrollment_id is active and belongs to the section. Bulk upsert (single query, chunk 500), stamps `recorded_by`.
2. `POST /attendance` — `permission:attendance.create`. `PUT /attendance/{id}` — `permission:attendance.update`, status only.
3. Browse: `GET /attendance?class_id=&section_id=&date=&status=` — `permission:attendance.view`, paginated.

## API Contract
### POST /api/v1/attendance
Request: `{ "section_id": 12, "date": "2026-06-11", "records": [ { "enrollment_id": 31, "status": "present" }, { "enrollment_id": 32, "status": "absent" } ] }`
Success — 200 `{ "data": { "saved": 2 } }`. Re-post with changed statuses — 200, records updated (idempotent upsert).
Failures: future date → 422 `errors.date`; unassigned teacher → 403 ("You are not assigned to this class"); enrollment not in section / TC / inactive → 422 keyed `errors.records.N.enrollment_id`; invalid status → 422.
### PUT /attendance/{id} — `{ "status": "late" }` → 200 record. Unknown id / other branch → 404.

## Success Criteria
- [ ] Upsert semantics proven (insert then update path)
- [ ] Assignment check enforced for teachers, bypassed for admin with permission
- [ ] All 422/403 cases exact; bulk insert ≠ N queries
- [ ] Tests green

## Required Tests
1. save then re-save updates; saved count correct
2. unassigned teacher 403; admin succeeds
3. future date 422; TC enrollment 422; wrong-section 422
4. correction endpoint; cross-branch 404

## Out of Scope
Monthly summaries (5.3) · teacher attendance (Phase 6).

## Completion Protocol
Set Status `done`, tick 5.2, log surprises.
