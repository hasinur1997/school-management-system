# API Specification — Index & Conventions

All endpoint contracts live in `docs/api/*.md`, one file per build unit. This file defines the conventions shared by every endpoint — they are written once here and never repeated in module files.

## Module Map

| File | Build Phase | Covers |
|---|---|---|
| `api/auth.md` | 1 | Login, logout, current user, password change |
| `api/academic-structure.md` | 1 | Branches, sessions, classes, sections, subjects, teacher assignments |
| `api/teachers.md` | 2 | Teacher CRUD, credential dispatch |
| `api/admissions.md` | 3 | Public admission form, review, approve/reject |
| `api/students.md` | 4 | Student profiles, parents, linking, enrollments |
| `api/student-attendance.md` | 5 | Daily attendance, attendance sheets |
| `api/teacher-attendance.md` | 6 | Check-in/out, IP whitelist |
| `api/exams-marks.md` | 7 | Exams, grading scale, mark entry |
| `api/results.md` | 8 | Result generation, publication, search, PDFs |
| `api/promotions.md` | 9 | Bulk and individual promotion |
| `api/fees-payments.md` | 10 | Fee structures, invoices, SSLCommerz, local payment, receipts |
| `api/finance.md` | 11 | Income, expenses, assets, categories |
| `api/documents.md` | 12 | ID cards, transfer certificates |
| `api/reports.md` | 13 | Filterable reports and exports |
| `api/settings.md` | 14 | Settings, dashboard |

## Base

- Base URL: `/api/v1`
- Format: JSON request and response (`Accept: application/json`), except PDF endpoints which stream `application/pdf`.
- Auth: `Authorization: Bearer {token}` (Sanctum). Public endpoints are explicitly marked `Public` — everything else requires a token.
- Permissions: each endpoint lists its required permission. Super admin bypasses all permission checks. Policies additionally restrict students/parents to their own records.
- Branch scope: non-super-admin requests are automatically scoped to the user's branch. Super admin may pass `branch_id` as a query/body parameter where noted; `branch_id=all` returns consolidated data.

## Response Envelope

Every JSON response:

```json
{ "success": true, "message": "OK", "data": { } }
```

Paginated lists:

```json
{
  "success": true,
  "message": "OK",
  "data": [ ],
  "meta": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8 }
}
```

## Pagination, Filtering, Sorting

- List endpoints accept `page` and `per_page` (default 15, max 100).
- Common filters are query parameters named after columns (`status`, `class_id`, `section_id`, `month`, `year`).
- Free-text search uses `search` (name, admission no, phone — per module).
- Sorting uses `sort` (column) and `direction` (`asc`/`desc`); each module lists allowed sort columns.

## Errors

| Status | Meaning | Body |
|---|---|---|
| 401 | Missing/invalid token | envelope, `success: false` |
| 403 | Permission or policy denied | envelope |
| 404 | Not found or out of branch scope | envelope |
| 422 | Validation failed | envelope + `errors: { field: [messages] }` |
| 409 | Business-rule conflict (duplicate attendance, already-paid invoice, replayed transaction) | envelope |
| 500 | Server error | envelope, no internals leaked |

Out-of-branch records return **404, not 403** — existence of other branches' data is not disclosed.

## Data Formats

- Dates: `YYYY-MM-DD`. Datetimes: ISO 8601 (`2026-06-11T08:30:00+06:00`).
- Money: decimal strings, two places — `"1500.00"`. Never floats.
- Statuses: lowercase string enums exactly as defined in `database-schema.md`.
- Files in: `multipart/form-data`. Files out: streamed PDFs with `Content-Disposition`.
- IDs: integers. Route model binding by `id` unless a module states otherwise.
