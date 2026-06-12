# Task 14.2 — Role-Aware Dashboard

| Field | Value |
|---|---|
| Phase | 14 — Settings, Dashboard & Polish |
| Status | `todo` |
| Depends on | 14.1 |
| Blocks | — |
| Spec references | `docs/api/settings.md` |
| Estimated size | One sitting |

## Objective
`GET /dashboard` — one endpoint, shape depends on the caller's role.

## What To Implement
`DashboardService` with per-role assemblers (all SQL aggregates, cacheable 5 min per user-role+branch):
- staff (admin/accountant/super): today's attendance % , pending admissions, month income/expense/net, unpaid invoice count, totals (students, teachers, asset value).
- teacher: checked-in today?, assigned classes, classes lacking today's attendance.
- student/parent: month attendance summary, unpaid invoices, latest published result (per linked child for parents).

## API Contract
### GET /api/v1/dashboard (admin) — 200:
```json
{ "success": true, "message": "OK", "data": { "role_view": "staff",
  "today_attendance_percent": 92.4, "pending_admissions": 6,
  "month": { "income": "152000.00", "expense": "90500.00", "net": "61500.00" },
  "unpaid_invoices": 37, "totals": { "students": 412, "teachers": 18, "asset_value": "385000.00" } } }
```
Teacher — `{ "role_view": "teacher", "checked_in": true, "classes": [...], "attendance_pending": [ { "class": "Class 7", "section": "A" } ] }`.
Student — `{ "role_view": "student", "attendance": {...}, "unpaid_invoices": [...], "latest_result": {...} }`.

## Success Criteria
- [ ] Three shapes correct vs fixtures; aggregates SQL-side; no cross-branch leakage; tests green

## Required Tests
1. each role shape; 2. parent sees per-child blocks for linked only; 3. numbers reconcile with module fixtures

## Out of Scope
Frontend widgets (Phase 2 of the project).

## Completion Protocol
Set Status `done`, tick 14.2, log surprises.
