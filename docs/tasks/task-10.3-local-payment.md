# Task 10.3 — Local Payment, Income Posting & Receipt PDF

| Field | Value |
|---|---|
| Phase | 10 — Fees & Payments |
| Status | `todo` |
| Depends on | 10.2 (and 11.2's incomes table — see note) |
| Blocks | 10.4 |
| Spec references | `docs/api/fees-payments.md`, `architecture-context.md` → Payment Model |
| Estimated size | One sitting |

## Background
Counter payments. The transaction pipeline built here (payment → invoice → income → receipt_no) is reused verbatim by the SSLCommerz IPN in 10.5. **Note:** requires the `incomes` table — create the `incomes` + `categories` migrations here (schema only, per database-schema.md); Phase 11 adds their endpoints.

## Objective
`POST /invoices/{id}/payments/local` and `GET /payments/{id}/receipt`.

## What To Implement
1. Migrations: `payments` per schema; plus schema-only `categories`, `incomes`.
2. `PaymentService::settle(payment)` — THE pipeline, one DB transaction: payment → status paid, receipt_no `RCPT-{branchCode}-{seq}`, paid_at; invoice paid_amount += amount, status paid/partial; income row (title "Monthly fee {month}/{year} — {invoice_no}", payment_id link, date = paid_at).
3. Local endpoint (`fee.collect`): `{ "amount" }`; amount must equal outstanding unless `partial_payment_enabled` setting true (then 0 < amount ≤ outstanding); creates payment (method cash, collected_by) and settles.
4. Receipt PDF (dompdf, streamed): school header, receipt_no, student, invoice month/year, amount, method, collected_by, date. Policy: staff `invoice.view`, self, linked parent; only when status paid.

## API Contract
### POST /api/v1/invoices/88/payments/local
Request: `{ "amount": "1500.00" }`
Success — 201:
```json
{ "success": true, "message": "Payment recorded", "data": { "id": 41, "receipt_no": "RCPT-MP-000041", "amount": "1500.00", "method": "cash", "status": "paid", "paid_at": "2026-06-11T10:02:00+06:00", "invoice": { "id": 88, "status": "paid", "paid_amount": "1500.00" }, "receipt_url": "/api/v1/payments/41/receipt" } }
```
Failures: invoice already paid → 409 "Invoice is already paid"; amount ≠ outstanding with partial disabled → 422 `errors.amount` "Full payment of 1500.00 required"; partial enabled but amount > outstanding → 422; 0/negative → 422.
### GET /payments/41/receipt — 200 application/pdf; pending/failed payment → 404.

## Success Criteria
- [ ] Pipeline atomic (induced failure → nothing persisted); exactly one income row, linked + immutable flag honored later
- [ ] Partial toggle behavior both ways; receipt PDF policy + paid-only
- [ ] Tests green

## Required Tests
1. full payment: invoice paid, income exists with payment_id, receipt_no format
2. partial disabled: under/over 422 · enabled: partial → status partial, second payment completes
3. paid invoice 409; rollback on induced failure
4. receipt: staff/self/parent 200, unrelated 404, pending 404

## Out of Scope
SSLCommerz (10.4/10.5) · income endpoints (11.2).

## Completion Protocol
Set Status `done`, tick 10.3, log surprises.
