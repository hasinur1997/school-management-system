# Task 3.2 — Student / Parent / Enrollment Tables (schema only)

| Field | Value |
|---|---|
| Phase | 3 — Admissions |
| Status | `todo` |
| Depends on | 3.1 |
| Blocks | 3.5 (approval writes these), all of Phase 4+ |
| Spec references | schema → `students`, `parents`, `parent_student`, `enrollments` |
| Estimated size | One sitting |

## Background
Approval (3.5) creates student, enrollment, and optional parent rows — so the tables must exist now. Management endpoints come in Phase 4; this task is migrations + models + factories only.

## What To Implement
1. Migrations exactly per schema: `students` (all bilingual/address fields mirroring applications + user_id unique, application_id nullable unique, admission_no unique, status default active, admitted_at, soft deletes), `parents` (user_id unique, name, phone, relation), `parent_student` (composite PK, cascades), `enrollments` (student_id, session_id, class_id, section_id, roll_no, status; uniques: (student,session) and (session,class,section,roll)).
2. Models: `Student` (BelongsToBranch, HasMedia `photo`, relations: user, application, enrollments, parents, currentEnrollment()); `ParentProfile` (table `parents` — avoid PHP keyword clash, note it); `Enrollment` (relations + status enum).
3. Factories for all.
4. `AdmissionNoGenerator`: `STU-{branchCode}-{year}-{seq}`.

## API Contract
None. Contract = schema fidelity + relations.

## Success Criteria
- [ ] All uniques present (duplicate roll in same session/class/section → constraint violation)
- [ ] `currentEnrollment()` returns the active/current-session enrollment
- [ ] Factories build a coherent student-with-enrollment graph

## Required Tests
1. unique constraints fire (student+session, roll composite, admission_no, birth_reg_no)
2. parent_student linking via factory
3. currentEnrollment resolution

## Out of Scope
Every endpoint (Phase 4) · approval flow (3.5).

## Completion Protocol
Status `done`, tick 3.2, log surprises.
