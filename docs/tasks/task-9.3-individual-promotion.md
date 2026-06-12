# Task 9.3 — Individual Promotion & History

| Field | Value |
|---|---|
| Phase | 9 — Promotion |
| Status | `todo` |
| Depends on | 9.2 |
| Blocks | — |
| Spec references | `docs/api/promotions.md` |
| Estimated size | One sitting (small) |

## Background
Per-requirement: "Promotion can be also individual." Includes the override path (promote despite fail) behind a separate permission.

## Objective
`POST /promotions/individual` + `GET /promotions` history.

## What To Implement
Individual: `{ student_id, to_session_id, to_class_id, to_section_id, roll_no }`; default requires passed annual result; failed/no-result student allowed only with `promotion.override` (logged). Reuses the service close-old/create-new/log pipeline (type `individual`). History: `GET /promotions?session_id=&class_id=&type=` paginated with student + from/to class names.

## API Contract
### POST /api/v1/promotions/individual
Success — 200 promotion record `{ "student": {...}, "from": { "class": "Class 7", "session": "2026" }, "to": { "class": "Class 8", "session": "2027", "roll_no": 5 }, "type": "individual" }`.
Failures: not passed without override permission → 403 "Student has not passed; override permission required"; duplicate roll in target section/session → 422; already enrolled in target session → 409.
### GET /promotions — 200 paginated history.

## Success Criteria
- [ ] Override gate exact; duplicate-roll 422; history filters; tests green

## Required Tests
1. passed student happy; 2. failed w/o override 403, with override 200; 3. duplicate roll 422; already-promoted 409; 4. history filter by type

## Out of Scope
Demotion/undo (not in spec — open question if needed).

## Completion Protocol
Set Status `done`, tick 9.3, log surprises.
