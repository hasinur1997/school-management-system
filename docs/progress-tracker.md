# Progress Tracker & Task Board

> The unit of work is one task below. **Every task has a full ticket file in `docs/tasks/` — read the ticket before implementing; the line here is only a pointer.** One task = one sitting: implementable and verifiable (`php artisan test` green) in a single session. Claude Code marks `[x]` only when the task's code AND tests are complete. Never start a task before all tasks above it in the same phase are done; never start a phase before the previous phase is done.

## Phase 1 — Foundation `[x]`
Specs: `api/auth.md`, `api/academic-structure.md`

- [x] [1.1](tasks/task-1.1-authentication.md) — Sanctum setup
- [x] [1.2](tasks/task-1.2-roles-permissions.md) — spatie/laravel-permission setup
- [x] [1.3](tasks/task-1.3-branches.md) — `branches` migration + model + CRUD (super admin only)
- [x] [1.4](tasks/task-1.4-academic-sessions.md) — `academic_sessions` migration + CRUD
- [x] [1.5](tasks/task-1.5-classes-sections.md) — `school_classes` + `sections` migrations + CRUD
- [x] [1.6](tasks/task-1.6-subjects.md) — `subjects` migration + CRUD
- [x] [1.7](tasks/task-1.7-branch-scope.md) — `BranchScope` global scope + `BelongsToBranch` trait (auto-stamp branch_id on create)
- [x] [1.8](tasks/task-1.8-teacher-assignments.md) — `teacher_assignments` migration + CRUD + filters

## Phase 2 — Teacher Management `[x]`
Specs: `api/teachers.md`

- [x] [2.1](tasks/task-2.1-teacher-create.md) — `teachers` migration + model
- [x] [2.2](tasks/task-2.2-credentials-job.md) — `SendCredentials` queued mail job (dispatch after commit)
- [x] [2.3](tasks/task-2.3-teacher-endpoints.md) — Teacher list/show/update/status endpoints

## Phase 3 — Admissions `[x]`
Specs: `api/admissions.md`

- [x] [3.1](tasks/task-3.1-admission-tables.md) — `admission_applications` + `admission_previous_educations` migrations + models (all bilingual fields per schema)
- [x] [3.2](tasks/task-3.2-student-tables.md) — Schema-only migrations for `students`, `parents`, `parent_student`, `enrollments` (needed by 3.5)
- [x] [3.3](tasks/task-3.3-public-admission.md) — Public submission endpoint
- [x] [3.4](tasks/task-3.4-admission-review.md) — Admin list/show with status/class/search filters
- [x] [3.5](tasks/task-3.5-admission-approve-reject.md) — Approve flow — one transaction

## Phase 4 — Students & Parents `[ ]`
Specs: `api/students.md`

- [x] [4.1](tasks/task-4.1-student-endpoints.md) — Student models/relationships
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
| 5 | `admission_applications.birth_reg_no` is unique — a rejected applicant cannot re-apply (e.g., next session) with the same birth registration no. Allow re-application by scoping uniqueness to non-rejected applications (app-level), or keep the hard block? | schema verification | **resolved 2026-06-13 (user):** keep the hard block — plain DB unique index on `birth_reg_no`, exactly as `database-schema.md` states. Re-application after rejection with the same birth reg no is not supported. |

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
- 2026-06-13 — Task 1.8: `teacher_assignments` created with an unconstrained `teacher_id` (no FK — teachers arrives in 2.1, which **must** add `foreign('teacher_id')->references('id')->on('teachers')`); `session_id`/`class_id`/`section_id`/`subject_id` are restrict FKs. DB `unique(teacher_id, session_id, class_id, section_id, subject_id)` defends only the fully-populated tuple — SQL treats NULLs as distinct, so duplicates with NULL `section_id`/`subject_id` (class duties) are caught by `TeacherAssignmentRequest` validation instead.
- 2026-06-13 — Task 1.8: model carries no `branch_id`; branch isolation reuses `BelongsToBranchThroughClass` via its `schoolClass()` relation (same pattern as sections/subjects). Body-supplied `class_id`/`section_id`/`subject_id` are resolved through their (branch-scoped) models in the Form Request `after()` hook, so out-of-branch ids report 422 `errors.class_id` rather than leaking other branches' rows; `session_id` uses a plain `exists` rule (sessions are global, no branch_id). Duplicate-tuple violations attach to `errors.teacher_id`.
- 2026-06-13 — Task 1.8: per ticket decision, all five CRUD endpoints are guarded by `permission:teacher.update` (no separate read permission — assignments are admin-managed). The `teacher()` relation + nested teacher name in `TeacherAssignmentResource` are deferred to 2.1; today the resource nests class/session/section/subject names (all eager loaded — N+1 guard test runs under `Model::shouldBeStrict()`).
- 2026-06-13 — Task 2.1: the deferred FKs landed here — `teacher_assignments.teacher_id` (`restrictOnDelete`) and `sections.class_teacher_id` (`nullOnDelete`, so deleting a class teacher just unsets the section). The new `teacher_id` FK broke the 1.8 tests/factory that used arbitrary integer teacher ids: `TeacherAssignmentFactory` now defaults `teacher_id` to `Teacher::factory()`, `TeacherFactory.branch_id` defaults to `Branch::factory()` (column is NOT NULL), and `TeacherAssignmentCrudTest::setUp` seeds teachers with the literal ids (4, 7) those tests reference. The 1.8-deferred `TeacherAssignment::teacher()` relation was added (resource/nested name still deferred to 2.3).
- 2026-06-13 — Task 2.1: `POST /teachers` creates the login (`users` row, random `Str::password(10)`, teacher role, creator's branch) + `teachers` profile in one `DB::transaction`; `SendCredentials` is dispatched `->afterCommit()` (verified to fire under RefreshDatabase). `SendCredentials` is a queued stub (empty `handle`, real mail in 2.2) carrying `User` + plaintext password with `tries=3`/`backoff=30`. Email/phone uniqueness is validated against the **users** table (login credentials) — duplicate phone surfaces there since `teachers.phone` is not unique. `teachers.status` uses new `App\Enums\TeacherStatus` (active/inactive). The login test had to call `$this->app['auth']->forgetGuards()` between the two authenticated requests because the sanctum guard caches the resolved user across requests within a single test.
- 2026-06-13 — Task 2.3: first `spatie/laravel-medialibrary` usage — installed `^11.23`, published `media` migration + config unedited; `Teacher implements HasMedia` with a single-file `photo` collection (`acceptsMimeTypes` jpg/png). `photo_url` resolves via `Teacher::photoUrl()` (`getFirstMediaUrl('photo') ?: null`); all reads eager-load `media` to dodge N+1 under `shouldBeStrict()`. **Decisions the ticket left open:** (a) `GET /teachers` defaults `sort=name asc` (joining_date defaults desc), filters `status`+`search` (LIKE across name/email/phone/designation), `per_page` capped 100 — same meta envelope as 1.8; (b) `PUT /teachers/{id}` mirrors `name`+`phone` onto the linked `users` row (phone is a login identifier, so the profile and login must not drift) and `email` is `prohibited` (422) since it's the login identity — phone uniqueness validated on `users` ignoring the teacher's own user; (c) `PATCH /teachers/{id}/status`: inactive sets `users.is_active=false` **and** deletes all tokens (active session cut immediately), active re-enables the login; (d) `POST /teachers/{id}/photo` replaces via the single-file collection. All `{teacher}` routes 404 out-of-branch via `BranchScope` route-model binding. `assignments` is only serialized on `show`, eager-loaded for the **current** session (`AcademicSession.is_current`) with class/section/subject names.
- 2026-06-13 — Task 2.2: `SendCredentials` now sends `CredentialsMail` (markdown view `resources/views/mail/credentials.blade.php`); gained a third ctor arg `string $role = 'Teacher'` so students/parents reuse it in 3.5, and `backoff` changed from int `30` to escalating `[60, 300]` per ticket. Login identifier in the mail is the user's **email** (login accepts email or phone; email is the stable credential); login URL hint is `config('app.url').'/login'`. `POST /teachers/{teacher}/resend-credentials` (`permission:teacher.create`) regenerates the password, `forceFill`s the new hash, revokes **all** tokens (`$user->tokens()->delete()`), and dispatches the job after commit — inactive teacher → 409 `"Teacher is inactive."` via `abort()` in the service, out-of-branch → 404 via route-model binding under `BranchScope`. Queue (`database`) + `failed_jobs` were already migrated (default `0001_01_01_000002_create_jobs_table`), so no new migration. Mail-sent assertions pass under the test `sync` queue, confirming `afterCommit` fires there.
- 2026-06-13 — Task 3.1 (open question #5 resolved by user): `admission_applications.birth_reg_no` keeps the **hard** DB unique index per the schema — a rejected applicant cannot re-apply with the same birth registration number. No app-level scoping. `application_no` and `birth_reg_no` uniqueness asserted via `QueryException` (SQLSTATE 23000) in tests.
- 2026-06-13 — Task 3.1: `AdmissionPreviousEducation` sets `$table = 'admission_previous_educations'` explicitly — "education" is uncountable, so Eloquent's inferred plural (`admission_previous_education`) was wrong. FK `application_id` is `cascadeOnDelete` (the only cascade in this module, per schema's "cascade only on pure child rows"); deleting an application removes its previous-education rows (tested).
- 2026-06-13 — Task 3.1: FK delete behavior judgment calls — `branch_id` and `desired_class_id` use `restrictOnDelete` (the schema's documented default). `reviewed_by` (nullable audit pointer → users) uses `nullOnDelete` so deleting a former reviewer just unsets the field rather than blocking the delete.
- 2026-06-13 — Task 3.1: `ApplicationNoGenerator` (`APP-{branchCode}-{seq}`, zero-padded to 5 digits, e.g. `APP-MP-00001`) is race-safe via a `Branch::lockForUpdate()` held for the enclosing `DB::transaction`, serializing concurrent same-branch submissions; sequence derived from the latest committed `application_no` (`orderByDesc('id')`, `withoutGlobalScope(BranchScope)`), with the DB unique index as the final guard. Sequences are independent per branch. Caller (3.3) should invoke `generate()` inside its create transaction so the lock spans the insert.
- 2026-06-13 — Task 3.2: `parents` carries its own `branch_id` per the schema, so `ParentProfile` uses `BelongsToBranch` (auto-stamp + scope) like `Student` — the ticket only spelled this out for `Student`. Model named `ParentProfile` (table `parents`) to dodge the PHP `parent` keyword clash; relations are `parents()`/`students()` (not `parent()`). `Enrollment` has **no** `branch_id` (derives via student per schema §41), so it gets no branch trait — isolation in Phase 4 will come through the `student` relation.
- 2026-06-13 — Task 3.2: FK delete behavior — `parent_student` is the only cascade (both `parent_id` and `student_id` `cascadeOnDelete`, per schema's "cascade only on pure child rows"); everything else `restrictOnDelete`. `enrollments.session_id` and `enrollments.section_id` are the real restrict FKs the synthetic-table tests in Tasks 1.4/1.5 anticipated. `students.application_id` is nullable + unique + `restrictOnDelete`.
- 2026-06-13 — Task 3.2: `Student::currentEnrollment()` is a `hasOne` filtered by `whereHas('session', is_current=true)` — resolves the enrollment whose academic session is the current one (not by enrollment `status`, since multiple sessions can have `active`-status rows historically). `AdmissionNoGenerator` mirrors `ApplicationNoGenerator` (branch `lockForUpdate` for race safety) but the sequence is scoped per branch **and** per year (`STU-{code}-{year}-{seq}`), derived from the latest `admission_no` matching the `STU-{code}-{year}-` prefix; `withTrashed()` included so soft-deleted students don't free up a number. Default year is `now()->year`; caller (3.5) passes the admission year explicitly.
- 2026-06-13 — Task 3.3: public endpoints live under a no-auth `v1/public/*` group; submission is `throttle:10,60` (10/hour/IP — the throttle middleware runs before validation, so even malformed requests count, which is how the 429 test reaches the limit). Controller is `PublicAdmissionController` (named to leave `AdmissionController` free for the admin endpoints in 3.4). In the public context `BranchScope` is bypassed and `BelongsToBranch` does **not** stamp `branch_id`, so `AdmissionService::submit` sets it explicitly from the validated input; `ApplicationNoGenerator::generate` is called inside the submit transaction so its branch `lockForUpdate` spans the insert. Photo + documents are added to medialibrary **inside** the transaction so a disk failure rolls back the application + previous-education rows (asserted by pointing the media disk at an undefined disk name).
- 2026-06-13 — Task 3.3 (surprise): the 3.1 migration made `admission_applications.mother_mobile` NOT NULL, but the ticket's API contract marks the input field `mother_mobile?` optional. Reconciled by validating it `nullable` and coalescing a missing value to `''` in the service (the schema doc, line 173, does not list it as nullable, so the NOT NULL column stands). Validation rules mirror the schema's bilingual/optional columns; `desired_class_id` is validated via `Rule::exists` scoped to `is_active` **and** the submitted `branch_id`, so an out-of-branch or inactive class reports 422 `errors.desired_class_id`; duplicate `birth_reg_no` reports the contract's exact message via `messages()`. Status lookup uses `whereDate('date_of_birth', …)->firstOrFail()` → 404 on any miss (never reveals existence).
- 2026-06-13 — Task 3.4: admin reads live under `permission:admission.view` (route group); `AdmissionController` is separate from `PublicAdmissionController` (3.3). Branch isolation needs **no** manual clause — `AdmissionApplication` uses `BelongsToBranch`, so list excludes and `{admission}` route-model binding 404s out-of-branch automatically. Judgment calls the ticket left open: (a) list defaults `status=pending` in the **service** (not the Form Request) and orders `created_at desc`; the contract's `submitted_at` maps to `created_at` (ISO-8601); (b) the date range filters `from`/`to` are inclusive `whereDate` on `created_at`; (c) detail's `reviewed_by` is rendered as a nested `{id,name}` (the reviewer user) or `null` — not the raw FK — to match the contract's `null` for pending while staying useful once 3.5 stamps a reviewer; (d) two resources split the shapes — compact `AdmissionListResource` (eager-loads `desiredClass`) vs full `AdmissionDetailResource` (eager-loads `desiredClass`/`previousEducations`/`reviewer`/`media`); `documents` maps medialibrary `documents` collection to `[{name:file_name,url}]`, `photo_url` via a new `AdmissionApplication::photoUrl()` mirroring Teacher. N+1 guard runs the index under `Model::shouldBeStrict()`.
- 2026-06-13 — Task 3.5 (surprise — spec vs. schema conflict): the ticket says the student login takes `phone = father_mobile`, but a father/guardian parent account's contact is *also* `father_mobile`, and `users.phone` is a global unique index — both logins can't hold it. Resolution: the **parent claims the shared number** (the parent is the human who owns it and the credential job/feature exists so they can log in), and the **student's login phone is set null** when it would collide. With no parent account (the common case + the happy-path test) the student keeps `father_mobile` exactly as specced. The parent's contact phone is still stored on the `ParentProfile` row regardless. Parent `name`/`phone` are derived from the relation: `mother` → `mother_name_en` + `mother_mobile` (falls back to `father_mobile` if absent); `father`/`guardian` → `father_name_en` + `father_mobile`.
- 2026-06-13 — Task 3.5 (surprise): students/parents have **null email**, but the reused `SendCredentials` job (2.2) mails to `$user->email` and uses it as the credential identifier → `TypeError`/null-recipient when the queue actually runs it (only hidden under `Queue::fake`). Since SMS delivery is out of scope, `SendCredentials::handle()` now **early-returns when `email` is null** (a no-op for phone-only accounts) rather than crash. The job is still dispatched afterCommit for student (+ parent) per the contract, so the dispatch assertions hold.
- 2026-06-13 — Task 3.5: `AdmissionService::approve()` runs one `DB::transaction` (student user → student row copying all bilingual/address fields + photo media via `$media->copy()` → active enrollment → optional parent user/profile/`parent_student` link → application `approved` + reviewer stamp); credential jobs dispatch `->afterCommit()`. The 409 "Application has already been reviewed." guard sits in the service (both approve + reject), so a rejected application can never be approved later. `ApproveAdmissionRequest` validates `roll_no` unique within `(session_id, class_id, section_id)` and `admission_no` unique on `students` (→ 422); the class is resolved branch-scoped and the section verified to belong to it in `after()` (→ 422 `errors.section_id`). `admission_no` auto-generates from the **session's** year (`session.start_date->year`) when absent, via `AdmissionNoGenerator` called inside the transaction. The approval response student block is `ApprovedStudentResource` (enrollment relations preloaded by the service, no lazy loads). Routes `POST /admissions/{admission}/approve|reject` live under `permission:admission.approve`; out-of-branch `{admission}` 404s via `BranchScope` binding.
- 2026-06-13 — Task 4.1: `StudentPolicy::view` is `user.can('student.view') || user.id === student.user_id` (super_admin bypasses via `Gate::before`); registered explicitly with `Gate::policy()` in `AuthServiceProvider`. The ticket wants **404, not 403** when a student requests another student — but the policy returning `false` yields 403 via `authorize()`, and an in-branch peer is never hidden by `BranchScope`. Resolved at the HTTP boundary: the `show` route carries **no** `permission` middleware (so students can reach their own record), and the controller does `if ($request->user()->cannot('view', $student)) abort(404)` to hide existence. The reusable bool policy stays clean for attendance/results/fees to consume; the 404-vs-403 choice lives in each controller. List/update/status/photo keep `permission:student.view|student.update` middleware, so a student hits 403 on the list (correct per criteria). admission_no **and** birth_reg_no are `prohibited` in `UpdateStudentRequest` (explicit 422). `tc` status is blocked in `UpdateStudentStatusRequest` via `Rule::in([active,inactive])` with a custom message ("Use the TC module…"). List display class/section/roll come from the eager-loaded `currentEnrollment` (3.2's is_current-session hasOne); filters `class_id/section_id/session_id` match via `whereHas('enrollments')` so a student filtered by a past session still shows their current-session row. N+1 guarded with `Model::preventLazyLoading()` in the list test (no global `shouldBeStrict` set in this project).
- 2026-06-13 — Task 1.6: subject delete-in-use 409 depends on the future `marks` table — **Task 7.3 must declare a restrict FK to `subjects`**; tested via a synthetic restrict-FK table, mirroring 1.4/1.5. Class delete with subjects already 409s for real via the `subjects.class_id` restrict FK (asserted at DB level in the test).
