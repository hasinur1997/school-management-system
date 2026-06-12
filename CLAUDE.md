# CLAUDE.md — School Management System (Laravel REST API)

Framework versions, artisan/tooling conventions, Pint, PHPUnit, and doc-search rules come from the **Laravel Boost guidelines** in this project — follow them; they reflect the actually installed versions (composer.json is the source of truth). This file adds the project's domain rules on top.

## How To Work (read order per session — do NOT read everything)

1. Open `docs/progress-tracker.md`, find the first unchecked task.
2. Read that task's ticket in `docs/tasks/` — tickets are **self-contained**: background, API contract with exact success/failure JSON, success criteria, required tests.
3. Consult other docs **only when the ticket references them**: `docs/database-schema.md` for the tables it names, the ticket's `docs/api/*.md` file for endpoint details, `docs/architecture-context.md` when making a structural decision, `docs/project-overview.md` for scope questions.
4. Implement → tests green → tick the board, set ticket Status `done`, log surprises in the tracker's Decisions Log.

Do not bulk-read the docs folder or all tickets up front. The full workflow (scoping, splitting, protected files) is in `ai-workflow.md` — read it once, early, not every session.

**Documentation carve-out:** Boost's "only create documentation files if explicitly requested" does NOT apply to `docs/progress-tracker.md`, ticket Status fields, or spec files the workflow requires updating — those updates are explicitly requested and mandatory. It DOES apply to everything else (no unsolicited READMEs, summaries, or notes).

## Architecture Rules

1. **Thin controllers** — resolve validated input → one service call → Resource. No queries, logic, or transactions in controllers.
2. **Form Requests** for all input (authorization in `authorize()`); **API Resources** for all output; envelope `{ success, message, data }` everywhere, `meta` on paginated lists.
3. **Services own business logic**; multi-step writes in `DB::transaction()`.
4. **Policies** for record-level access (parent → linked students only; student → self only).
5. **PHP backed enums** for every status column.
6. **Branch scoping is automatic**: `BelongsToBranch` trait + `BranchScope` global scope, auto-stamping `branch_id` on create. Never manual `where('branch_id', …)`; never accept `branch_id` from non-super-admin input. Out-of-branch records → **404, not 403**.
7. **Authorization is permission-based** (`marks.entry`), never role-name checks. Super admin bypasses via `Gate::before`.

## Performance Rules

- No N+1 — eager load everything Resources touch; `Model::shouldBeStrict()` outside production.
- Every list endpoint paginated (default 15, max 100).
- Reports aggregate in SQL (`SUM`/`GROUP BY`), never PHP loops.
- Bulk actions (promotion, invoice generation, attendance, marks) use bulk upsert/insert in chunks of 500 — never per-row `create()` in loops.
- Cache settings, grading scale, fee structures, academic structure; invalidate on write; `Cache::` facade only (driver-agnostic).
- Email/SMS/bulk-PDF/external APIs → queued jobs, never inline in requests. Jobs idempotent with explicit tries/backoff.
- PDFs stream; never base64 in JSON; generated on demand (TC document is the only stored PDF).

## Hard Invariants (never violate)

- Money is `DECIMAL(12,2)` (decimal strings in JSON); financial mutations in transactions; each successful payment → exactly one income entry + one receipt.
- Payment callbacks (SSLCommerz IPN) are idempotent — replay must be a no-op.
- Published results are immutable; grading-scale edits never alter past results. Annual GPA = 0.25·S1 + 0.25·S2 + 0.50·Final.
- TC students excluded from attendance/invoicing/promotion via query scope, not per-feature conditionals.
- Public surface = admission form + payment callbacks + public settings only; everything else behind Sanctum + permissions.

## Never Do

`DB::raw` with interpolated input · float/double for money · `env()` outside config · lazy loading in loops/Resources · swallowing exceptions in services · divergent envelope shapes · editing `vendor/` or published package migrations (extend with new migrations).

## Documentation Map (consult on demand)

| File | Owns |
|---|---|
| `docs/progress-tracker.md` | Task board, open questions, decisions log — the project state |
| `docs/tasks/*.md` | One ticket per task — the per-task source of truth |
| `docs/api-spec.md` + `docs/api/*.md` | Envelope/error conventions + per-module endpoint contracts |
| `docs/database-schema.md` | Tables, columns, types, constraints, indexes |
| `docs/architecture-context.md` | Layers, storage model, payment/result models, invariants |
| `docs/project-overview.md` | Goals, roles, flows, scope |
| `ai-workflow.md` | Scoping, splitting, protected files, exit criteria |

If a requirement is ambiguous, record an open question in the tracker — do not invent behavior.
