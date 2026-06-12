# Task 10.2 — Invoices & Monthly Generation

| Field | Value |
|---|---|
| Phase | 10 — Fees & Payments |
| Status | `todo` |
| Depends on | 10.1 |
| Blocks | 10.3–10.5 |
| Spec references | `docs/api/fees-payments.md`, schema → `invoices` |
| Estimated size | One sitting |

## Background
Invoices are generated monthly (scheduler, 1st) for all active students from their class's fee structure, copying the amount. Generation is idempotent — unique (student, month, year) makes re-runs safe.

## Objective
Migration, `invoices:generate` command (scheduled + manual endpoint), list/show/`/me/invoices`.

## What To Implement
1. Migration per schema; invoice_no format `INV-{branchCode}-{yyyymm}-{seq}`; due_date = `invoice_due_day` setting (default 10).
2. `GenerateInvoices` command `{month} {year}`: chunk active (non-TC/inactive) students with active enrollment; skip if fee structure missing (log + report) or invoice exists; bulk insert. Schedule monthly on the 1st. `POST /invoices/generate` (`fee.manage`) triggers same service.
3. Reads: `GET /invoices?student_id=&class_id=&status=&month=&year=` (`invoice.view`); `GET /invoices/{id}` (policy: staff, self, linked parent) incl. payments; `GET /me/invoices?student_id=&year=`.

## API Contract
### POST /api/v1/invoices/generate
Request: `{ "month": 6, "year": 2026 }` → 200 `{ "data": { "created": 120, "skipped_existing": 3, "missing_fee_structure": [ { "class_id": 9 } ] } }`. Re-run → created 0, skipped_existing 123 (200, idempotent). Invalid month → 422.
### GET /invoices/{id} — 200:
```json
{ "success": true, "message": "OK", "data": { "id": 88, "invoice_no": "INV-MP-202606-0009", "student": { "id": 9, "name_en": "Rahima Khatun" }, "month": 6, "year": 2026, "amount": "1500.00", "paid_amount": "0.00", "status": "unpaid", "due_date": "2026-06-10", "payments": [] } }
```
Student/parent on unrelated invoice → 404.

## Success Criteria
- [ ] Idempotent generation proven; TC/inactive skipped; amount copied (later fee edit doesn't change it)
- [ ] Scheduler registered; policy matrix on reads; tests green

## Required Tests
1. generate → re-generate idempotent; TC student excluded
2. fee-structure-missing reporting; amount snapshot vs later fee edit
3. /me/invoices for student + linked parent; unrelated 404

## Out of Scope
Payments (10.3+), late fees (open question — do not implement).

## Completion Protocol
Set Status `done`, tick 10.2, log surprises.
