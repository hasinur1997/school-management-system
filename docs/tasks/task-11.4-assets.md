# Task 11.4 — Assets CRUD & Summary

| Field | Value |
|---|---|
| Phase | 11 — Finance |
| Status | `todo` |
| Depends on | 11.3 |
| Blocks | Reports 13.3 |
| Spec references | `docs/api/finance.md`, schema → `assets` |
| Estimated size | One sitting (small) |

## Background
Asset register: name, description, value, purchase date — with "total assets at a glance".

## Objective
Assets CRUD (`asset.manage`) + summary endpoint.

## What To Implement
Migration per schema (status: in_use|damaged|disposed default in_use); CRUD routes with filters `status`, `search`, sorts value/purchase_date; `GET /assets/summary` — SQL aggregates only.

## API Contract
### POST /api/v1/assets
Request: `{ "name": "Projector", "value": "45000.00", "description": "Epson, Room 3", "purchase_date": "2026-02-01" }` → 201 (status in_use).
### GET /assets/summary — 200:
```json
{ "success": true, "message": "OK", "data": { "total_value": "385000.00", "count": 14, "by_status": { "in_use": { "count": 12, "value": "360000.00" }, "damaged": { "count": 2, "value": "25000.00" }, "disposed": { "count": 0, "value": "0.00" } } } }
```
Disposed assets excluded from `total_value`? **Decision: total_value = in_use + damaged; disposed excluded.**

## Success Criteria
- [ ] Summary in single aggregate query; disposed exclusion rule; filters/sorts; tests green

## Required Tests
1. CRUD + status transitions; 2. summary math incl. disposed exclusion; 3. filters

## Out of Scope
Depreciation (not in spec).

## Completion Protocol
Set Status `done`, tick 11.4, log surprises.
