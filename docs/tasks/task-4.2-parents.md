# Task 4.2 — Parents CRUD, Linking & /me/students

| Field | Value |
|---|---|
| Phase | 4 — Students & Parents |
| Status | `todo` |
| Depends on | 4.1 |
| Blocks | Parent-facing reads in attendance/results/fees |
| Spec references | `docs/api/students.md`, schema → `parents`, `parent_student` |
| Estimated size | One sitting |

## Background
Parents are created by admin (no self-registration — decided default) and linked to one or more students. The `ParentPolicy`/linked-students check built here is reused by every "parent sees own children" endpoint later.

## Objective
Parent management (`parent.manage`), link/unlink, credentials dispatch, and the parent's `/me/students`.

## What To Implement
1. Models/relations (`Parent` ↔ `Student` belongsToMany; note 3.5 may already create parents during approval — reuse).
2. Routes/controller/service:
   - `GET /parents` — paginated, search name/phone.
   - `POST /parents` — `{ name, phone, email?, relation, student_ids: [] }`; transaction: user (parent role) + parent + links; afterCommit credentials job. Validates all student_ids exist in branch.
   - `POST /parents/{id}/students` — `{ "student_id" }` link; duplicate link → 409.
   - `DELETE /parents/{id}/students/{student}` — unlink; 404 if not linked.
   - `GET /me/students` — parent role; linked students compact shape.
3. Helper used project-wide: `$parent->isLinkedTo($studentId)`.

## API Contract
### POST /api/v1/parents
Request: `{ "name": "Abdul Karim", "phone": "01811111111", "email": null, "relation": "father", "student_ids": [9] }`
Success — 201 parent + linked students array. Failures: duplicate phone (users) → 422; unknown/foreign-branch student_id → 422; invalid relation → 422.
### GET /me/students (as parent) — 200:
```json
{ "success": true, "message": "OK", "data": [ { "id": 9, "name_en": "Rahima Khatun", "class": "Class 7", "section": "A", "photo_url": "..." } ] }
```
Non-parent role → 403.

## Success Criteria
- [ ] Create with multi-link in one transaction; credentials queued after commit
- [ ] Link/unlink with 409/404 semantics; cross-branch student link impossible
- [ ] `/me/students` only for parent role; returns linked only
- [ ] Tests green

## Required Tests
1. create + links + queued mail (Queue::fake)
2. duplicate link 409; unlink missing 404; foreign-branch student 422
3. /me/students returns exactly linked; admin role on it → 403

## Out of Scope
Parent-facing attendance/results/fee endpoints (their modules).

## Completion Protocol
Set Status `done`, tick 4.2, log surprises.
