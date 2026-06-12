# Auth API — Phase 1

| Method | URI | Permission | Description |
|---|---|---|---|
| POST | /auth/login | Public | Login with email or phone + password |
| POST | /auth/logout | Authenticated | Revoke current token |
| GET | /auth/me | Authenticated | Current user, roles, permissions, branch, profile |
| POST | /auth/change-password | Authenticated | Change own password |

## POST /auth/login
Request: `{ "login": "email or phone", "password": "string", "device_name": "string" }`
Response `data`: `{ "token": "...", "user": { id, name, email, phone, branch, roles: [], permissions: [] } }`
Errors: 422 invalid credentials; 403 inactive account.

## GET /auth/me
Response `data` includes the role-specific profile (`teacher`, `student`, or `parent` object with linked students) so clients need one call after login.

## POST /auth/change-password
Request: `{ "current_password", "password", "password_confirmation" }` → 200. Other tokens for the user are revoked.
