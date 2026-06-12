# Task 3.1 — Admission Tables & Models

| Field | Value |
|---|---|
| Phase | 3 — Admissions |
| Status | `todo` |
| Depends on | 2.3 |
| Blocks | 3.3–3.5 |
| Spec references | schema → `admission_applications`, `admission_previous_educations` |
| Estimated size | One sitting |

## Background
The paper admission form (bilingual, structured addresses, previous-education table) becomes two tables. No endpoints yet — this is pure schema + model groundwork so the public endpoint (3.3) stays small.

## What To Implement
1. Migration `admission_applications` — every column per schema exactly (bilingual name/parent fields, structured present address + father_mobile, permanent address bn + mother_mobile, permanent address en, birth_reg_no unique, dob, religion/nationality/caste, status default pending, rejection_reason, reviewed_by/at, application_no unique, branch_id, desired_class_id).
2. Migration `admission_previous_educations` — application_id FK cascade, exam_name, institution_name, gpa DECIMAL(4,2) null, passing_year YEAR null, board_roll, board_reg_no.
3. Models: `AdmissionApplication` (BelongsToBranch, HasMedia: collections `photo`, `documents`; `previousEducations()` hasMany; status enum class), `AdmissionPreviousEducation`.
4. `ApplicationNoGenerator` service: `APP-{branchCode}-{seq}` (per-branch sequence, race-safe).
5. Factories for both models.

## API Contract
None (no endpoints). Contract = schema fidelity.

## Success Criteria
- [ ] Migrations match `database-schema.md` column-for-column (types, lengths, nullability, uniques)
- [ ] Cascade delete: removing application removes its previous-education rows
- [ ] Generator produces unique sequential numbers under parallel calls

## Required Tests
1. factory creates valid rows  2. cascade delete  3. application_no uniqueness under concurrency (two simultaneous creates)  4. birth_reg_no unique 23000 handled

## Out of Scope
All endpoints (3.3, 3.4) · student conversion (3.5).

## Completion Protocol
Status `done`, tick 3.1, log surprises.
