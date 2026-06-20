# Architecture Context

## Stack

| Layer            | Technology                  | Role                                                              |
| ---------------- | --------------------------- | ----------------------------------------------------------------- |
| Framework        | Laravel 13.14 (PHP 8.4)        | REST API backend; no server-rendered views except payment redirects |
| API              | Versioned REST under `/api/v1` | All client-facing functionality; JSON only                      |
| Auth             | Laravel Sanctum             | Token-based authentication for SPA/mobile clients                 |
| Authorization    | spatie/laravel-permission   | Granular permissions bundled into six roles                       |
| Database         | MySQL 8                     | All relational data: academic, finance, attendance, settings      |
| Media            | spatie/laravel-medialibrary | Student photos, admission documents, school logo                  |
| PDF              | barryvdh/laravel-dompdf     | Result sheets, ID cards, money receipts, TC, report exports       |
| Payments         | SSLCommerz                  | Online fee payment (sandbox first), plus manual local payments    |
| Background work  | Laravel queues (database driver) | Credential emails, bulk PDF generation, payment post-processing |
| Scheduling       | Laravel scheduler           | Monthly invoice generation on the 1st                             |

## System Boundaries

- `routes/api.php` — API route entry point; applies the `/api/v1` URI prefix and `v1.` route-name prefix, then loads module route files from `routes/api/v1`.
- `routes/api/v1/*.php` — Module route definitions; each file owns one functional area and applies its own auth, permission, throttle, and module-level prefixes.
- `app/Http/Controllers/Api/V1` — Thin controllers: receive validated input, call a service, return a Resource. No business logic.
- `app/Http/Requests` — Form Requests: all input validation and authorization gates per endpoint.
- `app/Http/Resources` — API Resources: all response shaping. Models are never serialized directly.
- `app/Services` — Business logic: admission approval, result computation, promotion, payment processing, report aggregation.
- `app/Models` — Eloquent models, relationships, scopes (including the branch scope), and casts.
- `app/Policies` — Record-level ownership checks (e.g., a parent may only view linked students).
- `app/Jobs` — Queued work: credential dispatch, receipt generation, bulk ID card builds.
- `app/Console/Commands` — Scheduled commands: monthly invoice generation.
- `database/migrations`, `database/seeders` — Schema plus seeders for roles, permissions, grading scale, branches, and demo data.
- `config/school.php` — Application-level defaults that are not user-editable settings.

## Storage Model

- **MySQL**: all metadata and transactional data — users, students, teachers, parents, academic structure, attendance, marks, results, invoices, payments, income, expenses, assets, settings, promotion history.
- **Media disk (medialibrary)**: uploaded binary content — student photos, admission documents, logos. Local `storage/app/public` in development; swappable to S3-compatible storage via config without code changes.
- **PDFs are generated on demand**, not stored: result sheets, ID cards, and receipts are rendered from database state at download time. Only the TC document is persisted (it is a legal record), stored via medialibrary and linked to the student.
- Money values are stored as `decimal(12,2)`. Floats are never used for money.
- Computed results (per-exam GPA and the weighted annual GPA) are persisted in a results table at publication time so historical results are immutable even if the grading scale later changes.

## Auth and Authorization Model

- Sanctum issues personal access tokens on login; all `/api/v1` routes except the public admission form and payment callbacks require a token.
- Authorization is permission-based, never role-based, in code: controllers and Form Requests check permissions like `attendance.create` or `marks.entry`. Roles are only bundles of permissions, so "any permitted user" requirements work without code changes.
- The six seeded roles: super admin, admin, accountant, teacher, student, parent. Super admin bypasses permission checks via `Gate::before`.
- Policies enforce record-level access: students see only their own records; parents see only records of students linked through the `parent_student` pivot.
- Teacher check-in additionally validates the request IP against the branch's `checkin_ip_whitelists` entries (a dedicated table, managed through the branch settings endpoints).

## Branch Scoping Model

- Every branch-scoped table carries a `branch_id` foreign key.
- Non-super-admin users have exactly one `branch_id` on their user record.
- A global Eloquent scope (`BranchScope`) automatically constrains queries to the authenticated user's branch.
- Super admin requests bypass the scope and may pass an explicit `branch_id` filter or request consolidated data.
- Cross-branch writes are impossible for non-super-admins: the scope applies on create as well, stamping `branch_id` from the authenticated user.

## Payment Model

### Online (SSLCommerz)
- Input: an unpaid monthly invoice and an authenticated student or parent.
- Flow: API creates a pending payment record → redirects to SSLCommerz hosted checkout → SSLCommerz hits the IPN/success callback → service validates the transaction against the SSLCommerz validation API → payment marked paid inside a DB transaction.
- Output: payment record, money receipt (PDF on demand), and an automatic system-generated income entry.

### Local (counter)
- A permitted staff user records the payment against the invoice directly.
- Same post-payment pipeline: receipt availability and income posting.

### Rules
- Full-month payment by default; partial payments only when the settings toggle is enabled, tracked as paid amount against invoice amount.
- Payment success, income posting, and invoice status updates occur in one DB transaction.
- Callback endpoints are idempotent: a replayed IPN cannot double-post income.

## Result Computation Model

- Input: subject-wise marks for a student across the three exams of a session (first semester, second semester, final).
- Marks map to grades and grade points through the configurable grading scale (Bangladesh-standard seeded default).
- Per-exam GPA = average of subject grade points; an F in any subject marks that exam as failed.
- Annual GPA = (first semester GPA × 0.25) + (second semester GPA × 0.25) + (final exam GPA × 0.50).
- Promotion eligibility = passed annual result per the fail rules; the bulk Promote action selects exactly the eligible students.
- Computation lives in a dedicated `ResultService`; controllers and PDF templates only read persisted results.

## Background Work and Scheduling

- Queued jobs: sending credentials to approved students and new teachers, bulk ID card PDF builds, payment post-processing notifications.
- Scheduled command on the 1st of each month: generate monthly fee invoices for all active (non-TC) students using per-class, per-branch fee amounts.
- Request handlers never perform bulk PDF rendering or email dispatch inline; anything touching more than one document or external service is queued.

## API Conventions

- Every response uses the envelope `{ "success": bool, "message": string, "data": ... }`; paginated lists include a `meta` object.
- Errors return appropriate HTTP status codes with the same envelope; validation errors include a field-keyed `errors` object.
- Route names and URIs are plural-resource style: `/api/v1/students`, `/api/v1/students/{student}/attendance`.
- Multi-step writes (admission approval, promotion, payment completion) are wrapped in DB transactions inside services.

## API Routing Model

- `routes/api.php` is the only top-level API route file Laravel loads directly. It wraps all API routes in `Route::prefix('v1')->name('v1.')->group(...)`, so every module file loaded inside it automatically serves under `/api/v1` and receives route names beginning with `v1.`.
- Module files live in `routes/api/v1`. They do not repeat the `v1` URI prefix or `v1.` route-name prefix.
- Module files import only the controllers they use and define the middleware for their own endpoints. Public endpoints, auth routes, permission-gated routes, and self-service routes should stay explicit in the module file.
- The include order in `routes/api.php` is part of routing behavior. Add more specific routes before broad parameter routes when they share a URI prefix, and keep related modules near existing neighboring modules.
- Existing modules include `public`, `auth`, `sessions`, `classes`, `teachers`, `students`, `parents`, `admissions`, `teacher-assignments`, `attendance`, `grading-scales`, `exams`, `marks`, `results`, `promotions`, `fees`, `accounting`, `assets`, `reports`, `dashboard`, `settings`, `access-control`, and `branches`.

### Adding a Module Route File

1. Create a focused file under `routes/api/v1`, for example `routes/api/v1/library.php`.
2. Start the file with `<?php`, import `Illuminate\Support\Facades\Route`, and import only the controllers used by that file.
3. Define routes exactly as they should appear after `/api/v1`; for example `Route::get('books', ...)` serves `/api/v1/books`.
4. Apply middleware in the module file, usually `auth:sanctum` plus the required permission middleware. Keep public routes rare and intentional.
5. Give every route a stable name without the `v1.` prefix; for example `->name('books.index')` becomes `v1.books.index`.
6. Add `require __DIR__.'/api/v1/library.php';` to `routes/api.php` inside the existing `v1` group.
7. Run `php artisan route:list` and confirm the URI, method, middleware, and route names are correct.

## Invariants

1. Controllers contain no business logic — validation lives in Form Requests, logic in services, output in Resources.
2. Authorization is enforced at every mutation boundary through permissions plus policies; branch isolation is enforced by the global scope, never by ad hoc `where` clauses.
3. Money is always `decimal(12,2)`; financial mutations always run inside DB transactions.
4. Published results are immutable snapshots; changing the grading scale never alters past results.
5. Payment callbacks are idempotent and every successful payment produces exactly one income entry and one receipt.
6. TC-status students are excluded from attendance, invoicing, and promotion by query scope, not by per-feature conditionals.
7. Long-running or bulk work (emails, batch PDFs) runs in queued jobs, never in request handlers.
8. The public surface is limited to the admission form and payment callbacks; everything else requires Sanctum authentication.
