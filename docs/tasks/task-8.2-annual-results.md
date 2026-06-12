# Task 8.2 — Annual Result (25/25/50)

| Field | Value |
|---|---|
| Phase | 8 — Results |
| Status | `todo` |
| Depends on | 8.1 |
| Blocks | 8.3, 8.4, Phase 9 |
| Spec references | `docs/api/results.md`, schema → `annual_results` |
| Estimated size | One sitting |

## Background
The headline business rule: **Annual GPA = 0.25·S1 + 0.25·S2 + 0.50·Final**, requiring all three exams published. `is_passed` requires the final exam passed and the resulting annual grade not F. Promotion (Phase 9) consumes this table.

## Objective
`POST /annual-results/generate` and `/publish` for a (session, class).

## What To Implement
1. Migration per schema (enrollment_id unique, three component GPAs, annual_gpa, grade, is_passed, published_at).
2. Generate (`result.generate`): body `{ session_id, class_id }`; 409 unless all three exams of the tuple are `published`; per enrollment with all three exam_results: weighted GPA (2dp half-up), grade via scale grade-point mapping, is_passed rule; bulk upsert; skip+report enrollments missing any exam result; 409 if annual already published.
3. Publish: stamp published_at (transaction). Browse via 8.3.

## API Contract
### POST /api/v1/annual-results/generate
Request: `{ "session_id": 1, "class_id": 7 }`
Success — 200 `{ "data": { "generated": 41, "skipped": [ { "enrollment_id": 55, "reason": "missing first_semester result" } ] } }`
Failures: any exam unpublished → 409 `{ "message": "All three exams must be published first" }`; already published → 409; unknown tuple → 422.
### POST /annual-results/publish — `{ session_id, class_id }` → 200 count; re-publish 409.

## Success Criteria
- [ ] Formula exact to 2dp incl. rounding edges (e.g., 3.555 → 3.56)
- [ ] Pass rule: failed final ⇒ failed annual regardless of weighted number
- [ ] Three-published guard; idempotency-until-publish; tests green

## Required Tests
1. unit: weighting fixtures (incl. rounding edge); failed-final ⇒ not passed
2. guard 409 with one exam unpublished; skip reporting
3. regenerate-then-publish-then-409

## Out of Scope
Search/student reads (8.3) · promotion (9.x).

## Completion Protocol
Set Status `done`, tick 8.2, log surprises.
