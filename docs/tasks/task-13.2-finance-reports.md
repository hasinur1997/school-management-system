# Task 13.2 — Income / Expense / Profit-Loss Reports

| Field | Value |
|---|---|
| Phase | 13 — Reports |
| Status | `todo` |
| Depends on | 13.1 |
| Blocks | 13.4 |
| Spec references | `docs/api/reports.md` |
| Estimated size | One sitting |

## Objective
Three endpoints (`report.view`) — pure SQL aggregation, shared shape.

## What To Implement
`GET /reports/income`, `/reports/expense`: total, by_category (LEFT JOIN categories, null → "Uncategorized"), series at resolver granularity (DATE() or DATE_FORMAT month grouping). `/reports/profit-loss`: income_total, expense_total, net, combined series `{ date, income, expense }`. Consolidated (`branch_id=all`) adds `by_branch` array.

## API Contract
### GET /api/v1/reports/income?period=monthly — 200:
```json
{ "success": true, "message": "OK", "data": {
  "filters": { "period": "monthly", "from": "2026-06-01", "to": "2026-06-30", "branch": "Madani PathShala" },
  "total": "152000.00",
  "by_category": [ { "category": "Tuition Fee", "amount": "120000.00" }, { "category": "Uncategorized", "amount": "32000.00" } ],
  "series": [ { "date": "2026-06-01", "amount": "5000.00" } ] } }
```
### /reports/profit-loss — `data: { income_total, expense_total, net: "61500.00", series: [...] }` (net may be negative → "-3200.00").
Failures: filter 422s per 13.1; teacher without report.view → 403.

## Success Criteria
- [ ] Zero PHP-side summation (query log assertion of GROUP BY); fee incomes included; series granularity switch at 62 days; tests green

## Required Tests
1. totals + category breakdown vs seeded fixtures (incl. system fee income)
2. net negative case; daily vs monthly series switch
3. branch filter + all (super admin); 403 non-permitted

## Out of Scope
PDF export (13.4).

## Completion Protocol
Set Status `done`, tick 13.2, log surprises.
