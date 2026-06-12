# AI Workflow

## Approach

Build this project incrementally using a spec-driven workflow. Context files define what to build (`docs/project-overview.md`), how to build it (`docs/architecture-context.md`, `CLAUDE.md`), the data model (`docs/database-schema.md`), the endpoint contracts (`docs/api-spec.md` index + `docs/api/*.md` module files), and the current state of progress (`docs/progress-tracker.md`). Always implement against these specs — do not infer or invent behavior from scratch.

## Build Order

Implement in this sequence. Each phase is broken into ordered tasks in `docs/progress-tracker.md`; never start a task before the previous task is done, and never start a phase before the previous phase is complete.

1. Foundation — Sanctum auth, spatie roles/permissions seed, branches, academic structure (sessions, classes, sections, subjects)
2. User and teacher management, credential dispatch
3. Public admission flow and approval pipeline
4. Student management — profiles, parent linking, statuses, enrollments
5. Student attendance
6. Teacher attendance and IP whitelist settings
7. Exams, grading scale, mark entry
8. Result computation (per-exam and 25/25/50 annual) and result PDF
9. Promotion (bulk and individual)
10. Fees — invoices, SSLCommerz, local payment, receipt PDF
11. Income, expense, assets
12. ID card PDF and TC system
13. Reports with filters
14. Settings, dashboard, seeders, final polish

## Scoping Rules

- The unit of work is one task from `docs/progress-tracker.md`. Work on exactly one task at a time, in board order.
- Prefer small, verifiable increments over large speculative changes.
- Do not combine unrelated system boundaries in a single implementation step.
- A unit always ships complete vertically: migration → model → routes → Form Request → service → Resource → controller → tests.

## When To Split Work

Split an implementation step if it combines:

- Schema changes and business logic for two different modules
- Synchronous request handling and queued job changes (e.g., payment endpoint and receipt email job are separate units)
- Multiple unrelated API route groups
- An external integration (SSLCommerz, SMS/email) and internal domain logic — build the domain unit first with the integration faked, then the integration unit
- Behavior that is not clearly defined in the context files

If a change cannot be verified end to end with `php artisan test` quickly, the scope is too broad — split it.

## Handling Missing Requirements

- Do not invent product behavior that is not defined in the context files.
- If a requirement is ambiguous, resolve it in the relevant context file before implementing.
- If a requirement is missing, add it as an open question in `docs/progress-tracker.md` before continuing, choose nothing silently.
- Defaults already decided (do not re-ask): full-month payments unless the partial toggle is on; invoices generated on the 1st; parents created by admin; an F in any final-exam subject blocks promotion; non-super-admins belong to one branch.

## Protected Foundation Components

Do not modify framework or package internals unless explicitly instructed. This includes:

- `vendor/*` — never edit, ever
- Laravel skeleton files (`bootstrap/`, `public/index.php`, framework base classes)
- Published package migrations (spatie permission tables, medialibrary `media` table, Sanctum `personal_access_tokens`) — extend via new migrations, never edit published ones
- Published package config files — change values, do not restructure

Project-specific behavior belongs in app-level code: `app/Services`, `app/Models`, `app/Http`, `app/Policies`, `app/Jobs`, new migrations. Wrap packages behind services (e.g., `PaymentGatewayService` around SSLCommerz) rather than scattering package calls.

## Keeping Docs In Sync

Update the relevant context file whenever implementation changes:

- System boundaries or layer responsibilities → `docs/architecture-context.md`
- Tables, columns, indexes, or constraints → `docs/database-schema.md`
- Endpoints, permissions, or payload shapes → the relevant `docs/api/*.md` file (shared conventions → `docs/api-spec.md`)
- Code conventions or rules → `CLAUDE.md`
- Feature scope → `docs/project-overview.md`

Progress state must reflect the actual state of the implementation, not the intended state. A migration that diverges from `database-schema.md` without updating the doc is a defect.

## Before Moving To The Next Unit

1. The current unit works end to end within its defined scope: routes respond per the unit's `docs/api/*.md` contract, with the standard envelope from `docs/api-spec.md`.
2. `php artisan test` passes, including the unit's tests for the happy path, permission denial, branch isolation, and validation failure (plus idempotency for financial units).
3. No invariant defined in `docs/architecture-context.md` was violated — especially: money in transactions, immutable published results, TC exclusion by scope, permission-based authorization.
4. `docs/progress-tracker.md` reflects the completed work, including any open questions raised.
5. Seeders and factories cover any new reference data the unit introduced.
