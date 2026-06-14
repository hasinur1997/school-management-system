# Task 11.1 — Income/Expense Categories CRUD

| Field | Value |
|---|---|
| Phase | 11 — Finance |
| Status | `done` |
| Depends on | 10.5 (table exists from 10.3) |
| Blocks | 11.2, 11.3 |
| Spec references | `docs/api/finance.md`, schema → `categories` |
| Estimated size | One sitting (small) |

## Objective
CRUD for the shared category list (`type: income|expense`); permission `income.manage` OR `expense.manage`.

## What To Implement
Model (BelongsToBranch) on existing migration; routes `GET/POST /categories`, `PUT/DELETE /categories/{id}`; filter `type`; seeder with sensible defaults (Tuition Fee, Donation / Salary, Utilities, Maintenance, Stationery).

## API Contract
### POST /api/v1/categories
Request: `{ "name": "Utilities", "type": "expense" }` → 201. Duplicate (branch,name,type) → 422; invalid type → 422.
### DELETE — in use by income/expense rows → 409 `{ "message": "Category is in use" }`.

## Success Criteria
- [x] Type filter; duplicate + in-use guards; seeder; tests green

## Required Tests
1. CRUD + duplicate 422; 2. delete-in-use 409; 3. filter type; branch isolation

## Out of Scope
Income/expense rows (11.2/11.3).

## Completion Protocol
Set Status `done`, tick 11.1, log surprises.
