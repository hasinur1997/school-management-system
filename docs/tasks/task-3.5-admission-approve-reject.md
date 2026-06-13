# Task 3.5 — Admission Approve / Reject

| Field | Value |
|---|---|
| Phase | 3 — Admissions |
| Status | `done` |
| Depends on | 3.2, 3.4, 2.2 (credentials job) |
| Blocks | Phase 4 |
| Spec references | `docs/api/admissions.md` |
| Estimated size | One sitting |

## Background
The most important transaction of the admission pipeline: converting an approved application into a real student with login, enrollment (office-use box: admission no, class, section, roll, session), and optionally a linked parent account.

## What To Implement
1. `POST /admissions/{id}/approve` — `permission:admission.approve`; `ApproveAdmissionRequest`: session_id, class_id (must equal or override desired class — allow override), section_id (of class), roll_no (free within composite unique), admission_no optional (auto-generate when absent), create_parent_account bool, parent_relation required_if.
2. `AdmissionService::approve()` — ONE transaction: student user (email null, phone = father_mobile, random password) → student row (copy all application data; copy photo media) → enrollment (status active) → optional parent user + profile + `parent_student` link → application status approved + reviewed_by/at. afterCommit: SendCredentials for student (and parent if created).
3. `POST /admissions/{id}/reject` — `{ "rejection_reason": "string|required|max:255" }`; sets status + reviewer.
4. Both: 409 if application already reviewed.

## API Contract
### POST /admissions/{id}/approve
Request: `{ "session_id":1, "class_id":3, "section_id":7, "roll_no":12, "admission_no?":"STU-JA-2026-0012", "create_parent_account":true, "parent_relation":"father" }`
Success — 200:
```json
{ "success": true, "message": "Admission approved. Student account created.",
  "data": { "student": { "id":31, "admission_no":"STU-JA-2026-0012", "name_en":"Karim Hossain",
            "enrollment": { "session":"2026", "class":"Class 7", "section":"A", "roll_no":12 } },
            "parent_created": true } }
```
Failures: already reviewed → 409 `"message":"Application has already been reviewed."` · duplicate roll in section → 422 `errors.roll_no` · duplicate admission_no → 422 · section not of class → 422 · no permission → 403.
### POST /admissions/{id}/reject `{ "rejection_reason":"Incomplete documents" }` → 200 `"message":"Application rejected."` · already reviewed → 409.

## Success Criteria
- [ ] One transaction: any failure leaves NO user/student/enrollment/parent rows
- [ ] Application data copied faithfully (spot-check bilingual + address fields); photo media copied
- [ ] Credentials job(s) dispatched afterCommit
- [ ] Re-review blocked (409); rejected application can never be approved later (409)
- [ ] Public status endpoint (3.3) now reflects approved/rejected

## Required Tests
1. approve happy path: all five row types exist, data matches application
2. approve with parent: link row exists; parent `/me/students` will work (assert pivot)
3. mid-transaction failure → full rollback
4. duplicate roll / already-reviewed / section-mismatch errors
5. reject happy path + 409 on second review
6. Queue::fake credentials dispatched (student, and parent when created)

## Out of Scope
Student/parent management endpoints (Phase 4) · SMS notification.

## Completion Protocol
Status `done`, tick 3.5 + Phase 3, log surprises.
