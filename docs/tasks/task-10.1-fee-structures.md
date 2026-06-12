# Task 10.1 — Fee Structures CRUD

| Field | Value |
|---|---|
| Phase | 10 — Fees & Payments |
| Status | `todo` |
| Depends on | 9.3 |
| Blocks | 10.2 |
| Spec references | `docs/api/fees-payments.md`, schema → `fee_structures` |
| Estimated size | One sitting (small) |

## Background
Defines the monthly fee per (branch, session, class) — the amount invoices copy at generation time.

## Objective
CRUD guarded by `fee.manage`.

## What To Implement
Migration per schema (unique branch+session+class); model (BelongsToBranch), Resource, Requests (`monthly_fee` numeric ≥ 0, 2dp), controller; routes `GET/POST /fee-structures`, `PUT /fee-structures/{id}` (amount only), `GET` filters session/class. No delete (history matters) — omit DELETE route.

## API Contract
### POST /api/v1/fee-structures
Request: `{ "session_id": 1, "class_id": 7, "monthly_fee": "1500.00" }` → 201. Duplicate tuple → 422 "Fee already defined for this class and session". Negative/3dp amount → 422.
### PUT — `{ "monthly_fee": "1600.00" }` → 200 (affects only future invoice generation — existing invoices keep their copied amount).

## Success Criteria
- [ ] Unique tuple; decimal-string money in responses; no delete route; tests green

## Required Tests
1. create + duplicate 422; 2. update amount; 3. money serialized as "1600.00" string

## Out of Scope
Invoice generation (10.2).

## Completion Protocol
Set Status `done`, tick 10.1, log surprises.
