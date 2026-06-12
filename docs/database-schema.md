# Database Schema

> MySQL 8 · Laravel migrations. Companion to `project-overview.md` and `architecture-context.md`.

## Design Analysis

Key decisions derived from the requirements:

1. **One `users` table for all six roles.** Authentication is uniform (Sanctum); role-specific data lives in profile tables (`teachers`, `students`, `parents`). Roles/permissions come from spatie tables, not a `role` column.
2. **Admission data is kept separate from student data.** The public form writes to `admission_applications` (+ a child table for previous education). On approval, a `students` row and `users` row are created and the application is linked, preserving the original submission as an audit record.
3. **Bilingual fields.** The paper form captures Bangla and English for names and addresses, so those are paired `_bn` / `_en` columns. Addresses are structured (village / post office / upazila / district), not free text.
4. **Enrollment is session-based.** A student's membership in a class+section for an academic session is an `enrollments` row. Promotion = closing the current enrollment and creating the next one; `promotions` logs the action. This gives full class history per student.
5. **Results are persisted snapshots.** `exam_results` (per exam) and `annual_results` (25/25/50 weighted) are computed and stored at publication so they are immutable against later grading-scale edits.
6. **Fees:** `fee_structures` defines the monthly amount per class/branch/session → `invoices` are generated monthly per student → `payments` settle invoices → a paid fee auto-creates an `incomes` row (linked back to the payment).
7. **Branch scoping:** every branch-owned table carries `branch_id` (FK → `branches.id`, indexed). Global tables (users? no — users are branch-scoped except super admin with NULL) are noted explicitly.
8. **Statuses are string enums** kept in PHP enum classes; stored as `VARCHAR` with CHECK-like app validation (avoids MySQL ENUM migration pain).

### Conventions (apply to every table unless stated)

- `id` — `BIGINT UNSIGNED`, PK, auto-increment
- `created_at`, `updated_at` — `TIMESTAMP NULL`
- FK columns — `BIGINT UNSIGNED`, indexed, `ON DELETE RESTRICT` unless noted
- Money — `DECIMAL(12,2)`
- Soft deletes (`deleted_at TIMESTAMP NULL`) only where stated

### Relationship Map

```
branches 1─* users, students, teachers, school_classes, exams, invoices, incomes, expenses, assets, settings…
users 1─1 teachers | students | parents (profile tables)
parents *─* students (parent_student)
academic_sessions 1─* enrollments, exams, fee_structures
school_classes 1─* sections, subjects, fee_structures
students 1─* enrollments, invoices
enrollments: student × session × class × section ; 1─* student_attendances, marks, exam_results ; 1─0..1 annual_results
exams 1─* marks, exam_results
invoices 1─* payments ; payments 1─1 incomes (fee income)
admission_applications 1─* admission_previous_educations ; 1─0..1 students
```

> Tables without their own `branch_id` (`sections`, `subjects`, `enrollments`, `student_attendances`, `marks`, `exam_results`, `annual_results`) derive their branch through their parent (`class.branch_id` or `student.branch_id`); `BranchScope` applies to them via those relations, not a local column.

---

## 1. Core & Academic Structure

### branches
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(150) | e.g. "Madani PathShala", "Jabed Ali" |
| code | VARCHAR(20) | unique; used in admission/receipt numbers |
| address | VARCHAR(255) | nullable |
| phone | VARCHAR(20) | nullable |
| email | VARCHAR(150) | nullable |
| is_active | BOOLEAN | default true |

### users
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches; **NULL only for super admin** |
| name | VARCHAR(150) | display name (English) |
| email | VARCHAR(150) | unique, nullable (students may have none) |
| phone | VARCHAR(20) | unique, nullable; login alternative |
| password | VARCHAR(255) | bcrypt |
| is_active | BOOLEAN | default true |
| last_login_at | TIMESTAMP | nullable |
| deleted_at | TIMESTAMP | soft delete |

> Roles/permissions: standard **spatie/laravel-permission** tables (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) — package defaults, not redefined here. Sanctum's `personal_access_tokens`, medialibrary's `media`, and queue tables are likewise package defaults.

### academic_sessions
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| name | VARCHAR(20) | e.g. "2026" |
| start_date | DATE | |
| end_date | DATE | |
| is_current | BOOLEAN | exactly one true (app-enforced) |

### school_classes
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| name | VARCHAR(50) | e.g. "Class 7" |
| numeric_level | TINYINT UNSIGNED | 1–12; drives "next class" in promotion |
| is_active | BOOLEAN | default true |

Unique: (`branch_id`, `numeric_level`).

### sections
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| class_id | BIGINT UNSIGNED | FK → school_classes |
| name | VARCHAR(30) | e.g. "A" |
| class_teacher_id | BIGINT UNSIGNED | FK → teachers, nullable |

Unique: (`class_id`, `name`).

### subjects
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| class_id | BIGINT UNSIGNED | FK → school_classes |
| name | VARCHAR(100) | |
| code | VARCHAR(20) | nullable |
| full_marks | SMALLINT UNSIGNED | default 100 |
| pass_marks | SMALLINT UNSIGNED | default 33 |

Unique: (`class_id`, `name`).

### teacher_assignments
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| teacher_id | BIGINT UNSIGNED | FK → teachers |
| session_id | BIGINT UNSIGNED | FK → academic_sessions |
| class_id | BIGINT UNSIGNED | FK → school_classes |
| section_id | BIGINT UNSIGNED | FK → sections, nullable (whole class) |
| subject_id | BIGINT UNSIGNED | FK → subjects, nullable (class duty, e.g. attendance only) |

Unique: (`teacher_id`, `session_id`, `class_id`, `section_id`, `subject_id`).

---

## 2. People

### teachers
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | FK → users, unique |
| branch_id | BIGINT UNSIGNED | FK → branches |
| name | VARCHAR(150) | |
| email | VARCHAR(150) | unique |
| phone | VARCHAR(20) | |
| designation | VARCHAR(100) | e.g. "Assistant Teacher" |
| joining_date | DATE | nullable |
| status | VARCHAR(20) | active \| inactive (default active) |
| deleted_at | TIMESTAMP | soft delete |

Photo via medialibrary collection `photo`.

### admission_applications
Public form submission — mirrors the paper form.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| application_no | VARCHAR(30) | unique, generated e.g. `APP-{branch}-{seq}` |
| desired_class_id | BIGINT UNSIGNED | FK → school_classes (form item 12) |
| name_bn | VARCHAR(150) | item 1 |
| name_en | VARCHAR(150) | item 1 |
| father_name_bn | VARCHAR(150) | item 2 |
| father_name_en | VARCHAR(150) | item 2 |
| father_nid | VARCHAR(20) | item 3, nullable |
| mother_name_bn | VARCHAR(150) | item 4 |
| mother_name_en | VARCHAR(150) | item 4 |
| mother_nid | VARCHAR(20) | item 5, nullable |
| present_village | VARCHAR(100) | item 6 |
| present_post_office | VARCHAR(100) | |
| present_upazila | VARCHAR(100) | |
| present_district | VARCHAR(100) | |
| father_mobile | VARCHAR(20) | item 6 |
| permanent_village_bn | VARCHAR(100) | item 7 |
| permanent_post_office_bn | VARCHAR(100) | |
| permanent_upazila_bn | VARCHAR(100) | |
| permanent_district_bn | VARCHAR(100) | |
| mother_mobile | VARCHAR(20) | item 7 |
| permanent_village_en | VARCHAR(100) | item 8 |
| permanent_post_office_en | VARCHAR(100) | |
| permanent_upazila_en | VARCHAR(100) | |
| permanent_district_en | VARCHAR(100) | |
| birth_reg_no | VARCHAR(25) | item 9, unique |
| date_of_birth | DATE | item 10 |
| religion | VARCHAR(50) | item 11 |
| nationality | VARCHAR(50) | item 11, default "Bangladeshi" |
| caste | VARCHAR(50) | item 11 (বর্ণ), nullable |
| status | VARCHAR(20) | pending \| approved \| rejected (default pending) |
| rejection_reason | VARCHAR(255) | nullable |
| reviewed_by | BIGINT UNSIGNED | FK → users, nullable |
| reviewed_at | TIMESTAMP | nullable |

Photo + documents via medialibrary collections `photo`, `documents`.

### admission_previous_educations
Form item 13 (repeatable rows).

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| application_id | BIGINT UNSIGNED | FK → admission_applications, cascade delete |
| exam_name | VARCHAR(100) | |
| institution_name | VARCHAR(150) | |
| gpa | DECIMAL(4,2) | nullable |
| passing_year | YEAR | nullable |
| board_roll | VARCHAR(30) | nullable |
| board_reg_no | VARCHAR(30) | nullable |

### students
Created at admission approval; office-use fields assigned here.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | FK → users, unique |
| branch_id | BIGINT UNSIGNED | FK → branches |
| application_id | BIGINT UNSIGNED | FK → admission_applications, nullable, unique |
| admission_no | VARCHAR(30) | unique (office-use box) |
| name_bn | VARCHAR(150) | |
| name_en | VARCHAR(150) | |
| father_name_bn | VARCHAR(150) | |
| father_name_en | VARCHAR(150) | |
| father_nid | VARCHAR(20) | nullable |
| mother_name_bn | VARCHAR(150) | |
| mother_name_en | VARCHAR(150) | |
| mother_nid | VARCHAR(20) | nullable |
| present_village | VARCHAR(100) | |
| present_post_office | VARCHAR(100) | |
| present_upazila | VARCHAR(100) | |
| present_district | VARCHAR(100) | |
| permanent_village_bn | VARCHAR(100) | |
| permanent_post_office_bn | VARCHAR(100) | |
| permanent_upazila_bn | VARCHAR(100) | |
| permanent_district_bn | VARCHAR(100) | |
| permanent_village_en | VARCHAR(100) | |
| permanent_post_office_en | VARCHAR(100) | |
| permanent_upazila_en | VARCHAR(100) | |
| permanent_district_en | VARCHAR(100) | |
| father_mobile | VARCHAR(20) | |
| mother_mobile | VARCHAR(20) | nullable |
| birth_reg_no | VARCHAR(25) | unique |
| date_of_birth | DATE | |
| religion | VARCHAR(50) | |
| nationality | VARCHAR(50) | |
| caste | VARCHAR(50) | nullable |
| status | VARCHAR(20) | active \| tc \| inactive (default active) |
| admitted_at | DATE | |
| deleted_at | TIMESTAMP | soft delete |

Photo via medialibrary collection `photo`.

### parents
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| user_id | BIGINT UNSIGNED | FK → users, unique |
| branch_id | BIGINT UNSIGNED | FK → branches |
| name | VARCHAR(150) | |
| phone | VARCHAR(20) | |
| relation | VARCHAR(30) | father \| mother \| guardian |

### parent_student
| Column | Type | Notes |
|---|---|---|
| parent_id | BIGINT UNSIGNED | FK → parents, cascade |
| student_id | BIGINT UNSIGNED | FK → students, cascade |

Composite PK (`parent_id`, `student_id`).

### enrollments
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| student_id | BIGINT UNSIGNED | FK → students |
| session_id | BIGINT UNSIGNED | FK → academic_sessions |
| class_id | BIGINT UNSIGNED | FK → school_classes |
| section_id | BIGINT UNSIGNED | FK → sections |
| roll_no | SMALLINT UNSIGNED | office-use "roll" — per class/section/session |
| status | VARCHAR(20) | active \| promoted \| failed \| tc |

Unique: (`student_id`, `session_id`) and (`session_id`, `class_id`, `section_id`, `roll_no`).

---

## 3. Attendance

### student_attendances
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| enrollment_id | BIGINT UNSIGNED | FK → enrollments |
| date | DATE | |
| status | VARCHAR(10) | present \| absent \| late \| leave |
| recorded_by | BIGINT UNSIGNED | FK → users |

Unique: (`enrollment_id`, `date`). Index: (`date`).

### teacher_attendances
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| teacher_id | BIGINT UNSIGNED | FK → teachers |
| date | DATE | |
| check_in_at | DATETIME | |
| check_out_at | DATETIME | nullable |
| check_in_ip | VARCHAR(45) | IPv4/IPv6 |
| status | VARCHAR(10) | present \| late \| absent \| leave |
| corrected_by | BIGINT UNSIGNED | FK → users, nullable (admin correction) |

Unique: (`teacher_id`, `date`).

### checkin_ip_whitelists
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| ip_address | VARCHAR(45) | exact IP or CIDR e.g. `103.4.5.0/24` |
| label | VARCHAR(100) | nullable, e.g. "School WiFi" |
| is_active | BOOLEAN | default true |

Unique: (`branch_id`, `ip_address`).

---

## 4. Exams & Results

### grading_scales
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| grade | VARCHAR(5) | A+, A, A-, B, C, D, F |
| min_marks | TINYINT UNSIGNED | inclusive |
| max_marks | TINYINT UNSIGNED | inclusive |
| grade_point | DECIMAL(3,2) | 5.00 … 0.00 |
| is_fail | BOOLEAN | true for F |

Seeded with the Bangladesh-standard scale; editable in settings.

### exams
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| session_id | BIGINT UNSIGNED | FK → academic_sessions |
| class_id | BIGINT UNSIGNED | FK → school_classes |
| type | VARCHAR(20) | first_semester \| second_semester \| final |
| name | VARCHAR(100) | e.g. "First Semester 2026" |
| start_date | DATE | nullable |
| end_date | DATE | nullable |
| status | VARCHAR(20) | upcoming \| ongoing \| completed \| published |

Unique: (`session_id`, `class_id`, `type`).

### marks
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| exam_id | BIGINT UNSIGNED | FK → exams |
| enrollment_id | BIGINT UNSIGNED | FK → enrollments |
| subject_id | BIGINT UNSIGNED | FK → subjects |
| obtained_marks | DECIMAL(5,2) | 0 ≤ x ≤ subject.full_marks |
| grade | VARCHAR(5) | snapshot from grading scale |
| grade_point | DECIMAL(3,2) | snapshot |
| entered_by | BIGINT UNSIGNED | FK → users |

Unique: (`exam_id`, `enrollment_id`, `subject_id`).

### exam_results
Persisted per-exam snapshot.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| exam_id | BIGINT UNSIGNED | FK → exams |
| enrollment_id | BIGINT UNSIGNED | FK → enrollments |
| total_marks | DECIMAL(7,2) | |
| gpa | DECIMAL(3,2) | avg of subject grade points |
| grade | VARCHAR(5) | overall grade |
| is_passed | BOOLEAN | false if any subject F |
| published_at | TIMESTAMP | nullable |

Unique: (`exam_id`, `enrollment_id`).

### annual_results
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| enrollment_id | BIGINT UNSIGNED | FK → enrollments, unique |
| first_semester_gpa | DECIMAL(3,2) | |
| second_semester_gpa | DECIMAL(3,2) | |
| final_exam_gpa | DECIMAL(3,2) | |
| annual_gpa | DECIMAL(3,2) | 0.25·S1 + 0.25·S2 + 0.50·Final |
| grade | VARCHAR(5) | |
| is_passed | BOOLEAN | per fail rules |
| published_at | TIMESTAMP | nullable |

### promotions
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| student_id | BIGINT UNSIGNED | FK → students |
| from_enrollment_id | BIGINT UNSIGNED | FK → enrollments |
| to_enrollment_id | BIGINT UNSIGNED | FK → enrollments, nullable (null = held back record) |
| type | VARCHAR(10) | bulk \| individual |
| promoted_by | BIGINT UNSIGNED | FK → users |
| promoted_at | TIMESTAMP | |

---

## 5. Fees & Finance

### fee_structures
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| session_id | BIGINT UNSIGNED | FK → academic_sessions |
| class_id | BIGINT UNSIGNED | FK → school_classes |
| monthly_fee | DECIMAL(12,2) | |

Unique: (`branch_id`, `session_id`, `class_id`).

### invoices
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| student_id | BIGINT UNSIGNED | FK → students |
| enrollment_id | BIGINT UNSIGNED | FK → enrollments |
| invoice_no | VARCHAR(30) | unique, e.g. `INV-{branch}-{yyyymm}-{seq}` |
| month | TINYINT UNSIGNED | 1–12 |
| year | YEAR | |
| amount | DECIMAL(12,2) | from fee_structures at generation time |
| paid_amount | DECIMAL(12,2) | default 0.00 |
| status | VARCHAR(10) | unpaid \| partial \| paid |
| due_date | DATE | nullable |

Unique: (`student_id`, `month`, `year`).

### payments
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| invoice_id | BIGINT UNSIGNED | FK → invoices |
| receipt_no | VARCHAR(30) | unique, set on success |
| amount | DECIMAL(12,2) | |
| method | VARCHAR(20) | sslcommerz \| cash |
| status | VARCHAR(20) | pending \| paid \| failed \| cancelled |
| transaction_id | VARCHAR(100) | SSLCommerz tran_id, unique, nullable |
| gateway_payload | JSON | nullable; validated IPN response |
| paid_at | TIMESTAMP | nullable |
| collected_by | BIGINT UNSIGNED | FK → users, nullable (staff for cash; payer user for online) |

### categories
Shared category list for income/expense.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| name | VARCHAR(100) | |
| type | VARCHAR(10) | income \| expense |

Unique: (`branch_id`, `name`, `type`).

### incomes
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| category_id | BIGINT UNSIGNED | FK → categories, nullable |
| payment_id | BIGINT UNSIGNED | FK → payments, nullable, unique — set ⇒ system-generated fee income, not editable |
| title | VARCHAR(150) | |
| amount | DECIMAL(12,2) | |
| date | DATE | indexed (reports) |
| description | TEXT | nullable |
| created_by | BIGINT UNSIGNED | FK → users |

### expenses
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| category_id | BIGINT UNSIGNED | FK → categories, nullable |
| item_name | VARCHAR(150) | |
| amount | DECIMAL(12,2) | price |
| date | DATE | indexed (reports) |
| description | TEXT | nullable |
| created_by | BIGINT UNSIGNED | FK → users |

### assets
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| name | VARCHAR(150) | |
| description | TEXT | nullable |
| value | DECIMAL(12,2) | |
| purchase_date | DATE | nullable |
| status | VARCHAR(20) | in_use \| damaged \| disposed (default in_use) |
| created_by | BIGINT UNSIGNED | FK → users |

---

## 6. Documents & Settings

### transfer_certificates
| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches |
| student_id | BIGINT UNSIGNED | FK → students, unique |
| tc_no | VARCHAR(30) | unique |
| reason | VARCHAR(255) | |
| issue_date | DATE | |
| issued_by | BIGINT UNSIGNED | FK → users |

Issuing a TC sets `students.status = tc` and `enrollments.status = tc` in the same transaction. The generated PDF is persisted via medialibrary (legal record).

### settings
Key–value store; `branch_id NULL` = global setting.

| Column | Type | Notes |
|---|---|---|
| id | BIGINT UNSIGNED | PK |
| branch_id | BIGINT UNSIGNED | FK → branches, **nullable** |
| key | VARCHAR(100) | e.g. `school_name`, `partial_payment_enabled`, `sslcommerz_store_id` |
| value | TEXT | JSON-encoded where structured |

Unique: (`branch_id`, `key`).

> ID cards have **no table** — they are rendered on demand from `students` + `enrollments` + branch data (per the architecture decision that PDFs are not stored, TC excepted).

---

## 7. Index & Integrity Summary

- All `branch_id`, `date`, and status columns used in report filters are indexed.
- MySQL unique indexes treat NULLs as distinct values: `settings (branch_id, key)` with a NULL `branch_id` (global settings) and `teacher_assignments` rows with NULL `section_id`/`subject_id` are **not** deduplicated at the DB level — uniqueness for those cases is enforced by application validation in the Form Requests.
- Uniqueness guards: one attendance per student per day, one mark per subject per exam, one invoice per student per month, one enrollment per student per session, one TC per student, idempotent payment `transaction_id`.
- Cascade deletes only on pure child rows (`admission_previous_educations`, `parent_student`); everything else `RESTRICT` to protect financial and academic history.
- Soft deletes only on `users`, `teachers`, `students` — financial rows are never deleted, only statused.
