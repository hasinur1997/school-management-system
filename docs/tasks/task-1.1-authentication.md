# Task 1.1 — Authentication (Sanctum)

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `done` |
| Depends on | Nothing (first task of the project) |
| Blocks | Every other task (all protected endpoints need auth) |
| Spec references | `docs/api/auth.md`, `docs/api-spec.md` (envelope/errors), `docs/database-schema.md` → `users` |
| Estimated size | One sitting |

## Background

This is the first implementation task. The Laravel project is freshly set up; nothing exists yet. This task establishes token-based authentication that every later task's protected endpoints will sit behind. Roles and permissions come in Task 1.2 — this task only proves identity, it does not authorize actions.

## Objective

A user can log in with email **or** phone + password and receive a Sanctum token; use that token to fetch their identity; change their password; and log out. Inactive users cannot log in. All responses use the standard envelope.

## What To Implement

1. **Migration** — `users` table exactly per `database-schema.md`: `branch_id` (nullable FK, indexed), `name` VARCHAR(150), `email` VARCHAR(150) unique nullable, `phone` VARCHAR(20) unique nullable, `password`, `is_active` BOOLEAN default true, `last_login_at` nullable, soft deletes, timestamps. Install Sanctum (`personal_access_tokens` migration as published — do not edit).
2. **Model** — `User`: `HasApiTokens`, `SoftDeletes`, hidden password, casts (`is_active` → bool, `last_login_at` → datetime). (No `BelongsToBranch` yet — the scope is Task 1.7.)
3. **Routes** — in `routes/api.php` under `/api/v1`:
   - `POST /auth/login` (public, throttled 5/min)
   - `POST /auth/logout` (auth:sanctum)
   - `GET /auth/me` (auth:sanctum)
   - `POST /auth/change-password` (auth:sanctum)
4. **Form Requests** — `LoginRequest`, `ChangePasswordRequest`.
5. **Service** — `AuthService`: `login()` resolves the `login` field against email or phone, verifies password, rejects inactive users, updates `last_login_at`, issues token named by `device_name`. `changePassword()` verifies current password, updates, revokes all other tokens.
6. **Resources** — `UserResource` (id, name, email, phone, branch_id, is_active). Roles/permissions arrays return `[]` for now; Task 1.2 fills them.
7. **Envelope plumbing** — a base `ApiController` (or response helper) producing `{ success, message, data }`, plus exception handler rendering for 401/403/404/422 in the same envelope. Every later task reuses this.

## API Contract

### POST /api/v1/auth/login — Public

Request:
```json
{ "login": "teacher@school.com", "password": "secret123", "device_name": "web" }
```
`login` may be an email or a phone number. All three fields required.

Success — 200:
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|xxxxxxxxxxxxxxxxxxxx",
    "user": {
      "id": 5, "name": "Rahim Uddin",
      "email": "teacher@school.com", "phone": "01712345678",
      "branch_id": 1, "is_active": true,
      "roles": [], "permissions": []
    }
  }
}
```

Failures:
- Wrong credentials — 422:
```json
{ "success": false, "message": "The provided credentials are incorrect.", "errors": { "login": ["The provided credentials are incorrect."] } }
```
- Inactive account — 403:
```json
{ "success": false, "message": "This account is inactive. Contact the administration." }
```
- Missing fields — 422 with per-field `errors`.
- Too many attempts — 429 standard throttle response.

### GET /api/v1/auth/me — Bearer token

Success — 200: `data` = the user object above (same shape as login's `user`). Task 1.2 extends it with real roles/permissions; later phases add the `teacher`/`student`/`parent` profile object — **not in this task**.

Failure — missing/invalid token — 401:
```json
{ "success": false, "message": "Unauthenticated." }
```

### POST /api/v1/auth/change-password — Bearer token

Request:
```json
{ "current_password": "secret123", "password": "newSecret456", "password_confirmation": "newSecret456" }
```
Rules: `current_password` must match; `password` min 8, confirmed, different from current.

Success — 200:
```json
{ "success": true, "message": "Password changed successfully", "data": null }
```
Side effect: all the user's **other** tokens are revoked; the current token stays valid.

Failures: wrong current password — 422 (`errors.current_password`); validation — 422.

### POST /api/v1/auth/logout — Bearer token

Success — 200:
```json
{ "success": true, "message": "Logged out", "data": null }
```
Side effect: only the current token is revoked. Re-using it afterwards → 401.

## Success Criteria (all must hold)

- [ ] Login works with email AND with phone, returns token + user in the envelope
- [ ] Wrong password → 422, inactive user → 403, no token → 401 — all in the envelope
- [ ] Login is rate-limited (5/min per IP)
- [ ] `last_login_at` is updated on successful login
- [ ] Change-password revokes other tokens but not the current one
- [ ] Logout revokes only the current token
- [ ] No endpoint returns a raw model or a non-envelope shape
- [ ] `php artisan test` fully green

## Required Tests (Feature)

1. login with email succeeds / with phone succeeds
2. login with wrong password → 422 envelope with `errors.login`
3. login as inactive user → 403
4. login throttled after 5 rapid attempts → 429
5. `me` with valid token → 200 user shape; without token → 401
6. change-password: happy path; wrong current → 422; other tokens revoked, current still works
7. logout revokes current token (subsequent `me` → 401)

## Out of Scope

Roles/permissions (1.2) · branch scoping (1.7) · profile objects in `/me` (Phases 2–4) · password reset/forgot-password (not in Phase 1 specs — raise as an open question if needed).

## Completion Protocol

When done: set Status above to `done`, tick Task 1.1 in `docs/progress-tracker.md`, and note anything unexpected in the tracker's Decisions Log.
