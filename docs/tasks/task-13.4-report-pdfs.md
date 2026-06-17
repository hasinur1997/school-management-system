# Task 13.4 — Report PDF Exports

| Field | Value |
|---|---|
| Phase | 13 — Reports |
| Status | `done` |
| Depends on | 13.3 |
| Blocks | — |
| Spec references | `docs/api/reports.md` |
| Estimated size | One sitting (small) |

## Objective
`GET /reports/{type}/pdf` — any of the seven reports as a streamed PDF with identical query params.

## What To Implement
Route constraint `type` ∈ income|expense|profit-loss|students|teachers|assets|fees; reuse 13.2/13.3 services for data; one generic report Blade layout (header, filter line, tables) + per-type partials; stream `report-{type}-{from}-{to}.pdf`.

## API Contract
Success — 200 application/pdf. Failures: invalid type → 404 route; filter 422s as JSON envelope; 403 without report.view.

## Success Criteria
- [x] All seven types render (parameterized smoke tests); data identical to JSON endpoints (shared service asserted); tests green

## Required Tests
1. each type → 200 pdf; 2. invalid type 404; 3. custom-range filename correctness

## Out of Scope
Excel export (open question if wanted).

## Completion Protocol
Set Status `done`, tick 13.4, log surprises.
