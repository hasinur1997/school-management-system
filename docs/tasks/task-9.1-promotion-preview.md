# Task 9.1 — Promotion Table & Preview

| Field | Value |
|---|---|
| Phase | 9 — Promotion |
| Status | `todo` |
| Depends on | 8.4 |
| Blocks | 9.2, 9.3 |
| Spec references | `docs/api/promotions.md`, schema → `promotions` |
| Estimated size | One sitting |

## Background
Before the one-click promote, admins see exactly who moves and who doesn't, with reasons. Target class resolved by `numeric_level + 1` within the branch.

## Objective
`promotions` migration + `GET /promotions/preview`.

## What To Implement
1. Migration per schema: student_id, from_enrollment_id, to_enrollment_id nullable, type (bulk|individual), promoted_by, promoted_at.
2. Preview (`promotion.execute`): `?session_id=&class_id=` → join active enrollments × annual_results: eligible (published, is_passed), not_eligible with reason `failed | no_result | tc`; resolve `to_class` (level+1) — top class (no next) → `to_class: null` with note.

## API Contract
### GET /api/v1/promotions/preview?session_id=1&class_id=7 — 200:
```json
{ "success": true, "message": "OK", "data": {
  "to_class": { "id": 8, "name": "Class 8" },
  "eligible": [ { "student_id": 9, "name_en": "Rahima Khatun", "roll_no": 12, "annual_gpa": "4.59" } ],
  "not_eligible": [ { "student_id": 14, "name_en": "Karim", "reason": "failed" },
                    { "student_id": 17, "name_en": "Saif", "reason": "no_result" } ] } }
```
Failures: missing params 422; annual results not published for the class → 409 "Publish annual results first".

## Success Criteria
- [ ] Reason taxonomy exact (failed/no_result/tc); next-class resolution incl. top-class null; published guard; single query set; tests green

## Required Tests
1. mixed cohort preview (passed/failed/no-result/tc each appear correctly)
2. unpublished guard 409; top class to_class null

## Out of Scope
Executing promotion (9.2/9.3).

## Completion Protocol
Set Status `done`, tick 9.1, log surprises.
