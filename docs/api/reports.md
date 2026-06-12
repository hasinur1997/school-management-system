# Reports API — Phase 13

All report endpoints share the filter contract:

`?period=weekly|monthly|yearly|custom` · `from`/`to` (required when custom) · `branch_id` (super admin; `all` = consolidated) · weekly = current ISO week, monthly = current month, yearly = current session year unless `from`/`to` given.

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /reports/income | report.view | Total + breakdown by category + day/month series |
| GET | /reports/expense | report.view | Same shape as income |
| GET | /reports/profit-loss | report.view | `{ income_total, expense_total, net }` + series |
| GET | /reports/students | report.view | Totals by class, status (active/tc/inactive), new admissions in range |
| GET | /reports/teachers | report.view | Totals by status, attendance summary in range |
| GET | /reports/assets | report.view | Total value, by status, additions in range |
| GET | /reports/fees | report.view | Collected vs outstanding by month/class |
| GET | /reports/{type}/pdf | report.view | Any report above as PDF (stream), same query params |

Response shape (income example):
`data: { filters: {...}, total: "152000.00", by_category: [ { category, amount } ], series: [ { date, amount } ] }`

All aggregation in SQL (`SUM`/`GROUP BY`); series granularity auto: daily for ranges ≤ 62 days, monthly otherwise.
