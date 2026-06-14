# Task 8.3 — Result Search & Student-Facing Reads

| Field | Value |
|---|---|
| Phase | 8 — Results |
| Status | `done` |
| Depends on | 8.2 |
| Blocks | 8.4 |
| Spec references | `docs/api/results.md` |
| Estimated size | One sitting |

## Background
"Teacher, student or any permitted user can search any student result." Staff search by admission no or class coordinates; students/parents read their own — published only.

## Objective
`GET /results/search`, `GET /enrollments/{id}/results`, `GET /me/results`.

## What To Implement
1. Search (`result.view`): `?admission_no=` OR `?session_id=&class_id=&section_id=&roll_no=` → resolves enrollment → full result bundle.
2. `GET /enrollments/{id}/results` — policy: staff `result.view`, or self, or linked parent. Staff see unpublished (flagged `"published": false`); students/parents see published only (unpublished omitted).
3. `GET /me/results?session_id=&student_id=` — student: own; parent: `student_id` must be linked.

## API Contract
### GET /api/v1/results/search?admission_no=MP-2026-0009 — 200:
```json
{ "success": true, "message": "OK", "data": {
  "student": { "id": 9, "name_en": "Rahima Khatun", "admission_no": "MP-2026-0009", "class": "Class 7", "section": "A", "roll_no": 12 },
  "exams": [
    { "type": "first_semester", "published": true, "gpa": "4.50", "grade": "A", "is_passed": true,
      "subjects": [ { "name": "Mathematics", "obtained_marks": "78.50", "grade": "A", "grade_point": "4.00" } ] } ],
  "annual": { "first_semester_gpa": "4.50", "second_semester_gpa": "4.25", "final_exam_gpa": "4.80", "annual_gpa": "4.59", "grade": "A+", "is_passed": true, "published": true } } }
```
Failures: no match → 404; both query styles mixed/missing → 422.
### /me/results — parent with unlinked student_id → 404; student passing student_id → ignored (own only).

## Success Criteria
- [ ] Both search styles; full bundle eager-loaded (no N+1)
- [ ] Published-only filter for students/parents; staff preview flagged
- [ ] Policy matrix correct; tests green

## Required Tests
1. search by admission_no and by coordinates; no-match 404
2. student sees published only; staff sees draft flagged
3. parent linked 200 / unlinked 404

## Out of Scope
PDFs (8.4).

## Completion Protocol
Set Status `done`, tick 8.3, log surprises.
