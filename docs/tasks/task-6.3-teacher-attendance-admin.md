# Task 6.3 — Teacher Attendance: Browse, Correction, /me

| Field | Value |
|---|---|
| Phase | 6 — Teacher Attendance |
| Status | `todo` |
| Depends on | 6.2 |
| Blocks | Reports (13.3), dashboard |
| Spec references | `docs/api/teacher-attendance.md` |
| Estimated size | One sitting (small) |

## Objective
Admin visibility and correction (`teacher_attendance.view` / `.manage`), plus the teacher's own history.

## What To Implement
- `GET /teacher-attendance?teacher_id=&date=&month=&year=&status=` — paginated, eager-loaded teacher names.
- `PUT /teacher-attendance/{id}` — `{ status?, check_in_at?, check_out_at? }`, stamps `corrected_by`.
- `GET /me/teacher-attendance?month=&year=` — teacher's own records + summary counts.

## API Contract
### PUT /api/v1/teacher-attendance/7
Request: `{ "status": "leave" }` → 200 record incl. `"corrected_by": { "id": 1, "name": "Admin" }`.
Failures: checkout before checkin time → 422; cross-branch id → 404.
### GET /me/teacher-attendance — 200 `{ summary: { present, late, absent, leave }, records: [...] }`. Non-teacher → 403.

## Success Criteria
- [ ] Filters work; corrected_by stamped; time-order validation; tests green

## Required Tests
1. browse filters; 2. correction + corrected_by; bad time order 422; 3. /me summary; 4. cross-branch 404

## Out of Scope
Attendance reports (13.3).

## Completion Protocol
Set Status `done`, tick 6.3, log surprises.
