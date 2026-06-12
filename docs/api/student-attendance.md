# Student Attendance API — Phase 5

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /attendance/sheet | attendance.create | Entry sheet: roster + existing marks for the day |
| POST | /attendance | attendance.create | Bulk save a class/section's attendance for one date |
| PUT | /attendance/{id} | attendance.update | Correct a single record |
| GET | /attendance | attendance.view | Browse; filters: class_id, section_id, date, status |
| GET | /students/{id}/attendance | attendance.view + policy | Monthly sheet: `?month=&year=` |
| GET | /me/attendance | student role | Own monthly sheet: `?month=&year=` |

## GET /attendance/sheet
Query: `class_id`, `section_id`, `date` (default today).
Response `data`: `{ date, section, students: [ { enrollment_id, roll_no, name_en, photo, status: "present|absent|late|leave|null" } ] }` — `null` means not yet taken.

## POST /attendance
Request: `{ "section_id", "date", "records": [ { "enrollment_id", "status" } ] }`
Behavior: validates teacher is assigned to the class (or user has attendance for all), upserts in bulk, stamps `recorded_by`. Future dates → 422. TC/inactive enrollments rejected.
Response: `{ "saved": n }`. Re-posting the same date updates (idempotent upsert).

## GET /students/{id}/attendance
Response `data`: `{ month, year, summary: { present, absent, late, leave, working_days }, days: [ { date, status } ] }`.
