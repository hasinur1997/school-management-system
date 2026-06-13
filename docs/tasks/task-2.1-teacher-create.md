# Task 2.1 — Teacher Profile & Creation

| Field | Value |
|---|---|
| Phase | 2 — Teacher Management |
| Status | `done` |
| Depends on | 1.7 |
| Blocks | 2.2, 2.3, 1.8 FK, teacher attendance (Phase 6) |
| Spec references | `docs/api/teachers.md`, schema → `teachers` |
| Estimated size | One sitting |

## Background
Admins create teachers; the system makes the login. Required profile fields per requirements: name, email, phone, designation. Credential email is faked here and built for real in 2.2.

## Objective
POST /teachers creates user + profile + role atomically.

## What To Implement
1. Migration per schema: user_id unique FK, branch_id, `name` VARCHAR(150), `email` VARCHAR(150) unique, `phone` VARCHAR(20), `designation` VARCHAR(100), `joining_date` DATE null, `status` VARCHAR(20) default active, soft deletes. Add the deferred FK for `teacher_assignments.teacher_id` and `sections.class_teacher_id` here.
2. `Teacher` model with `BelongsToBranch`, `user()` relation; `TeacherResource`.
3. `TeacherService::create()` — transaction: user (random 10-char password, teacher role, branch from creator) + teacher profile; afterCommit: dispatch `SendCredentials` (job class stubbed, real mail in 2.2).
4. `POST /teachers` route, `permission:teacher.create`, `StoreTeacherRequest`.

## API Contract
### POST /api/v1/teachers — `teacher.create`
Request: `{ "name":"Rahim Uddin", "email":"rahim@school.com", "phone":"01712345678", "designation":"Assistant Teacher", "joining_date?":"2026-01-15" }`
Success — 201:
```json
{ "success": true, "message": "Teacher created. Credentials are being sent.",
  "data": { "id":5, "user_id":12, "name":"Rahim Uddin", "email":"rahim@school.com",
            "phone":"01712345678", "designation":"Assistant Teacher",
            "joining_date":"2026-01-15", "status":"active", "photo_url":null } }
```
Failures: duplicate email/phone → 422 (`errors.email`/`errors.phone`) · missing required field → 422 · no permission → 403.

## Success Criteria
- [ ] User + profile + role in one transaction (failure rolls back both)
- [ ] Teacher user can log in (1.1) and has teacher role permissions (1.2)
- [ ] Job dispatched afterCommit (Queue::fake assertion)
- [ ] Branch stamped from creator

## Required Tests
1. happy path 201, both rows exist, role attached
2. duplicate email 422; nothing persisted
3. forced service exception mid-transaction → no user, no teacher
4. created teacher can login; `/auth/me` shows teacher role
5. Queue::fake: SendCredentials dispatched

## Out of Scope
Real credential mail (2.2) · list/show/update/photo (2.3).

## Completion Protocol
Status `done`, tick 2.1, log surprises.
