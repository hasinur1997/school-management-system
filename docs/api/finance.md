# Finance API — Phase 11

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /incomes | income.manage | Filters: category_id, from, to, search; sort: date, amount |
| POST | /incomes | income.manage | `{ title, amount, date, category_id?, description? }` |
| PUT/DELETE | /incomes/{id} | income.manage | **403 if `payment_id` set** (system-generated fee income is immutable) |
| GET | /expenses | expense.manage | Same filters as incomes |
| POST | /expenses | expense.manage | `{ item_name, amount, date, category_id?, description? }` |
| PUT/DELETE | /expenses/{id} | expense.manage | |
| GET | /assets | asset.manage | Filters: status, search; sort: value, purchase_date |
| POST | /assets | asset.manage | `{ name, value, description?, purchase_date?, status? }` |
| PUT/DELETE | /assets/{id} | asset.manage | |
| GET | /assets/summary | asset.manage | `{ total_value, count, by_status: {} }` — at-a-glance figure |
| GET/POST/PUT/DELETE | /categories[/{id}] | income.manage or expense.manage | `{ name, type: "income|expense" }` |

Income list rows include `is_system` (true when payment_id set) so clients can hide edit controls. Deleting a category in use → 409; rows keep `category_id` via RESTRICT.
