# Task 1.7 — BranchScope Global Scope & Isolation

| Field | Value |
|---|---|
| Phase | 1 — Foundation |
| Status | `done` |
| Depends on | 1.5 |
| Blocks | Every branch-scoped module (correctness depends on this) |
| Spec references | `docs/architecture-context.md` → Branch Scoping Model, `CLAUDE.md` → rule 7 |
| Estimated size | One sitting |

## Background
Until now services filtered by branch manually. This task replaces that with the permanent mechanism: a global Eloquent scope + trait so isolation is automatic and unforgettable. This is a **security boundary** — the most important task in Phase 1.

## Objective
A `BelongsToBranch` trait that (a) constrains every query to the auth user's branch, (b) auto-stamps `branch_id` on create, (c) is bypassed for super admin, who may filter via `?branch_id=` or request `all`.

## What To Implement
1. `App\Models\Scopes\BranchScope`: applies `where branch_id = auth user's branch` for non-super-admins; no constraint for super admin; no-op in console without an auth user (seeders/commands handle branch explicitly).
2. `App\Models\Concerns\BelongsToBranch` trait: boots the scope, `creating` hook stamps `branch_id` from the auth user (non-super-admin); defines `branch()` relation.
3. Apply the trait to `SchoolClass` (and remove manual branch filtering from 1.5/1.6 services).
4. Super admin filtering convention: services accept optional `branch_id` (validated to exist) — `all` skips the filter; document in code.
5. 404 semantics: out-of-branch `show/update/delete` resolves to model-not-found → 404 envelope (never 403).

## API Contract
No new endpoints. Behavior change (example with classes):
- Admin of branch 1: `GET /classes` returns only branch-1 classes; `GET /classes/{branch2_class_id}` → 404:
```json
{ "success": false, "message": "Resource not found" }
```
- Super admin: `GET /classes?branch_id=2` → branch-2 only; `?branch_id=all` → everything; omitted → current behavior per service default (all).
- Create as branch-1 admin ignores any submitted `branch_id` and stamps 1.

## Success Criteria
- [x] Cross-branch reads/writes impossible for non-super-admins (404, not 403)
- [x] `branch_id` in request bodies is ignored for non-super-admins
- [x] Super admin `branch_id` filter and `all` both work
- [x] Manual branch `where` clauses removed from earlier services
- [x] Tests green

## Required Tests
1. branch-1 admin cannot list/show/update/delete branch-2 class (list excludes; show 404)
2. create stamps branch automatically; submitted branch_id ignored
3. super admin sees all; filter by branch works
4. seeder/console context does not crash (no auth user)

## Out of Scope
Applying the trait to future models (each module's task does that as it creates its models).

## Completion Protocol
Set Status `done`, tick 1.7, log surprises.
