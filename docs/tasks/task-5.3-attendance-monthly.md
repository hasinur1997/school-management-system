# Task 5.3 — Student Attendance: Monthly Sheets

| Field | Value |
|---|---|
| Phase | 5 — Student Attendance |
| Status | `todo` |
| Depends on | 5.2 |
| Blocks | Dashboard (14.2) |
| Spec references | `docs/api/student-attendance.md` |
| Estimated size | One sitting (small) |

## Background
Read views for students, parents, and staff: a month of days plus a summary.

## Objective
`GET /students/{id}/attendance` (staff/parent/self via policy) and `GET /me/attendance` (student shortcut).

## What To Implement
Service method aggregating one enrollment-month: summary counts via single SQL aggregate + day list. Routes with `?month=&year=` (defaults: current). Policy: `attendance.view` OR self OR linked parent.

## API Contract
### GET /api/v1/students/9/attendance?month=6&year=2026 — 200:
```json
{ "success": true, "message": "OK", "data": {
  "month": 6, "year": 2026,
  "summary": { "present": 18, "absent": 2, "late": 1, "leave": 0, "working_days": 21 },
  "days": [ { "date": "2026-06-01", "status": "present" }, { "date": "2026-06-02", "status": "absent" } ] } }
```
`working_days` = count of recorded days. Failures: invalid month → 422; unrelated student/parent → 404. `/me/attendance` as non-student → 403.

## Success Criteria
- [ ] Summary computed in SQL; policy matrix (staff/self/linked parent/unrelated) correct; tests green

## Required Tests
1. summary counts; 2. self + linked parent 200; unrelated 404; 3. /me/attendance student-only

## Out of Scope
Attendance percentage on dashboard (14.2).

## Completion Protocol
Set Status `done`, tick 5.3, log surprises.
