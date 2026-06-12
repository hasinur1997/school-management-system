# Admissions API — Phase 3

| Method | URI | Permission | Description |
|---|---|---|---|
| POST | /public/admissions | Public | Submit admission application |
| GET | /public/admissions/{application_no}/status | Public | Check status by application no + date_of_birth query param |
| GET | /admissions | admission.view | Paginated; filters: status (default pending), desired_class_id, search |
| GET | /admissions/{id} | admission.view | Full application incl. previous education + documents |
| POST | /admissions/{id}/approve | admission.approve | Convert to student |
| POST | /admissions/{id}/reject | admission.approve | `{ "rejection_reason": "string" }` |

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
