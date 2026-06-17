# Task 13.3 — Students / Teachers / Assets / Fees Reports

| Field | Value |
|---|---|
| Phase | 13 — Reports |
| Status | `done` |
| Depends on | 13.2 |
| Blocks | 13.4 |
| Spec references | `docs/api/reports.md` |
| Estimated size | One sitting |

## Objective
Remaining four reports, same filter + envelope conventions.

## What To Implement
- `/reports/students`: totals by status, by class (active), new admissions in range.
- `/reports/teachers`: totals by status; attendance summary in range (present/late/absent/leave counts).
- `/reports/assets`: total value (11.4 rule), by status, additions in range (purchase_date).
- `/reports/fees`: per month-in-range — invoiced, collected, outstanding; plus by_class breakdown.

## API Contract
### GET /api/v1/reports/fees?period=yearly — 200:
```json
{ "success": true, "message": "OK", "data": {
  "filters": { "period": "yearly", "from": "2026-01-01", "to": "2026-12-31" },
  "totals": { "invoiced": "1800000.00", "collected": "1620000.00", "outstanding": "180000.00" },
  "by_month": [ { "month": "2026-06", "invoiced": "150000.00", "collected": "135000.00", "outstanding": "15000.00" } ],
  "by_class": [ { "class": "Class 7", "invoiced": "300000.00", "collected": "285000.00" } ] } }
```
### /reports/students — `data: { total: 412, by_status: { active: 398, tc: 9, inactive: 5 }, by_class: [ { class: "Class 7", count: 44 } ], new_admissions: 23 }`.
All: 422 filter errors; 403 without report.view.

## Success Criteria
- [x] All aggregates SQL-side; fees math reconciles with invoices/payments fixtures; tests green

## Required Tests
1. each report vs fixtures; 2. fees outstanding = invoiced − collected; 3. range edges (admission on boundary date counted)

## Out of Scope
PDFs (13.4) · dashboard (14.2).

## Completion Protocol
Set Status `done`, tick 13.3, log surprises.
