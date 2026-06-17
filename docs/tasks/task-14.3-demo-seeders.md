# Task 14.3 — Demo Seeders & Factory Review

| Field | Value |
|---|---|
| Phase | 14 — Settings, Dashboard & Polish |
| Status | `done` |
| Depends on | 14.2 |
| Blocks | 14.4 |
| Spec references | all docs |
| Estimated size | One sitting |

## Objective
`php artisan migrate:fresh --seed` yields a fully explorable system: one branch completely populated end-to-end.

## What To Implement
`DemoSeeder` (env-guarded, never production): both branches; sessions 2025+2026(current); classes 1–10 + sections; subjects; 10 teachers + assignments; ~200 students with parents (via factories exercising the real approval service where practical); a month of attendance; full marks for three exams of one class; generated + published results incl. annual; one executed promotion (2025→2026); fee structures + 2 months invoices; mixed payments (cash + fake-gateway) producing incomes; expenses, assets, categories; one TC student; whitelist IP 127.0.0.1. Review every factory for realistic Bangla/English names and valid relationships.

## API Contract
None. Acceptance is behavioral: after fresh-seed, the dashboard, reports, result search, and promotion preview all return non-empty, consistent data.

## Success Criteria
- [x] fresh --seed completes < 2 min, idempotent guards on static seeders
- [x] Smoke test hitting dashboard + 3 reports + result search against seeded data passes
- [x] No factory violates uniques/constraints across repeated runs; tests green

## Required Tests
1. seeded smoke suite (listed endpoints non-empty 200s); 2. seeder runs twice without constraint violations

## Out of Scope
Production seed data.

## Completion Protocol
Set Status `done`, tick 14.3, log surprises.
