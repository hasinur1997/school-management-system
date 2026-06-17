# Task 12.3 — Transfer Certificate System

| Field | Value |
|---|---|
| Phase | 12 — Documents |
| Status | `done` |
| Depends on | 12.1 |
| Blocks | — |
| Spec references | `docs/api/documents.md`, schema → `transfer_certificates`, invariant 6 |
| Estimated size | One sitting |

## Background
Issuing a TC retires a student from active operations while preserving every record. The PDF is the project's one **stored** document (legal record). Exclusion must work via scopes — this task adds the `ActiveForAcademics` scope usage checks across attendance, invoicing, promotion.

## Objective
Issue/list/show/pdf endpoints + cross-module exclusion verified.

## What To Implement
1. Migration per schema; tc_no `TC-{branchCode}-{seq}`.
2. `POST /students/{id}/tc` (`tc.issue`): transaction — TC row, student status `tc`, active enrollment status `tc`; render TC PDF (reason, dates, signature placeholders, bilingual name) and persist via medialibrary on the TC model.
3. Reads (`tc.view`): `GET /tcs?from=&to=&search=`, `GET /tcs/{id}`, `GET /tcs/{id}/pdf` (download stored file).
4. Exclusion sweep: assert existing scopes/guards exclude tc status in attendance sheet (5.1), invoice generation (10.2), promotion preview (9.1) — add scope usage where missing.

## API Contract
### POST /api/v1/students/9/tc
Request: `{ "reason": "Family relocated to Dhaka", "issue_date": "2026-06-11" }`
Success — 201 `{ "data": { "id": 3, "tc_no": "TC-MP-0003", "student": {...}, "reason": "...", "issue_date": "2026-06-11", "pdf_url": "/api/v1/tcs/3/pdf" } }`
Failures: TC already issued → 409 "Transfer certificate already issued"; empty reason → 422; unpaid invoices exist → allowed (record kept visible) — **no block, by design**.
### GET /tcs/{id}/pdf — 200 stored pdf; missing file → 500 logged.

## Success Criteria
- [x] Atomic issue (rollback test); stored PDF exists; statuses flipped
- [x] Exclusion proven in three modules (roster, generation, preview)
- [x] One TC per student; tests green

## Required Tests
1. issue happy: row+statuses+media; rollback on induced failure
2. duplicate 409
3. exclusion: attendance sheet, invoice run, promotion preview all skip the student
4. unpaid invoices remain readable

## Out of Scope
TC revocation/re-admission (open question).

## Completion Protocol
Set Status `done`, tick 12.3, log surprises.
