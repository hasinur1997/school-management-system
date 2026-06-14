# Task 7.3 — Marks Entry Sheet & Bulk Save

| Field | Value |
|---|---|
| Phase | 7 — Exams & Marks |
| Status | `done` |
| Depends on | 7.2 |
| Blocks | Phase 8 |
| Spec references | `docs/api/exams-marks.md`, schema → `marks` |
| Estimated size | One sitting |

## Background
Mirrors attendance's sheet/save pattern, per subject per exam. Grade + grade point are **snapshotted at entry** via GradeResolver so later scale edits don't alter stored marks.

## Objective
`GET /exams/{id}/marks/sheet`, `POST /exams/{id}/marks` (bulk upsert), `GET /exams/{id}/marks` (browse).

## What To Implement
1. Migration per schema: exam_id, enrollment_id, subject_id, obtained_marks DECIMAL(5,2), grade, grade_point, entered_by; unique (exam_id, enrollment_id, subject_id).
2. Sheet: roster of the exam's class (filter `section_id`) + existing marks for `subject_id`.
3. Save: `permission:marks.entry`; teacher must be assigned to the subject (teacher_assignments; non-teacher staff with permission bypass); exam not published; `0 ≤ obtained_marks ≤ subject.full_marks`; bulk upsert with snapshots.

## API Contract
### POST /api/v1/exams/3/marks
Request: `{ "subject_id": 31, "marks": [ { "enrollment_id": 31, "obtained_marks": 78.5 }, { "enrollment_id": 32, "obtained_marks": 91 } ] }`
Success — 200 `{ "data": { "saved": 2 } }` (rows store grade "A"/4.00 and "A+"/5.00 snapshots).
Failures: published exam → 409 "Marks are frozen for published exams"; out-of-range → 422 keyed `errors.marks.1.obtained_marks`; subject not in exam's class → 422; unassigned teacher → 403.
### GET sheet — 200 `{ exam, subject: { full_marks, pass_marks }, students: [ { enrollment_id, roll_no, name_en, obtained_marks|null } ] }`.

## Success Criteria
- [ ] Upsert + snapshot proven (change scale after entry → stored grade unchanged)
- [ ] Assignment check; published freeze; range validation keyed per row
- [ ] Tests green

## Required Tests
1. save/re-save upsert; snapshot immunity to scale change
2. unassigned teacher 403; admin ok; published 409
3. range 422 per-row; subject/class mismatch 422

## Out of Scope
GPA computation (8.1) · result browsing.

## Completion Protocol
Set Status `done`, tick 7.3, log surprises.
