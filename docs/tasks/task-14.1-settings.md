# Task 14.1 — Settings API

| Field | Value |
|---|---|
| Phase | 14 — Settings, Dashboard & Polish |
| Status | `done` |
| Depends on | 13.4 |
| Blocks | 14.2 |
| Spec references | `docs/api/settings.md`, schema → `settings` |
| Estimated size | One sitting |

## Background
Key–value store (branch_id null = global) feeding: SSLCommerz creds (10.4 falls back to config until now — swap to DB), partial-payment toggle (10.3), late threshold (6.2), invoice due day (10.2). Replaces the `SettingsRepository` stub with the real cached implementation.

## Objective
GET/PUT settings (`setting.manage`), public subset, write-only secrets, cache.

## What To Implement
1. Migration per schema; known-keys registry (global: school_name, school_logo, current_session_id, sslcommerz_store_id, sslcommerz_store_password, sslcommerz_sandbox, mail_from, sms_api_key; branch: partial_payment_enabled, late_fee_enabled, teacher_late_threshold, invoice_due_day) with type validation (bool/int/time/string).
2. `GET /settings` (`?branch_id=` super admin) — secrets returned as `{ "is_set": true }`. `PUT /settings` — bulk upsert `{ "settings": { key: value } }`; unknown key → 422; cache forget.
3. `GET /settings/public` — Public: school_name, logo URL, active branches, open classes per branch.
4. Wire consumers: 6.2 threshold, 10.2 due day, 10.3 partial toggle, 10.4 creds now read real settings.

## API Contract
### PUT /api/v1/settings
Request: `{ "settings": { "school_name": "Haji Jabed Ali Memorial School", "partial_payment_enabled": true, "teacher_late_threshold": "09:15" } }`
Success — 200 effective settings (secrets masked). Failures: unknown key → 422 `errors.settings.foo` "Unknown setting"; type mismatch (e.g., "yes" for bool) → 422.
### GET /settings — 200 `{ "global": { "school_name": "...", "sslcommerz_store_password": { "is_set": true } }, "branch": { "partial_payment_enabled": true, ... } }`.
### GET /settings/public — 200, no auth, no secrets ever.

## Success Criteria
- [ ] Secrets never readable (incl. public endpoint grep test); known-keys + types enforced
- [ ] Consumers verified reading DB values (toggle partial → 10.3 behavior flips in a test)
- [ ] Cache invalidation; tests green

## Required Tests
1. upsert + masking; unknown key/type 422
2. public subset content + no-secret assertion
3. consumer wiring: flip partial toggle changes 10.3 validation live

## Out of Scope
Settings UI conventions · late-fee behavior (open question — key exists, logic deferred).

## Completion Protocol
Set Status `done`, tick 14.1, log surprises.
