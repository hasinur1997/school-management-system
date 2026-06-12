# Documents API — Phase 12 (ID Cards & TC)

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /students/{id}/id-card | idcard.generate | Single ID card PDF (stream) |
| POST | /id-cards/batch | idcard.generate | Whole class/section batch |
| GET | /id-cards/batch/{batch_id} | idcard.generate | Poll batch status / get download URL |
| POST | /students/{id}/tc | tc.issue | Issue transfer certificate |
| GET | /tcs | tc.view | List; filters: from, to, search |
| GET | /tcs/{id} | tc.view | TC detail |
| GET | /tcs/{id}/pdf | tc.view | Stored TC PDF (download) |

## ID card contents
Photo, name_en, admission_no, current class/section/roll, session, branch name + logo, validity (session end date). Rendered on demand — no table.

## POST /id-cards/batch
Request: `{ "class_id", "section_id?", "session_id" }`. Queued job builds one merged PDF (chunked), stores temporarily, returns `{ "batch_id" }` (202). Poll endpoint returns `{ status: "processing|done", url? }`.

## POST /students/{id}/tc
Request: `{ "reason", "issue_date" }`.
One transaction: create TC (generated tc_no) → student status `tc` → active enrollment status `tc` → render TC PDF and persist via medialibrary (the one stored PDF — legal record).
Effects (enforced by scopes, see invariants): excluded from attendance sheets, future invoice generation, and promotion. Unpaid past invoices remain visible.
Errors: 409 TC already issued; 422 reason required.
