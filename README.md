# School Management System — REST API

A multi-branch school management backend built with Laravel. A single school
operates several branches and the system manages the full school lifecycle:
public admissions, students & parents, teachers, daily attendance, exams &
weighted result generation, class promotion, fee collection (local +
SSLCommerz), finance, document generation (ID cards, transfer certificates),
filterable reports, settings, and a role-aware dashboard. The entire surface is
a JSON REST API under `/api/v1`, behind Laravel Sanctum and permission-based
authorization.

---

## Goal

Deliver a production-ready REST API that runs a school's day-to-day operations
across multiple branches, end to end:

1. Let a public visitor apply for admission and let admins approve applications
   into working student accounts with credentials delivered automatically.
2. Provide permission-based access for six roles — super admin, admin,
   accountant, teacher, student, parent — with super admin overseeing every
   branch.
3. Scope all data to branches automatically, so each user sees only their
   branch while super admin sees consolidated data.
4. Record daily student attendance and IP-restricted teacher check-ins.
5. Enter subject-wise marks across three exams and generate weighted annual
   results (25% first semester + 25% second semester + 50% final).
6. Promote passed students to the next class, in bulk or individually.
7. Collect monthly fees via SSLCommerz or at the counter, issue downloadable
   receipts, and post every successful payment to income.
8. Track income, expenses, and assets per branch and consolidated.
9. Generate printable PDFs: result sheets, ID cards, money receipts, transfer
   certificates, and report exports.
10. Produce filterable reports (weekly, monthly, yearly, custom range) across
    finance, students, teachers, and assets.

This is Phase 1 (the API). The consuming frontend is Phase 2 and out of scope
here.

---

## Features

### Authentication, Roles & Permissions
- Token-based authentication with Laravel Sanctum.
- Granular permissions bundled into six roles via `spatie/laravel-permission`.
- Super admin manages roles, permissions, and user–role assignment; super admin
  bypasses checks via `Gate::before`.
- "Any permitted user" requirements resolve through permission checks
  (`marks.entry`), never role-name checks.

### Multi-Branch Architecture
- Every branch-scoped record carries a `branch_id`.
- Non-super-admin users belong to exactly one branch; isolation is enforced by a
  global query scope (out-of-branch records return **404**, not 403).
- Super admin can switch branch context and view consolidated data.

### Academic Structure
- Branches, academic sessions, classes, sections, and subjects per class.
- Class-teacher and subject-teacher assignments.

### Student Admission
- Public, unauthenticated admission form (personal info, guardian info, photo,
  documents).
- Applications land in a pending state for admin review.
- Approval creates the student user, sends credentials, and enrolls the student;
  rejection records a reason.

### Teacher Management
- Admin creates teacher profiles with subjects, assigned classes, and branch.
- System generates and sends login credentials; active/inactive status control.

### Student Attendance
- Daily attendance per class/section: present, absent, late, leave.
- One record per student per day (unique constraint).
- Students and parents view monthly attendance sheets for themselves/children.

### Teacher Attendance
- Teacher self check-in / check-out, allowed only from branch-whitelisted IPs.
- Admin can view and correct teacher attendance.

### Exams & Mark Entry
- Three exams per class per year: first semester, second semester, final.
- Marks entered per student per subject per exam.
- Marks map to grades and grade points via a configurable grading scale
  (Bangladesh-standard scale seeded by default).

### Result Generation
- Per-exam result: subject marks, grades, GPA; an F in any subject fails the exam.
- Annual GPA = 0.25·S1 + 0.25·S2 + 0.50·Final.
- Any permitted user can search any student's result; sheets download as PDF.
- Published results are immutable; later grading-scale edits never alter them.

### Student Promotion
- One-click bulk promotion of all passed students to the next class/session.
- Individual promotion or hold; failed students repeat; history is recorded.

### Student Monthly Payment
- Monthly invoices auto-generated on the 1st for active students, amounts
  configured per class per branch.
- Online payment via SSLCommerz or local payment recorded by staff.
- Full-month by default; partial payments behind a settings toggle.
- Each successful payment → one money receipt PDF + one automatic income entry.

### Finance — Income, Expense, Asset
- Income entries (student fees post here automatically) with category, amount,
  date, description.
- Expense entries with name, price, date, description, category.
- Asset entries with name, description, value, purchase date; total value at a
  glance per branch and consolidated.

### Documents
- **ID cards** for a student or whole class (photo, name, ID, class, branch,
  session, validity) as PDF.
- **Transfer Certificate (TC)**: issuing sets student status to TC, retains the
  record, excludes the student from attendance/invoicing/promotion, and stores
  the TC PDF (the only persisted PDF).

### Reports
- Income, expenses, total students, assets, teachers, and profit/loss summary.
- Filters: weekly, monthly, yearly, custom range; per branch or consolidated.
- Exportable as PDF.

### Settings & Dashboard
- Global: school identity, academic session, grading scale, SSLCommerz and
  notification credentials.
- Per-branch: branch info, teacher check-in IP whitelist, class fee amounts.
- Feature toggles (e.g. partial payments).
- Role-aware dashboard aggregating the figures each role is permitted to see.

---

## Development Setup

### Requirements

- PHP 8.3+
- Composer
- MySQL 8 (local/production); SQLite is used for the test suite
- Node.js (only for the bundled Vite tooling; the API needs no frontend build)

### Install

```bash
git clone <repo> && cd app-api
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` (see [Environment](#environment)), create the database, then
migrate and seed:

```bash
php artisan migrate --seed
```

`db:seed` runs the permission/role seeders, the grading scale, and a full
**demo seeder** that populates the first branch end-to-end (users, students,
enrollments, marks, published results, promotions, settled payments, an issued
TC) so the API is immediately explorable. A `migrate:fresh --seed` takes ~20s.

### Environment

Key variables beyond the Laravel defaults:

| Variable | Purpose | Example |
|---|---|---|
| `APP_ENV` | `production` disables strict model checks; anything else (`local`, `testing`) keeps them on | `local` |
| `DB_CONNECTION` | Primary database | `mysql` |
| `QUEUE_CONNECTION` | Must be a real queue for mail / batch PDFs (jobs are queued, never inline) | `database` |
| `CACHE_STORE` | Settings, grading scale, fee structures and academic structure are cached | `database` |
| `MAIL_MAILER` | Credential emails go through this mailer | `log` (dev) / `smtp` (prod) |
| `SSLCOMMERZ_STORE_ID` | SSLCommerz merchant store id | _(from SSLCommerz dashboard)_ |
| `SSLCOMMERZ_STORE_PASSWORD` | SSLCommerz store password | _(from SSLCommerz dashboard)_ |
| `SSLCOMMERZ_SANDBOX` | Use the sandbox gateway; **keep `true` outside production** | `true` |

For SSLCommerz **sandbox**, register a store at
<https://developer.sslcommerz.com/>, set the sandbox credentials, and leave
`SSLCOMMERZ_SANDBOX=true`. The IPN/callback handler is idempotent — replays are
a no-op.

### Running

```bash
php artisan serve            # API
php artisan queue:work       # process credential emails, batch ID-card PDFs
```

Or use the bundled all-in-one dev script (server + queue + logs + vite):

```bash
composer dev
```

#### Queue worker

Mail, bulk PDF generation, and other long-running work are queued and must be
processed by a worker. In production run a supervised worker:

```bash
php artisan queue:work --tries=3
```

#### Scheduler

Two scheduled commands are registered in `routes/console.php`:

- `invoices:generate` — monthly on the 1st at 00:00
- `idcards:prune-batches` — daily at 01:00

Add the Laravel scheduler to cron on the server:

```cron
* * * * * cd /path/to/app-api && php artisan schedule:run >> /dev/null 2>&1
```

### Tests

```bash
composer test          # clears config cache, then runs the suite
# or
php artisan test
```

The suite runs against in-memory SQLite. Outside production, models run under
`Model::shouldBeStrict()` (lazy-loading, missing attributes, and silently
discarded attributes all throw), so N+1s and typos fail loudly in local/CI.

### Production caching

`config:cache` and `route:cache` are both supported (no closures in routes, no
`env()` outside config files):

```bash
php artisan config:cache
php artisan route:cache
```

---

## Architecture

### Stack

| Layer | Technology | Role |
|---|---|---|
| Framework | Laravel 13 (PHP 8.3+) | REST API backend; no server-rendered views except payment redirects |
| API | Versioned REST under `/api/v1` | All client-facing functionality; JSON only |
| Auth | Laravel Sanctum | Token-based authentication for SPA/mobile clients |
| Authorization | spatie/laravel-permission | Granular permissions bundled into six roles |
| Database | MySQL 8 | All relational data: academic, finance, attendance, settings |
| Media | spatie/laravel-medialibrary | Student photos, admission documents, school logo |
| PDF | barryvdh/laravel-dompdf | Result sheets, ID cards, money receipts, TC, report exports |
| Payments | SSLCommerz | Online fee payment (sandbox first) + manual local payments |
| Background work | Laravel queues (database driver) | Credential emails, bulk PDF generation, payment post-processing |
| Scheduling | Laravel scheduler | Monthly invoice generation, batch pruning |

### Layered request flow

Code is strictly layered — each layer has one job:

```
Route (routes/api.php)
  → middleware (auth:sanctum, permission, branch scope)
  → Controller (app/Http/Controllers/Api/V1)   thin: input → service → Resource
  → Form Request (app/Http/Requests)           validation + authorize()
  → Service (app/Services)                      business logic, DB::transaction
  → Model (app/Models)                          Eloquent, scopes, casts, enums
  → API Resource (app/Http/Resources)           response shaping
```

- **Controllers** contain no queries, logic, or transactions — they resolve
  validated input, make one service call, and return a Resource.
- **Form Requests** own all input validation and per-endpoint authorization.
- **Services** own business logic; multi-step writes run in `DB::transaction()`.
- **Policies** (`app/Policies`) enforce record-level access (parent → linked
  students only; student → self only).
- **Resources** shape every response; models are never serialized directly.
- **Jobs** (`app/Jobs`) handle queued work; **Console commands**
  (`app/Console/Commands`) handle scheduled work.

### Response envelope

Every response uses `{ "success": bool, "message": string, "data": ... }`;
paginated lists add a `meta` object, and validation errors add a field-keyed
`errors` object. Lists are always paginated (default 15, max 100).

### Branch scoping

- Every branch-scoped table carries a `branch_id`.
- A global Eloquent scope (`BranchScope`) + `BelongsToBranch` trait constrains
  every query to the authenticated user's branch and auto-stamps `branch_id` on
  create — there is no manual `where('branch_id', …)`.
- Super admin bypasses the scope and may request consolidated data.
- Cross-branch access for non-super-admins is impossible; out-of-branch records
  return **404, not 403**.

### Authorization model

- Sanctum issues personal access tokens on login; all `/api/v1` routes except
  the public admission form and payment callbacks require a token.
- Authorization is permission-based in code (`attendance.create`,
  `marks.entry`), never role-based. Roles are only bundles of permissions.
- Policies enforce record ownership; teacher check-in additionally validates the
  request IP against the branch's check-in IP whitelist.

### Storage model

- **MySQL** holds all metadata and transactional data.
- **Media disk** (medialibrary) holds uploaded binaries (photos, documents,
  logos); local in dev, swappable to S3-compatible storage via config.
- **PDFs are generated on demand** and streamed, never stored or base64-encoded
  in JSON — except the **TC document**, the only persisted PDF (a legal record).
- Money is stored as `decimal(12,2)` (decimal strings in JSON); floats are never
  used for money, and financial mutations run inside transactions.
- Published per-exam GPA and weighted annual GPA are persisted at publication so
  historical results stay immutable even if the grading scale later changes.

### Payment model

- **Online (SSLCommerz):** API creates a pending payment → redirects to hosted
  checkout → SSLCommerz hits the IPN/success callback → service validates against
  the SSLCommerz API → payment marked paid inside a DB transaction.
- **Local (counter):** permitted staff records the payment directly.
- Both paths produce a money receipt (PDF on demand) and one automatic income
  entry. Callbacks are idempotent — a replayed IPN cannot double-post income.

### Result computation

- Marks map to grades/grade points via the configurable grading scale.
- Per-exam GPA = average of subject grade points; an F in any subject fails the
  exam.
- Annual GPA = (S1 × 0.25) + (S2 × 0.25) + (Final × 0.50).
- Computation lives in a dedicated `ResultService`; controllers and PDF
  templates only read persisted results.

### Background work & scheduling

- Queued jobs: credential dispatch, bulk ID-card PDF builds, payment
  post-processing. Request handlers never render bulk PDFs or send mail inline.
- Scheduled: monthly fee-invoice generation on the 1st for active (non-TC)
  students using per-class, per-branch amounts; daily ID-card batch pruning.

### Key invariants

1. Controllers hold no business logic; validation in Form Requests, logic in
   services, output in Resources.
2. Branch isolation is enforced by the global scope, never ad hoc `where`.
3. Money is always `decimal(12,2)`; financial mutations always run in
   transactions.
4. Published results are immutable; grading-scale edits never alter past results.
5. Payment callbacks are idempotent; each successful payment → exactly one income
   entry + one receipt.
6. TC students are excluded from attendance, invoicing, and promotion by query
   scope, not per-feature conditionals.
7. Long-running/bulk work runs in queued jobs, never in request handlers.
8. The public surface is limited to the admission form, payment callbacks, and
   public settings; everything else requires Sanctum + permissions.

---

## Documentation

Deeper project documentation lives in `docs/`:

| File | Owns |
|---|---|
| `docs/progress-tracker.md` | Task board, open questions, decisions log |
| `docs/tasks/*.md` | One ticket per task — the per-task source of truth |
| `docs/api-spec.md` + `docs/api/*.md` | Envelope/error conventions + per-module endpoint contracts |
| `docs/database-schema.md` | Tables, columns, types, constraints, indexes |
| `docs/architecture-context.md` | Layers, storage model, payment/result models, invariants |
| `docs/project-overview.md` | Goals, roles, flows, scope |
| `ai-workflow.md` | Scoping, splitting, protected files, exit criteria |
