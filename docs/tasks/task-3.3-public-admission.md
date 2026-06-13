# Task 3.3 — Public Admission Submission

| Field | Value |
|---|---|
| Phase | 3 — Admissions |
| Status | `done` |
| Depends on | 3.1 |
| Blocks | 3.4 |
| Spec references | `docs/api/admissions.md` |
| Estimated size | One sitting |

## Background
The only public write endpoint in the system. Mirrors the paper form items 1–13. Must be hardened: rate-limited, strictly validated, no auth context (so branch comes from input, validated as active).

## What To Implement
1. `POST /public/admissions` — no auth, `throttle:10,60` (10/hour/IP). multipart.
2. `StoreAdmissionRequest`: all required fields per schema; `photo` required jpg/png ≤2MB; `documents[]` optional pdf/jpg/png ≤5MB each, max 5; `previous_educations` optional array of objects; `branch_id` exists+active; `desired_class_id` exists, belongs to that branch, active.
3. `AdmissionService::submit()` — transaction: application + previous-education rows + media.
4. `GET /public/admissions/{application_no}/status?date_of_birth=YYYY-MM-DD` — both must match (dob acts as the shared secret), returns status only.

## API Contract
### POST /api/v1/public/admissions — Public
Request (multipart): `name_bn, name_en, father_name_bn, father_name_en, father_nid?, mother_name_bn, mother_name_en, mother_nid?, present_village, present_post_office, present_upazila, present_district, father_mobile, permanent_*_bn ×4, mother_mobile?, permanent_*_en ×4, birth_reg_no, date_of_birth, religion, nationality, caste?, branch_id, desired_class_id, photo, documents[]?, previous_educations[][exam_name|institution_name|gpa|passing_year|board_roll|board_reg_no]?`
Success — 201:
```json
{ "success": true, "message": "Application submitted successfully.",
  "data": { "application_no": "APP-JA-00042", "status": "pending" } }
```
Failures: validation → 422 per-field · duplicate birth_reg_no → 422 `errors.birth_reg_no` ("An application with this birth registration number already exists.") · inactive branch/class → 422 · 11th request in an hour → 429.
### GET /public/admissions/APP-JA-00042/status?date_of_birth=2014-03-09
200: `{ "data": { "application_no":"APP-JA-00042", "status":"pending|approved|rejected", "rejection_reason": "...|null" } }` · no/mismatched dob → 404 (do not reveal existence).

## Success Criteria
- [x] Anonymous submission with photo + docs + previous educations persists atomically
- [x] Rate limit, dup birth_reg_no, inactive-branch guards
- [x] Status check requires matching dob; mismatch → 404

## Required Tests
1. full happy path incl. media + 2 previous educations  2. each guard (422/429 cases)  3. status check match / mismatch 404  4. transaction rollback on media failure

## Out of Scope
Admin review (3.4) · approval (3.5).

## Completion Protocol
Status `done`, tick 3.3, log surprises.
