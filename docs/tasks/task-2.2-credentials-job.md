# Task 2.2 — Credential Dispatch Job

| Field | Value |
|---|---|
| Phase | 2 — Teacher Management |
| Status | `todo` |
| Depends on | 2.1 |
| Blocks | 3.5 (reuses this job for students/parents) |
| Spec references | `docs/api/teachers.md`, `docs/architecture-context.md` → Background Work |
| Estimated size | One sitting |

## Background
Credentials must reach teachers (and later students/parents) by email without blocking the request. The job must be generic: any user + plaintext password (passed transiently, never stored).

## Objective
A queued, retry-safe `SendCredentials` job + mailable; resend endpoint.

## What To Implement
1. `SendCredentials` job (queued, `tries=3`, `backoff=[60,300]`): takes user id + plaintext password + role label; sends `CredentialsMail` (login URL hint, login identifier, password, "change after first login").
2. Wire into 2.1's create flow for real.
3. `POST /teachers/{id}/resend-credentials` — `permission:teacher.create`: regenerate password, revoke all tokens, dispatch job.
4. Queue: database driver configured; failed-jobs table migrated.

## API Contract
### POST /api/v1/teachers/{id}/resend-credentials — `teacher.create`
No body. Success — 200:
```json
{ "success": true, "message": "New credentials are being sent to the teacher.", "data": null }
```
Failures: unknown/out-of-branch id → 404 · inactive teacher → 409 `"message": "Teacher is inactive."` · no permission → 403.

## Success Criteria
- [ ] Mail contains identifier + new password; password not persisted in plaintext anywhere
- [ ] Resend revokes existing tokens (old token → 401 afterwards)
- [ ] Retries/backoff configured; failure lands in failed_jobs

## Required Tests
1. Mail::fake — mailable queued with correct recipient on create + resend
2. resend: old token now 401, new password logs in
3. inactive teacher → 409
4. job retry config asserted

## Out of Scope
SMS channel (open question #2 in tracker) — email only for now.

## Completion Protocol
Status `done`, tick 2.2, log surprises.
