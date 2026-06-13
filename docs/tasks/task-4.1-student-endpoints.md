# Task 4.1 — Student Endpoints & Policy

| Field | Value |
|---|---|
| Phase | 4 — Students & Parents |
| Status | `done` |
| Depends on | 3.5 |
| Blocks | 4.2, 4.3, attendance/results/fees policies |
| Spec references | `docs/api/students.md`, schema → `students` |
| Estimated size | One sitting |

## Background
Students exist (created by admission approval in 3.5) but have no read/manage endpoints yet. This task adds them, plus the `StudentPolicy` establishing the pattern every personal-data module reuses: staff with `student.view` see branch students; a student sees only self.

## Objective
List/show/update/status/photo endpoints with correct authorization for staff vs. student roles.

## What To Implement
1. `StudentResource` (full, for show) and `StudentListResource` (compact: id, admission_no, name_en, name_bn, current class/section/roll via active enrollment, status, photo URL).
2. `StudentPolicy::view` — true if user has `student.view`; or user is the student. Register policy.
3. Routes/controller/service:
   - `GET /students` — `permission:student.view`; filters `class_id, section_id, session_id, status`; `search` over name_en/name_bn/admission_no/father_mobile; paginated; eager-load active enrollment + class/section (no N+1).
   - `GET /students/{id}` — policy `view`.
   - `PUT /students/{id}` — `permission:student.update`; all profile fields editable except `admission_no`, `birth_reg_no`.
   - `PATCH /students/{id}/status` — `{ "status": "active|inactive" }`; `tc` rejected here (422, "Use the TC module").
   - `POST /students/{id}/photo` — multipart `photo` jpg/png ≤2MB, replaces medialibrary collection.

## API Contract
### GET /api/v1/students?class_id=7&search=rah — 200:
```json
{ "success": true, "message": "OK",
  "data": [ { "id": 9, "admission_no": "MP-2026-0009", "name_en": "Rahima Khatun", "name_bn": "রহিমা খাতুন", "class": "Class 7", "section": "A", "roll_no": 12, "status": "active", "photo_url": "..." } ],
  "meta": { "current_page": 1, "per_page": 15, "total": 1, "last_page": 1 } }
```
### GET /students/{id} — 200 full bilingual profile (all schema fields + enrollments summary + photo URL). Student requesting another student → **404** (policy + scope). Failures: unknown id 404.
### PUT /students/{id} — 200 updated resource; attempt to change admission_no → field ignored or 422 `errors.admission_no` (choose 422, explicit).
### PATCH status — 200; `"tc"` → 422.
### POST photo — 200 with new `photo_url`; >2MB or wrong type → 422.

## Success Criteria
- [ ] Compact list vs. full show shapes; filters + search work; zero N+1 under strict mode
- [ ] Student role: own show 200, others 404; cannot access list (403)
- [ ] admission_no immutable; tc status blocked here
- [ ] Tests green

## Required Tests
1. list filters + search + pagination; strict-mode N+1 guard
2. student self-view 200; other student 404; list 403
3. update happy; admission_no change 422; status tc 422
4. photo upload + replace; invalid file 422

## Out of Scope
Parents (4.2) · enrollment history endpoint (4.3) · TC issuance (12.3).

## Completion Protocol
Set Status `done`, tick 4.1, log surprises.
