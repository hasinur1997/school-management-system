# Exams & Marks API — Phase 7

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /exams | exam.view | Filters: session_id, class_id, type, status |
| POST | /exams | exam.manage | `{ session_id, class_id, type, name, start_date?, end_date? }`; 422 if (session, class, type) exists |
| PUT | /exams/{id} | exam.manage | Update name/dates/status (not type/class/session) |
| GET | /grading-scales | Authenticated | Current scale (cached) |
| PUT | /grading-scales | setting.manage | Replace full scale; ranges must cover 0–100 without overlap |
| GET | /exams/{id}/marks/sheet | marks.entry | Entry sheet for one subject |
| POST | /exams/{id}/marks | marks.entry | Bulk save marks for one subject |
| GET | /exams/{id}/marks | marks.view | Browse; filters: subject_id, section_id |

## GET /exams/{id}/marks/sheet
Query: `subject_id`, `section_id`.
Response `data`: `{ exam, subject: { full_marks, pass_marks }, students: [ { enrollment_id, roll_no, name_en, obtained_marks: number|null } ] }`.

## POST /exams/{id}/marks
Request: `{ "subject_id", "marks": [ { "enrollment_id", "obtained_marks" } ] }`
Behavior: validates user teaches the subject (or has blanket marks.entry), `0 ≤ obtained_marks ≤ full_marks`, exam not `published`; computes grade + grade_point from the scale at entry; bulk upsert, stamps entered_by.
Errors: 409 exam already published (marks frozen); 422 out-of-range marks (per-row errors keyed by enrollment_id).
