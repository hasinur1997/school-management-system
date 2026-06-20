# Auth API — Phase 1

| Method | URI | Permission | Description |
|---|---|---|---|
| POST | /auth/login | Public | Login with email or phone + password |
| POST | /auth/logout | Authenticated | Revoke current token |
| GET | /auth/me | Authenticated | Current user, roles, permissions, branch, profile |
| PUT | /auth/profile | Authenticated | Update current user's account details |
| POST | /auth/photo | Authenticated | Replace current user's account photo |
| POST | /auth/change-password | Authenticated | Change own password |

## POST /auth/login
Request: `{ "login": "email or phone", "password": "string", "device_name": "string" }`
Response `data`: `{ "token": "...", "user": { id, name, email, phone, branch, roles: [], permissions: [] } }`
Errors: 422 invalid credentials; 403 inactive account.

## GET /auth/me
Response `data` includes `id`, `name`, `email`, `phone`, `branch_id`, `is_active`, `photo_url`, `roles`, and effective `permissions`.

## PUT /auth/profile
Request: `{ "name": "string", "email": "nullable email", "phone": "string" }` → 200 updated user resource. Email/phone must be unique; teacher accounts require an email and mirror the contact fields to the teacher profile.

## POST /auth/photo
Multipart request: `photo` jpg/png ≤2MB → 200 updated user resource with `photo_url`. Replaces the previous account photo.

## POST /auth/change-password
Request: `{ "current_password", "password", "password_confirmation" }` → 200. Other tokens for the user are revoked.
