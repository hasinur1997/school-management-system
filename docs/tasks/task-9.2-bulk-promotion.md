# Task 9.2 — Bulk Promotion

| Field | Value |
|---|---|
| Phase | 9 — Promotion |
| Status | `todo` |
| Depends on | 9.1 |
| Blocks | 9.3 |
| Spec references | `docs/api/promotions.md` |
| Estimated size | One sitting |

## Background
The "Promote button". One transaction, chunked bulk operations: passed students → new enrollment in next class/session; failed students → enrollment status `failed` + fresh enrollment in the **same** class for the new session.

## Objective
`POST /promotions/bulk` (`promotion.execute`).

## What To Implement
`PromotionService::bulk()` — validates: annual results published; to_session ≠ from_session and exists; to_section belongs to the resolved next class. Per chunk (500): close old enrollments (`promoted`/`failed`), insert new enrollments (roll strategy: `by_merit` = order by annual GPA desc reassigning 1..n, `keep` = carry old roll), insert promotion logs. Wrap entire run in one DB transaction. Re-run guard: students already having an enrollment in to_session → 409.

## API Contract
### POST /api/v1/promotions/bulk
Request: `{ "from_session_id": 1, "from_class_id": 7, "to_session_id": 2, "to_section_id": 21, "roll_strategy": "by_merit" }`
Success — 200 `{ "data": { "promoted": 38, "held": 3 } }` (`held` = failed students re-enrolled in same class).
Failures: results unpublished → 409; already promoted (any target-session enrollment exists) → 409 "This class has already been promoted for the target session"; bad section/class pairing → 422; same session → 422.

## Success Criteria
- [ ] Atomicity: induced failure mid-run leaves zero changes
- [ ] Both roll strategies; failed-student same-class re-enrollment; promotion rows logged with type bulk
- [ ] Re-run 409; bulk queries (not per-student); tests green

## Required Tests
1. happy path: counts, enrollment statuses, logs, rolls by merit
2. keep-roll strategy; failed student held in same class new session
3. transaction rollback on induced failure; re-run 409

## Out of Scope
Individual/override (9.3).

## Completion Protocol
Set Status `done`, tick 9.2, log surprises.
