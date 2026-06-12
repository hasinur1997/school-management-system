# Task 13.1 — Report Filter Resolver

| Field | Value |
|---|---|
| Phase | 13 — Reports |
| Status | `todo` |
| Depends on | 12.3 |
| Blocks | 13.2–13.4 |
| Spec references | `docs/api/reports.md` |
| Estimated size | One sitting (small) |

## Background
Every report shares one filter contract: `period=weekly|monthly|yearly|custom`, `from/to`, `branch_id` (super admin, incl. `all`). Centralizing it keeps seven report endpoints consistent.

## Objective
`ReportFilter` value object + Form Request, fully unit-tested.

## What To Implement
`ReportFilterRequest`: validates period enum; custom requires from ≤ to; non-custom forbids from/to? **allow override: from/to win if present**; branch_id only honored for super admin (`all` ⇒ null filter), others forced to own branch. `ReportFilter::range()` → [start, end]: weekly = current ISO week Mon–Sun; monthly = current calendar month; yearly = current session start–end (fallback calendar year if no current session). Granularity helper: daily ≤ 62 days else monthly.

## API Contract
No endpoint — consumed by 13.2/13.3. Validation errors surface there as 422 (`errors.period`, `errors.from`, …): custom without from/to → 422; from > to → 422; non-super-admin branch_id silently ignored (forced own).

## Success Criteria
- [ ] All period resolutions unit-tested with frozen time; branch forcing; granularity helper; tests green

## Required Tests
1. weekly/monthly/yearly ranges (Carbon::setTestNow)
2. custom validation matrix; override behavior
3. branch forcing for admin; `all` for super admin

## Out of Scope
Actual report queries (13.2/13.3).

## Completion Protocol
Set Status `done`, tick 13.1, log surprises.
