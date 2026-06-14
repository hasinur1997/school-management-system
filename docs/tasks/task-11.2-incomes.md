# Task 11.2 — Incomes CRUD (System Rows Immutable)

| Field | Value |
|---|---|
| Phase | 11 — Finance |
| Status | `done` |
| Depends on | 11.1 |
| Blocks | Reports 13.2 |
| Spec references | `docs/api/finance.md`, schema → `incomes` |
| Estimated size | One sitting |

## Background
Manual income entry, plus visibility of the system-generated fee incomes 10.3 already creates. System rows (payment_id set) are read-only — editing them would desync finance from payments.

## Objective
Income endpoints with the immutability rule.

## What To Implement
Model on existing migration (BelongsToBranch); routes: `GET /incomes?category_id=&from=&to=&search=` (sort date/amount, paginated), `POST /incomes`, `PUT/DELETE /incomes/{id}` — all `income.manage`. Resource exposes `is_system` (payment_id !== null). Update/delete on system rows → 403.

## API Contract
### POST /api/v1/incomes
Request: `{ "title": "Donation from alumni", "amount": "25000.00", "date": "2026-06-11", "category_id": 3, "description": null }` → 201 with `"is_system": false`.
Failures: negative amount → 422; future date → allowed (no rule) — **decision: allow**; bad category type (expense category) → 422.
### PUT/DELETE on system row — 403 `{ "message": "System-generated income cannot be modified" }`.
### GET — 200 paginated, `is_system` on every row; date-range filter inclusive.

## Success Criteria
- [x] System-row 403 both verbs; category-type check; range filter inclusive; tests green

## Required Tests
1. manual CRUD; expense-category 422
2. system row edit/delete 403; appears with is_system true after a 10.3 payment
3. from/to filter boundaries; sort by amount

## Out of Scope
Reports (13.2).

## Completion Protocol
Set Status `done`, tick 11.2, log surprises.
