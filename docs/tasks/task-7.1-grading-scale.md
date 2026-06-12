# Task 7.1 — Grading Scale

| Field | Value |
|---|---|
| Phase | 7 — Exams & Marks |
| Status | `todo` |
| Depends on | 6.3 |
| Blocks | 7.3, Phase 8 |
| Spec references | `docs/api/exams-marks.md`, schema → `grading_scales` |
| Estimated size | One sitting |

## Background
The scale converts marks → grade + grade point and drives all result math. Bangladesh-standard default: A+ 80–100 = 5.00, A 70–79 = 4.00, A- 60–69 = 3.50, B 50–59 = 3.00, C 40–49 = 2.00, D 33–39 = 1.00, F 0–32 = 0.00 (is_fail).

## Objective
Migration + seeder + cached GET + validated full-replace PUT, plus the `GradeResolver` service used by marks and results.

## What To Implement
1. Migration per schema; seeder with the default scale.
2. `GET /grading-scales` — any authenticated user, cached. `PUT /grading-scales` — `setting.manage`; body replaces the whole scale; validation: ranges cover 0–100 inclusive with no gaps/overlaps, exactly one `is_fail` row, grade points descending.
3. `GradeResolver::resolve(marks)` → `{grade, grade_point, is_fail}`; cached scale.

## API Contract
### PUT /api/v1/grading-scales
Request: `{ "scale": [ { "grade": "A+", "min_marks": 80, "max_marks": 100, "grade_point": 5.00, "is_fail": false }, ... , { "grade": "F", "min_marks": 0, "max_marks": 32, "grade_point": 0.00, "is_fail": true } ] }`
Success — 200 new scale. Failures: gap (e.g., missing 33–39) → 422 "Scale must cover 0–100 without gaps"; overlap → 422; zero or multiple fail rows → 422.
### GET — 200 ordered scale array.

## Success Criteria
- [ ] Seeder exact; coverage/overlap/fail-row validation; resolver correct at boundaries (32→F, 33→D, 80→A+); cache invalidated on PUT; tests green

## Required Tests
1. resolver boundary unit tests; 2. PUT happy; gap/overlap/multi-fail 422s; 3. cache refresh after PUT

## Out of Scope
Per-class scales (single global scale per requirements) · result snapshots (Phase 8 makes published results immune to scale edits).

## Completion Protocol
Set Status `done`, tick 7.1, log surprises.
