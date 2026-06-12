# Task 11.3 — Expenses CRUD

| Field | Value |
|---|---|
| Phase | 11 — Finance |
| Status | `todo` |
| Depends on | 11.1 |
| Blocks | Reports 13.2 |
| Spec references | `docs/api/finance.md`, schema → `expenses` |
| Estimated size | One sitting (small) |

## Objective
Expense entry per requirement: item, price, date, description (+ category). Permission `expense.manage`.

## What To Implement
Migration per schema (item_name VARCHAR(150), amount, date indexed, description TEXT null, category_id null, created_by); model/Resource/Requests/controller; routes mirroring incomes (same filters/sorts).

## API Contract
### POST /api/v1/expenses
Request: `{ "item_name": "Electricity bill", "amount": "8200.00", "date": "2026-06-10", "category_id": 5, "description": "May bill" }` → 201.
Failures: income-type category → 422; negative amount → 422.
### GET /expenses?from=&to=&category_id=&search= — 200 paginated. PUT/DELETE — plain 200 (no system rows here).

## Success Criteria
- [ ] CRUD + filters mirror incomes; category-type check; tests green

## Required Tests
1. CRUD; income-category 422; 2. range/search filters; 3. branch isolation 404

## Out of Scope
Reports (13.2).

## Completion Protocol
Set Status `done`, tick 11.3, log surprises.
