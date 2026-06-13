# Task 4.3 — Enrollment History Endpoint

| Field | Value |
|---|---|
| Phase | 4 — Students & Parents |
| Status | `done` |
| Depends on | 4.1 |
| Blocks | — |
| Spec references | `docs/api/students.md`, schema → `enrollments` |
| Estimated size | One sitting (small) |

## Background
Enrollments were created in 3.2/3.5. This exposes a student's class history (the promotion module appends to it in Phase 9).

## Objective
`GET /students/{id}/enrollments` returning class history, newest first, policy-guarded like the profile.

## What To Implement
`EnrollmentResource` (id, session name, class name, section name, roll_no, status) + route + controller method using `StudentPolicy::view`; eager-load session/class/section.

## API Contract
### GET /api/v1/students/9/enrollments — 200:
```json
{ "success": true, "message": "OK", "data": [
  { "id": 31, "session": "2026", "class": "Class 7", "section": "A", "roll_no": 12, "status": "active" },
  { "id": 18, "session": "2025", "class": "Class 6", "section": "A", "roll_no": 9, "status": "promoted" } ] }
```
Failures: student viewing someone else → 404; parent of the student → 200; unrelated parent → 404.

## Success Criteria
- [x] Ordered newest-first; policy semantics (self/linked-parent/staff) correct; no N+1; tests green

## Required Tests
1. staff view 200 ordered; 2. self 200; 3. unrelated student/parent 404; 4. linked parent 200

## Out of Scope
Creating/closing enrollments (3.5 created, Phase 9 mutates).

## Completion Protocol
Set Status `done`, tick 4.3, log surprises.
