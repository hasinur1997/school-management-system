# Task 2.3 — Teacher List / Show / Update / Status / Photo

| Field | Value |
|---|---|
| Phase | 2 — Teacher Management |
| Status | `done` |
| Depends on | 2.2 |
| Blocks | Phase 3 start |
| Spec references | `docs/api/teachers.md` |
| Estimated size | One sitting |

## Background
Completes the teacher module: browse, inspect, edit, deactivate, photo via medialibrary (first medialibrary usage — install/configure it here).

## What To Implement
1. Install/configure spatie/laravel-medialibrary (publish migration unedited); `Teacher` implements `HasMedia`, collection `photo` (single file).
2. `GET /teachers` (`teacher.view`): paginated; filters `status`, `search` (name/email/phone/designation); sort name/joining_date; eager loads.
3. `GET /teachers/{id}` (`teacher.view`): profile + current-session assignments.
4. `PUT /teachers/{id}` (`teacher.update`): name, phone, designation, joining_date (email immutable — note why: login identity).
5. `PATCH /teachers/{id}/status` (`teacher.update`): active|inactive; inactive ⇒ user `is_active=false` + tokens revoked.
6. `POST /teachers/{id}/photo` (`teacher.update`): multipart `photo`, jpg/png ≤2MB, replaces existing.

## API Contract
### GET /teachers?status=active&search=rahim → 200 paginated, rows: `{ id, name, email, phone, designation, status, photo_url }`
### GET /teachers/{id} → 200: row + `"assignments": [ { class, section, subject } ]`
### PATCH /teachers/{id}/status `{ "status":"inactive" }` → 200; that teacher's login now → 403 (1.1 inactive rule).
### POST /teachers/{id}/photo (multipart) → 200 `{ ..., "photo_url":"https://..." }` · oversize/wrong type → 422 `errors.photo`.
All: out-of-branch id → 404; missing permission → 403.

## Success Criteria
- [ ] Filters/search/sort work; no N+1
- [ ] Deactivation kills login + tokens immediately
- [ ] Photo stored via medialibrary, old replaced
- [ ] Branch isolation holds (cross-branch 404)

## Required Tests
1. list filters + search  2. show with assignments  3. update; email change attempt 422  4. status flip blocks login  5. photo upload + replacement + validation  6. cross-branch 404

## Out of Scope
Teacher attendance (Phase 6).

## Completion Protocol
Status `done`, tick 2.3 + Phase 2, log surprises.
