# Admissions API — Phase 3

| Method | URI | Permission | Description |
|---|---|---|---|
| POST | /public/admissions | Public | Submit admission application |
| GET | /public/admissions/{application_no}/status | Public | Check status by application no + date_of_birth query param |
| GET | /admissions | admission.view | Paginated; filters: status (default pending), desired_class_id, search |
| GET | /admissions/{id} | admission.view | Full application incl. previous education + documents |
| POST | /admissions/{id}/approve | admission.approve | Convert to student |
| POST | /admissions/{id}/reject | admission.approve | `{ "rejection_reason": "string" }` |
| GET | /admissions/trash | admission.delete | Paginated trashed (soft-deleted) applications; filters: desired_class_id, search, from, to |
| DELETE | /admissions/{id} | admission.delete | Soft delete (move to trash) |
| POST | /admissions/bulk-delete | admission.delete | `{ "ids": ["public_id", …] }` → `{ "deleted": n }` |
| POST | /admissions/{id}/restore | admission.delete | Restore from trash |
| POST | /admissions/bulk-restore | admission.delete | `{ "ids": [...] }` → `{ "restored": n }` |
| DELETE | /admissions/{id}/force | admission.delete | Permanently delete a trashed application (irreversible) |
| POST | /admissions/bulk-force-delete | admission.delete | `{ "ids": [...] }` → `{ "deleted": n }` |

## Trash / soft delete

Admission applications are soft-deleted. `DELETE /admissions/{id}` stamps `deleted_at` and removes the row from the live queue (`GET /admissions`) while preserving its previous-education rows and media, so a restore brings it back intact. Trashed rows appear only in `GET /admissions/trash`, where `AdmissionListResource` carries a non-null `deleted_at`. Bulk endpoints take a list of `public_id`s; ids outside the caller's branch (or, for restore/force, ids that are not trashed) are silently skipped and excluded from the returned count. `DELETE /admissions/{id}/force` permanently removes the row — its previous-education children cascade via the FK and its media is deleted — and is irreversible. All trash routes are gated on `admission.delete` and branch-isolated (out-of-branch → 404).

## POST /public/admissions
multipart/form-data. Fields mirror `admission_applications` in `database-schema.md` (items 1–12 of the paper form), plus:
- `photo` (required, jpg/png ≤2MB), `documents[]` (optional, pdf/jpg/png ≤5MB each)
- `previous_educations[]` — array of `{ exam_name, institution_name, gpa?, passing_year?, board_roll?, board_reg_no? }` (form item 13)
- `branch_id`, `desired_class_id` (required; public endpoint validates both are active)
Response 201: `{ "application_no": "APP-..." }`. Rate-limited (10/hour/IP).

## POST /admissions/{id}/approve
Request: `{ "admission_no", "session_id", "class_id", "section_id", "roll_no", "create_parent_account": bool, "parent_relation?": "father|mother|guardian" }`
(The office-use box: class, admission no, year, section.)
Behavior, one transaction: create user (+ optional parent user linked via `parent_student`), create student (copy of application data), create enrollment, mark application approved; queue credentials dispatch after commit.
Errors: 409 already reviewed; 422 duplicate admission_no/roll_no.
