# Progress Tracker & Task Board

> The unit of work is one task below. **Every task has a full ticket file in `docs/tasks/` — read the ticket before implementing; the line here is only a pointer.** One task = one sitting: implementable and verifiable (`php artisan test` green) in a single session. Claude Code marks `[x]` only when the task's code AND tests are complete. Never start a task before all tasks above it in the same phase are done; never start a phase before the previous phase is done.

## Phase 1 — Foundation `[ ]`
Specs: `api/auth.md`, `api/academic-structure.md`

- [x] [1.1](tasks/task-1.1-authentication.md) — Sanctum setup
- [x] [1.2](tasks/task-1.2-roles-permissions.md) — spatie/laravel-permission setup
- [x] [1.3](tasks/task-1.3-branches.md) — `branches` migration + model + CRUD (super admin only)
- [x] [1.4](tasks/task-1.4-academic-sessions.md) — `academic_sessions` migration + CRUD
- [x] [1.5](tasks/task-1.5-classes-sections.md) — `school_classes` + `sections` migrations + CRUD
- [x] [1.6](tasks/task-1.6-subjects.md) — `subjects` migration + CRUD
- [x] [1.7](tasks/task-1.7-branch-scope.md) — `BranchScope` global scope + `BelongsToBranch` trait (auto-stamp branch_id on create)
- [ ] [1.8](tasks/task-1.8-teacher-assignments.md) — `teacher_assignments` migration + CRUD + filters

## Phase 2 — Teacher Management `[ ]`
Specs: `api/teachers.md`

- [ ] [2.1](tasks/task-2.1-teacher-create.md) — `teachers` migration + model
- [ ] [2.2](tasks/task-2.2-credentials-job.md) — `SendCredentials` queued mail job (dispatch after commit)
- [ ] [2.3](tasks/task-2.3-teacher-endpoints.md) — Teacher list/show/update/status endpoints

## Phase 3 — Admissions `[ ]`
Specs: `api/admissions.md`

- [ ] [3.1](tasks/task-3.1-admission-tables.md) — `admission_applications` + `admission_previous_educations` migrations + models (all bilingual fields per schema)
- [ ] [3.2](tasks/task-3.2-student-tables.md) — Schema-only migrations for `students`, `parents`, `parent_student`, `enrollments` (needed by 3.5)
- [ ] [3.3](tasks/task-3.3-public-admission.md) — Public submission endpoint
- [ ] [3.4](tasks/task-3.4-admission-review.md) — Admin list/show with status/class/search filters
- [ ] [3.5](tasks/task-3.5-admission-approve-reject.md) — Approve flow — one transaction

## Phase 4 — Students & Parents `[ ]`
Specs: `api/students.md`

- [ ] [4.1](tasks/task-4.1-student-endpoints.md) — Student models/relationships
- [ ] [4.2](tasks/task-4.2-parents.md) — Parents CRUD + link/unlink endpoints + `/me/students`
- [ ] [4.3](tasks/task-4.3-enrollment-history.md) — `/students/{id}/enrollments` history endpoint

## Phase 5 — Student Attendance `[ ]`
Specs: `api/student-attendance.md`

- [ ] [5.1](tasks/task-5.1-attendance-sheet.md) — `student_attendances` migration
- [ ] [5.2](tasks/task-5.2-attendance-save.md) — POST bulk save
- [ ] [5.3](tasks/task-5.3-attendance-monthly.md) — Monthly sheets

## Phase 6 — Teacher Attendance `[ ]`
Specs: `api/teacher-attendance.md`

- [ ] [6.1](tasks/task-6.1-checkin-whitelist.md) — `teacher_attendances` + `checkin_ip_whitelists` migrations
- [ ] [6.2](tasks/task-6.2-checkin.md) — Check-in/check-out
- [ ] [6.3](tasks/task-6.3-teacher-attendance-admin.md) — Admin browse + correction (corrected_by) + `/me/teacher-attendance`

## Phase 7 — Exams & Marks `[ ]`
Specs: `api/exams-marks.md`

- [ ] [7.1](tasks/task-7.1-grading-scale.md) — `grading_scales` migration + Bangladesh-standard seeder
- [ ] [7.2](tasks/task-7.2-exams.md) — `exams` migration + CRUD
- [ ] [7.3](tasks/task-7.3-marks-entry.md) — `marks` migration

## Phase 8 — Results `[ ]`
Specs: `api/results.md`

- [ ] [8.1](tasks/task-8.1-exam-results.md) — `exam_results` migration
- [ ] [8.2](tasks/task-8.2-annual-results.md) — `annual_results` migration
- [ ] [8.3](tasks/task-8.3-result-reads.md) — Result search + `/enrollments/{id}/results` + `/me/results`
- [ ] [8.4](tasks/task-8.4-result-pdfs.md) — Marksheet PDFs (per-exam + annual) via dompdf, streamed

## Phase 9 — Promotion `[ ]`
Specs: `api/promotions.md`

- [ ] [9.1](tasks/task-9.1-promotion-preview.md) — `promotions` migration
- [ ] [9.2](tasks/task-9.2-bulk-promotion.md) — Bulk promotion
- [ ] [9.3](tasks/task-9.3-individual-promotion.md) — Individual promotion + override permission

## Phase 10 — Fees & Payments `[ ]`
Specs: `api/fees-payments.md`

- [ ] [10.1](tasks/task-10.1-fee-structures.md) — `fee_structures` migration + CRUD
- [ ] [10.2](tasks/task-10.2-invoices.md) — `invoices` migration
- [ ] [10.3](tasks/task-10.3-local-payment.md) — Local payment
- [ ] [10.4](tasks/task-10.4-sslcommerz-init.md) — `PaymentGatewayService` wrapping SSLCommerz (faked in tests)
- [ ] [10.5](tasks/task-10.5-sslcommerz-ipn.md) — IPN handler

## Phase 11 — Finance `[ ]`
Specs: `api/finance.md`

- [ ] [11.1](tasks/task-11.1-categories.md) — `categories` migration + CRUD (type income|expense)
- [ ] [11.2](tasks/task-11.2-incomes.md) — `incomes` CRUD
- [ ] [11.3](tasks/task-11.3-expenses.md) — `expenses` CRUD
- [ ] [11.4](tasks/task-11.4-assets.md) — `assets` CRUD + summary endpoint (total value, by status)

## Phase 12 — Documents `[ ]`
Specs: `api/documents.md`

- [ ] [12.1](tasks/task-12.1-idcard-single.md) — Single ID card PDF (on demand, streamed)
- [ ] [12.2](tasks/task-12.2-idcard-batch.md) — Batch ID card queued job (chunked merge) + poll endpoint (202/processing/done+url)
- [ ] [12.3](tasks/task-12.3-tc.md) — TC

## Phase 13 — Reports `[ ]`
Specs: `api/reports.md`

- [ ] [13.1](tasks/task-13.1-report-filters.md) — Shared filter resolver
- [ ] [13.2](tasks/task-13.2-finance-reports.md) — Income / expense / profit-loss reports
- [ ] [13.3](tasks/task-13.3-entity-reports.md) — Students / teachers / assets / fees reports
- [ ] [13.4](tasks/task-13.4-report-pdfs.md) — Report PDF exports

## Phase 14 — Settings, Dashboard & Polish `[ ]`
Specs: `api/settings.md`

- [ ] [14.1](tasks/task-14.1-settings.md) — `settings` migration
- [ ] [14.2](tasks/task-14.2-dashboard.md) — Role-aware dashboard endpoint
- [ ] [14.3](tasks/task-14.3-demo-seeders.md) — Full demo seeders + factory review (one branch fully populated)
- [ ] [14.4](tasks/task-14.4-final-polish.md) — Final pass

---

## Open Questions

| # | Question | Raised during | Resolution |
|---|---|---|---|
| 1 | Roll numbers: confirmed per class/section/session (reassigned yearly)? | schema design | pending user confirmation |
| 2 | SMS provider for credential/payment notifications (which gateway)? | — | pending |
| 3 | Late fee rules (amount/percent, grace period) — toggle exists, behavior undefined | settings design | pending; do not implement until defined |
| 4 | Grading scale uses absolute `min_marks`/`max_marks` (0–100), but subjects may have `full_marks ≠ 100` — is grade mapping on raw marks or percentage? | docs readiness review | pending; needed before Phase 7 (marks entry) |
| 5 | `admission_applications.birth_reg_no` is unique — a rejected applicant cannot re-apply (e.g., next session) with the same birth registration no. Allow re-application by scoping uniqueness to non-rejected applications (app-level), or keep the hard block? | schema verification | pending; needed before Task 3.1 (admission tables) |

## Decisions Log

- 2026-06-11 — Multi-branch confirmed; branch scoping via global scope.
- 2026-06-11 — PDFs on demand except TC (persisted, legal record).
- 2026-06-11 — Full-month payments default; partial behind setting toggle.
- 2026-06-11 — API spec split: index + per-module files matching build phases.
- 2026-06-11 — Execution model: module-based phases, task-based work units (this board).
- 2026-06-12 — Task 1.1: `users.branch_id` is created as an indexed nullable bigint **without** the FK constraint, because `branches` doesn't exist yet — the `create_branches_table` migration (Task 1.3) must add `foreign('branch_id')->references('id')->on('branches')` to `users`.
- 2026-06-12 — Task 1.1: API exception envelope handled centrally in `bootstrap/app.php` render closures (401/403/404/422 + generic HttpException incl. 429); success envelope via `App\Http\Controllers\Api\ApiController::success()`. Later tasks reuse both.
- 2026-06-12 — Task 1.2: `super_admin` role holds **zero** explicit permissions; bypass is `Gate::before` (`User::isSuperAdmin()`) in `AuthServiceProvider`. `UserResource` reports *effective* permissions, so super admins get the full permission list in `/auth/me` per the ticket's contract example.
- 2026-06-12 — Task 1.2: spatie's `UnauthorizedException` gets a dedicated render closure in `bootstrap/app.php` (registered **before** the generic `HttpExceptionInterface` closure — render closures match in registration order) so `permission:` middleware failures return the standard `{"success": false, "message": "This action is unauthorized."}` 403 envelope instead of spatie's default message.
- 2026-06-12 — Task 1.2: role bundle judgment calls (ticket said "sensible bundles"): admin additionally got `session.manage`, `class.manage`, `subject.manage` (academic structure is admin work; only branch CRUD is super-admin-only per the board), plus exam/attendance/teacher_attendance/result permissions, but **not** `marks.entry` (teachers enter marks), `branch.manage`, `setting.manage`, or finance (`income.manage`/`expense.manage`/`asset.manage` stay accountant-only). Accountant got `invoice.view` on top of the literal "finance + fee.collect + report.view" (collecting fees requires seeing invoices). Bundles live in `RoleSeeder::ROLES`; permission list in `PermissionSeeder::PERMISSIONS`.
- 2026-06-12 — Task 1.3: `branches` now owns the deferred FK for `users.branch_id`; branch deletes rely on DB restrict constraints and the API maps that failure to the required 409 envelope. `BranchSeeder` uses the codes `MP` and `JA` for the two known branches.
- 2026-06-12 — Task 1.4: model is `AcademicSession` (matches table, avoids clashing with framework `Session`); HTTP layer uses `Session*` names (`SessionController`, `SessionResource`, `Session/*Requests`) matching the `/sessions` endpoint and the ticket-mandated `SessionService`.
- 2026-06-12 — Task 1.4: "exactly one current session after any write" required two behaviors the ticket implied but didn't spell out: (a) the **first** session created always becomes current — explicit `is_current: false` with no current session existing is rejected 422 (`errors.is_current`, "One session must be current."), mirroring the documented update rule; (b) deleting the current session is rejected 422 with the same message (delete is a write too).
- 2026-06-12 — Task 1.4: the delete-in-use 409 depends on restrict FKs from `enrollments`/`exams`/`fee_structures`, none of which exist yet — those migrations (Phases 3/7/10) **must** declare `restrictOnDelete` FKs to `academic_sessions`. The required test exercises the path via a synthetic restrict-FK table created inside the test.
- 2026-06-13 — Task 1.5: Form Requests live in `App\Http\Requests\SchoolClass` because `Class` is a PHP reserved word (invalid namespace segment); model is `SchoolClass` per the table, HTTP layer uses `Class*` names (`ClassController`, `ClassResource`).
- 2026-06-13 — Task 1.5: `GET /classes` is an unpaginated dropdown list (≤12 rows/branch, same call as 1.4 made for sessions): active classes only, ordered by `numeric_level`, sections nested. Per the spec's "cached server-side" note it is cached via `Cache::remember` (key `academic.classes.{branch_id|all}`, 1h TTL); every class/section write in `ClassService` forgets the touched branch key plus the cross-branch key.
- 2026-06-13 — Task 1.5: branch handling until BranchScope (1.7): super admins **must** send `branch_id` on class create (they have no branch of their own) and may filter the index with `?branch_id=`; for everyone else `branch_id` input is `prohibited` (422) and stamping uses the caller's branch. Out-of-branch class/section access by non-super-admins returns 404 via explicit `assertClassVisibleTo`/`assertSectionVisibleTo` checks in `ClassService` — 1.7 replaces these with the global scope.
- 2026-06-13 — Task 1.5: `sections.class_teacher_id` is a plain nullable `unsignedBigInteger` (no FK — `teachers` doesn't exist); **Task 2.1 must add the FK**. Section delete-in-use 409 depends on future restrict FKs to `sections` (`teacher_assignments` 1.8, `enrollments` 3.2) — tested via a synthetic restrict-FK table, mirroring 1.4. Class delete-in-use already 409s for real via the `sections.class_id` restrict FK.
- 2026-06-13 — Task 1.6: the ticket-mandated `AcademicStructureService` now owns the academic-structure cache: the class-list caching from 1.5 moved into it from `ClassService` (keys unchanged, `academic.classes.{branch_id|all}`; `ClassService` delegates), and it holds the subject CRUD + cached subject reads. Subject lists are cached **per class** (`academic.subjects.{class_id}`) rather than per branch — the endpoint is class-nested, so per-class keys make invalidation exact; a stale key can't outlive its class because `subjects.class_id` restricts class deletes.
- 2026-06-13 — Task 1.6: `full_marks`/`pass_marks` are optional input (DB defaults 100/33); `pass_marks < full_marks` is enforced in the Form Requests' `after()` hooks against the *effective* values — input falling back to defaults on create and to stored values on update, so partial updates (e.g. raising only `pass_marks`) are checked against the stored `full_marks`. Error lands on `errors.pass_marks` per the ticket contract.
- 2026-06-13 — Task 1.7: `branch_id` input semantics changed from 1.5's `prohibited` (422) to **silently ignored** for non-super-admins, per the 1.7 contract ("Create as branch-1 admin ignores any submitted branch_id and stamps 1"): the class Form Requests use the `exclude` rule and `BelongsToBranch::creating` force-stamps `branch_id` from the auth user. `ClassSectionCrudTest::test_branch_id_is_super_admin_only_input` updated accordingly.
- 2026-06-13 — Task 1.7: `sections`/`subjects` carry no `branch_id` of their own, so the `BelongsToBranch` trait can't apply; isolation comes from a sibling `App\Models\Concerns\BelongsToBranchThroughClass` trait whose global scope adds `whereHas('schoolClass')` (the relation query carries `BranchScope`), making out-of-branch rows model-not-found at route binding. The 1.5/1.6 `assert*VisibleTo` helpers are removed from `ClassService` and controllers.
- 2026-06-13 — Task 1.7: scope-based binding 404s surfaced a latent envelope bug — Laravel prepares `ModelNotFoundException` into `NotFoundHttpException` **before** render closures run, so 1.1's `ModelNotFoundException` closure never matched and "No query results for model …" leaked through the generic `HttpExceptionInterface` closure. The closure now matches `NotFoundHttpException|ModelNotFoundException` and always returns the `Resource not found.` envelope.
- 2026-06-13 — Task 1.7: super admin list filter formalized in `ListClassesRequest` (`branch_id` = existing id, or `all`/omitted for every branch; invalid id → 422); the explicit `where('branch_id', …)` for that filter in `AcademicStructureService::listClasses` is the one sanctioned manual branch clause (documented in code). A branchless non-super-admin now gets an **uncached** empty list — caching it would land under the cross-branch `all` cache key (`classListKey(null)`).
- 2026-06-13 — Task 1.6: subject delete-in-use 409 depends on the future `marks` table — **Task 7.3 must declare a restrict FK to `subjects`**; tested via a synthetic restrict-FK table, mirroring 1.4/1.5. Class delete with subjects already 409s for real via the `subjects.class_id` restrict FK (asserted at DB level in the test).
