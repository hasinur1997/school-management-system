# Task 3.4 — Admission Review (List / Show)

| Field | Value |
|---|---|
| Phase | 3 — Admissions |
| Status | `done` |
| Depends on | 3.3 |
| Blocks | 3.5 |
| Spec references | `docs/api/admissions.md` |
| Estimated size | One sitting |

## Background
Admins triage pending applications. Branch scope applies (1.7): an admin sees only their branch's applications.

## What To Implement
1. `GET /admissions` — `permission:admission.view`; default filter `status=pending`; filters: status, desired_class_id, from/to (created date), `search` (name_en, name_bn, application_no, father_mobile, birth_reg_no); paginated, compact rows.
2. `GET /admissions/{id}` — full detail: all form fields, photo/document URLs, previous educations.

## API Contract
### GET /admissions?status=pending&search=karim → 200 paginated
Row: `{ "id":7, "application_no":"APP-JA-00042", "name_en":"Karim Hossain", "desired_class": {"id":3,"name":"Class 7"}, "father_mobile":"017...", "status":"pending", "submitted_at":"2026-06-10T11:20:00+06:00" }`
### GET /admissions/{id} → 200
`data`: every application field + `"photo_url"`, `"documents":[{name,url}]`, `"previous_educations":[...]`, `"reviewed_by":null, "reviewed_at":null`.
Failures: out-of-branch/unknown id → 404 · no permission → 403.

## Success Criteria
- [x] Default pending view; all filters + search work; no N+1
- [x] Detail exposes media URLs and child rows
- [x] Branch isolation (404 cross-branch)

## Required Tests
1. default status filter  2. each filter + search  3. detail completeness  4. cross-branch 404  5. N+1 guard

## Out of Scope
Approve/reject (3.5).

## Completion Protocol
Status `done`, tick 3.4, log surprises.
