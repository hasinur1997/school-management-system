# Fees & Payments API — Phase 10

| Method | URI | Permission | Description |
|---|---|---|---|
| GET/POST/PUT | /fee-structures[/{id}] | fee.manage | `{ session_id, class_id, monthly_fee }` |
| POST | /invoices/generate | fee.manage | Manual trigger of monthly generation: `{ month, year }` (idempotent; normally the scheduler runs this) |
| GET | /invoices | invoice.view | Filters: student_id, class_id, status, month, year |
| GET | /invoices/{id} | invoice.view + policy | Invoice + payments |
| GET | /me/invoices | student/parent | Own/linked: `?student_id=&year=` |
| POST | /invoices/{id}/payments/local | fee.collect | Record cash payment |
| POST | /invoices/{id}/payments/online | student/parent/staff + policy | Init SSLCommerz checkout |
| POST | /payments/sslcommerz/ipn | Public | Gateway server callback (the source of truth) |
| GET | /payments/sslcommerz/success\|fail\|cancel | Public | Browser redirect landing (no state change) |
| GET | /payments/{id} | invoice.view + policy | Payment detail |
| GET | /payments/{id}/receipt | invoice.view + policy | Money receipt PDF (stream), only when status `paid` |

## POST /invoices/{id}/payments/local
Request: `{ "amount" }` — must equal outstanding amount unless `partial_payment_enabled` setting is true.
One transaction: payment `paid` (method cash, receipt_no, collected_by) → invoice paid_amount/status updated → income row created. Response includes receipt URL. 409 if invoice already paid.

## POST /invoices/{id}/payments/online
Creates a `pending` payment with generated transaction reference, calls SSLCommerz session API, returns `{ "gateway_url": "..." }` for client redirect.

## POST /payments/sslcommerz/ipn
Behavior: look up payment by tran_id → verify with SSLCommerz validation API → if valid and still pending: same transaction pipeline as local (paid → invoice → income → receipt_no), store gateway_payload, queue notification. **Idempotent:** replayed IPN for a settled payment returns 200 with no changes. Invalid signature → 422, payment marked `failed`.
