# Task 5.1 — Student Attendance: Table & Entry Sheet

| Field | Value |
|---|---|
| Phase | 5 — Student Attendance |
| Status | `todo` |
| Depends on | 4.3 |
| Blocks | 5.2, 5.3 |
| Spec references | `docs/api/student-attendance.md`, schema → `student_attendances` |
| Estimated size | One sitting |

## Background
Teachers take attendance against a roster. The sheet endpoint returns that roster with any existing marks for the day so the same screen serves both first entry and editing.

## Objective
`student_attendances` migration plus `GET /attendance/sheet`.

## What To Implement
1. Migration per schema: `enrollment_id` FK, `date` DATE, `status` VARCHAR(10), `recorded_by` FK users; unique (`enrollment_id`,`date`); index `date`. `StudentAttendance` model + `AttendanceStatus` enum (present/absent/late/leave).
2. `GET /attendance/sheet?class_id=&section_id=&date=` — `permission:attendance.create`; date defaults today; returns active enrollments of the section (roll order) left-joined with that date's records.

## API Contract
### GET /api/v1/attendance/sheet?class_id=7&section_id=12&date=2026-06-11 — 200:
```json
{ "success": true, "message": "OK", "data": {
  "date": "2026-06-11", "class": "Class 7", "section": "A",
  "students": [
    { "enrollment_id": 31, "roll_no": 1, "name_en": "Karim", "photo_url": "...", "status": "present" },
    { "enrollment_id": 32, "roll_no": 2, "name_en": "Rahima", "photo_url": "...", "status": null } ] } }
```
`status: null` = not yet taken. Failures: missing class/section → 422; section not in class → 422; TC/inactive enrollments excluded from roster.

## Success Criteria
- [ ] Roster ordered by roll, joined statuses correct, TC/inactive excluded, single query set (no N+1)
- [ ] Tests green

## Required Tests
1. sheet with no records → all null; with partial records → mixed
2. TC student absent from roster
3. validation 422s

## Out of Scope
Saving (5.2) · monthly views (5.3).

## Completion Protocol
Set Status `done`, tick 5.1, log surprises.
