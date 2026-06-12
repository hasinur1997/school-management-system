# Task 8.1 — Per-Exam Result Generation & Publication

| Field | Value |
|---|---|
| Phase | 8 — Results |
| Status | `todo` |
| Depends on | 7.3 |
| Blocks | 8.2–8.4, Phase 9 |
| Spec references | `docs/api/results.md`, schema → `exam_results` |
| Estimated size | One sitting |

## Background
First half of the result engine. Rules: per-exam GPA = average of subject grade points; any F → exam failed. Generate is repeatable until publish; publish freezes results AND marks (7.3 enforces the marks side).

## Objective
`ResultService::generateExamResults()`, generate/publish/browse endpoints.

## What To Implement
1. Migration per schema: exam_id, enrollment_id, total_marks DECIMAL(7,2), gpa DECIMAL(3,2), grade, is_passed, published_at null; unique (exam_id, enrollment_id).
2. Generate (`result.generate`): for each enrollment with marks in the exam — total, GPA (2dp, round half up), overall grade (lowest subject grade? **No — grade from GPA mapped via scale grade_points; F overrides if any subject failed**), is_passed; bulk upsert; 409 if already published. Enrollments missing marks in any subject of the class are skipped and reported.
3. Publish: sets published_at on all rows + exam status `published` (transaction). 409 re-publish.
4. Browse `GET /exams/{id}/results?section_id=&is_passed=` (`result.view`), paginated, ordered by GPA desc.

## API Contract
### POST /api/v1/exams/3/results/generate — 200:
```json
{ "success": true, "message": "Results generated", "data": { "generated": 42, "skipped": [ { "enrollment_id": 55, "missing_subjects": ["English"] } ] } }
```
Published exam → 409. No marks at all → 422 "No marks entered for this exam".
### POST /exams/3/results/publish — 200 `{ "data": { "published": 42 } }`; re-publish → 409.
### GET /exams/3/results — 200 rows `{ enrollment_id, roll_no, name_en, total_marks: "428.50", gpa: "4.25", grade": "A", is_passed: true }`.

## Success Criteria
- [ ] GPA math + any-F rule exact (unit-tested with fixed fixtures)
- [ ] Idempotent regenerate until publish; publish freezes (regenerate 409)
- [ ] Skipped/missing-subject reporting; tests green

## Required Tests
1. unit: GPA average, rounding, any-F fail, grade mapping
2. generate→regenerate (changed marks reflected)→publish→regenerate 409
3. missing-subject skip list; browse filters

## Out of Scope
Annual weighting (8.2) · PDFs (8.4) · student-facing reads (8.3).

## Completion Protocol
Set Status `done`, tick 8.1, log surprises.
