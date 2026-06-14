# Task 10.4 — SSLCommerz Gateway Service & Init

| Field | Value |
|---|---|
| Phase | 10 — Fees & Payments |
| Status | `done` |
| Depends on | 10.3 |
| Blocks | 10.5 |
| Spec references | `docs/api/fees-payments.md`, `architecture-context.md` |
| Estimated size | One sitting |

## Background
Online payments start here: create a pending payment, ask SSLCommerz for a checkout session, hand the gateway URL to the client. The gateway is wrapped in a service interface so tests fake it and 10.5 validates through it.

## Objective
`PaymentGatewayService` (interface + SSLCommerz implementation + fake) and `POST /invoices/{id}/payments/online`.

## What To Implement
1. `PaymentGateway` interface: `createSession(payment): GatewaySession{gateway_url}`, `validate(tran_id, payload): bool`. `SslCommerzGateway` impl using store_id/password/sandbox from settings (config fallback); `FakeGateway` bound in tests.
2. Endpoint — policy: student self, linked parent, or staff with `fee.collect`: amount = outstanding (or partial per toggle, passed in body); create payment (method sslcommerz, status pending, generated `transaction_id` `TXN-{uuid}`); call createSession with success/fail/cancel/IPN URLs; return gateway_url. Gateway error → payment stays pending-failed? **mark `failed`**, return 502.

## API Contract
### POST /api/v1/invoices/88/payments/online
Request: `{ "amount": "1500.00" }` (optional — defaults to outstanding)
Success — 201 `{ "data": { "payment_id": 42, "transaction_id": "TXN-...", "gateway_url": "https://sandbox.sslcommerz.com/..." } }`
Failures: invoice paid → 409; amount rules as 10.3 → 422; gateway unreachable/error → 502 `{ "message": "Payment gateway unavailable. Try again." }` (payment marked failed); unrelated student/parent → 404.

## Success Criteria
- [x] Interface-faked tests (no network); pending payment + tran_id persisted before redirect
- [x] Gateway failure handling (502 + payment failed); policy matrix; tests green

## Required Tests
1. init happy (fake returns URL); pending payment row asserted
2. paid invoice 409; gateway exception → 502 + failed payment
3. parent linked 201 / unrelated 404

## Out of Scope
IPN/settlement (10.5) · real sandbox calls (manual verification later).

## Completion Protocol
Set Status `done`, tick 10.4, log surprises.
