# School Management System — REST API

A multi-branch school management backend built with Laravel. It covers
admissions, students & parents, teachers, attendance, exams & results,
promotions, fees & payments (local + SSLCommerz), finance, document
generation (ID cards, transfer certificates), reports, settings, and a
role-aware dashboard. The entire surface is a JSON REST API behind Laravel
Sanctum and permission-based authorization.

## Requirements

- PHP 8.2+
- Composer
- MySQL 8 (production/local); SQLite is used for the test suite
- Node.js (only for the bundled Vite tooling; the API itself needs no frontend build)

## Setup

```bash
git clone <repo> && cd app-api
composer install
cp .env.example .env
php artisan key:generate
```

Configure `.env` (see [Environment](#environment) below), create the database,
then migrate and seed:

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

For SSLCommerz **sandbox**, register a sandbox store at
<https://developer.sslcommerz.com/>, set `SSLCOMMERZ_STORE_ID` /
`SSLCOMMERZ_STORE_PASSWORD` to the sandbox credentials, and leave
`SSLCOMMERZ_SANDBOX=true`. The IPN/callback handler is idempotent — replays are
a no-op.

## Running

```bash
php artisan serve            # API
php artisan queue:work       # process credential emails, batch ID-card PDFs
```

Or use the bundled all-in-one dev script (server + queue + logs + vite):

```bash
composer dev
```

### Queue worker

Mail, bulk PDF generation, and other long-running work are dispatched to the
queue and must be processed by a worker. In production run a supervised
worker, e.g.:

```bash
php artisan queue:work --tries=3
```

### Scheduler

Two scheduled commands are registered in `routes/console.php`:

- `invoices:generate` — monthly on the 1st at 00:00
- `idcards:prune-batches` — daily at 01:00

Add the Laravel scheduler to cron on the server:

```cron
* * * * * cd /path/to/app-api && php artisan schedule:run >> /dev/null 2>&1
```

## Tests

```bash
composer test          # clears config cache, then runs the suite
# or
php artisan test
```

The suite runs against in-memory SQLite. Outside production, models run under
`Model::shouldBeStrict()` (lazy-loading, missing attributes, and silently
discarded attributes all throw), so N+1s and typos fail loudly in local/CI.

## Production caching

`config:cache` and `route:cache` are both supported (no closures in routes, no
`env()` outside config files):

```bash
php artisan config:cache
php artisan route:cache
```

## Architecture & documentation

Code is layered: thin controllers → Form Requests (validation + authorization)
→ Services (business logic, transactions) → API Resources (output). Every
response uses the `{ success, message, data }` envelope, with `meta` on
paginated lists. Branch isolation is automatic via a global scope.

Project documentation lives in `docs/`:

| File | Owns |
|---|---|
| `docs/progress-tracker.md` | Task board, open questions, decisions log |
| `docs/tasks/*.md` | One ticket per task — the per-task source of truth |
| `docs/api-spec.md` + `docs/api/*.md` | Envelope/error conventions + per-module endpoint contracts |
| `docs/database-schema.md` | Tables, columns, types, constraints, indexes |
| `docs/architecture-context.md` | Layers, storage model, payment/result models, invariants |
| `docs/project-overview.md` | Goals, roles, flows, scope |
| `ai-workflow.md` | Scoping, splitting, protected files, exit criteria |
