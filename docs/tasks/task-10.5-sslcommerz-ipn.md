# Task 10.5 — SSLCommerz IPN & Idempotency

| Field | Value |
|---|---|
| Phase | 10 — Fees & Payments |
| Status | `todo` |
| Depends on | 10.4 |
| Blocks | — |
| Spec references | `docs/api/fees-payments.md`, invariant 5 |
| Estimated size | One sitting |

## Background
The IPN (server-to-server) is the source of truth; browser redirects change nothing. Invariant: a replayed IPN cannot double-post income — the single most-tested behavior in the project.

## Objective
`POST /payments/sslcommerz/ipn` (public) + success/fail/cancel landing routes.

## What To Implement
1. IPN handler: find payment by `tran_id` (404-silent → 200 ack with no-op to avoid gateway retries storms? **No — unknown tran_id → 422 logged**); if payment already `paid` → 200 `{ "data": { "status": "already_processed" } }` (idempotent); else call `gateway->validate()`; valid → `PaymentService::settle()` (10.3 pipeline) + store gateway_payload + queue payer notification; invalid → payment `failed`, 422.
2. Landing routes `GET /payments/sslcommerz/{success|fail|cancel}?tran_id=` — public, **no state change**, return envelope status of the payment (frontend polls/redirects from here).
3. Concurrency: settle uses `lockForUpdate` on the payment row.

## API Contract
### POST /api/v1/payments/sslcommerz/ipn (form fields incl. tran_id, val_id, amount, status)
Valid first call — 200 `{ "data": { "status": "paid", "receipt_no": "RCPT-MP-000042" } }` (invoice updated, income posted).
Replay — 200 `{ "data": { "status": "already_processed" } }` — **no second income row, no invoice change**.
Invalid signature/validation fail — 422, payment `failed`. Unknown tran_id — 422 (logged). Amount mismatch vs payment → validation fail path.
### GET landing success — 200 `{ "data": { "payment_id": 42, "status": "paid" } }` (or current status). No DB writes — assert in tests.

## Success Criteria
- [ ] Replay produces zero side effects (income count, invoice paid_amount asserted unchanged)
- [ ] Validation failure marks failed; landing routes write nothing; lockForUpdate present
- [ ] Tests green

## Required Tests
1. valid IPN settles (pipeline assertions as 10.3)
2. replay: 200 + side-effect-free (the critical test)
3. invalid validation 422 + failed; amount mismatch fails
4. landing routes change nothing (DB snapshot equality)
5. parallel IPN simulation: two sequential calls, one settlement

## Out of Scope
Refunds/cancellation flows (not in spec).

## Completion Protocol
Set Status `done`, tick 10.5, log surprises.
