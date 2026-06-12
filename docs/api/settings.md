# Settings & Dashboard API — Phase 14

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /settings | setting.manage | All settings: global + current branch (super admin: `?branch_id=`) |
| PUT | /settings | setting.manage | Bulk upsert: `{ "settings": { key: value } }` |
| GET | /settings/public | Public | Safe subset for the public admission page: school name, logo URL, branches, open classes |
| GET | /dashboard | Authenticated | Role-aware summary |

## Known setting keys
Global: `school_name`, `school_logo` (media), `current_session_id`, `sslcommerz_store_id`, `sslcommerz_store_password` (write-only — never returned), `sslcommerz_sandbox` (bool), `mail_from`, `sms_api_key` (write-only).
Per-branch: `partial_payment_enabled` (bool), `late_fee_enabled` (bool), `teacher_late_threshold` (HH:MM), `invoice_due_day` (1–28).
Unknown keys → 422. Secrets are write-only: GET returns `"is_set": true` instead of the value. Settings cache invalidated on PUT.

## GET /dashboard
Role-aware `data`:
- admin/accountant/super admin: today's student attendance %, pending admissions, this month income/expense/net, unpaid invoice count, totals (students, teachers, asset value)
- teacher: own check-in status today, assigned classes, classes with attendance not yet taken today
- student/parent: attendance summary this month, unpaid invoices, latest published result
