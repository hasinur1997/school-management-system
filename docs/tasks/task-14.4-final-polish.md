# Task 14.4 — Final Pass

| Field | Value |
|---|---|
| Phase | 14 — Settings, Dashboard & Polish |
| Status | `todo` |
| Depends on | 14.3 |
| Blocks | — (last task) |
| Spec references | `CLAUDE.md`, `.claude/commands/performance-audit.md` |
| Estimated size | One sitting |

## Objective
Backend Phase 1 ships: clean, documented, audited.

## What To Implement
1. Verify `config:cache` + `route:cache` work (no closures in routes, no env() outside config).
2. `README.md`: setup (env vars incl. SSLCommerz sandbox), migrate+seed, queue worker, scheduler cron, test command, doc map pointer.
3. Pint clean; remove dead code/unused imports; `composer audit` reviewed.
4. Full suite green; strict mode on in non-production confirmed.
5. Run `/performance-audit`; implement approved high-ROI fixes; record outcomes in the Decisions Log.
6. Close out `docs/progress-tracker.md`: all boxes ticked, open questions resolved or explicitly carried to Phase 2 (frontend).

## API Contract
None new. Regression guarantee: the entire existing suite is the contract.

## Success Criteria
- [ ] route:cache/config:cache succeed; README complete; Pint clean
- [ ] Audit run, findings addressed or logged with rationale
- [ ] Tracker fully reconciled; suite green

## Required Tests
Full suite (no new tests required beyond audit-driven ones).

## Out of Scope
Frontend (project Phase 2) · deployment pipeline.

## Completion Protocol
Set Status `done`, tick 14.4 — Phase 1 complete. 🎉
