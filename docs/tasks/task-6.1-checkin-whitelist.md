# Task 6.1 — Teacher Attendance Tables & IP Whitelist CRUD

| Field | Value |
|---|---|
| Phase | 6 — Teacher Attendance |
| Status | `done` |
| Depends on | 5.3 |
| Blocks | 6.2, 6.3 |
| Spec references | `docs/api/teacher-attendance.md`, schema → `teacher_attendances`, `checkin_ip_whitelists` |
| Estimated size | One sitting |

## Background
Teacher check-in is IP-restricted per branch. This task lays both tables and the whitelist management endpoints (cached — checked on every check-in).

## Objective
Migrations + whitelist CRUD (`teacher_attendance.manage`) with cache + invalidation.

## What To Implement
1. Migrations per schema. `teacher_attendances`: teacher_id, date, check_in_at, check_out_at null, check_in_ip VARCHAR(45), status, corrected_by null; unique (teacher_id, date). `checkin_ip_whitelists`: branch_id, ip_address VARCHAR(45) (exact or CIDR), label null, is_active; unique (branch_id, ip_address).
2. Whitelist CRUD: `GET/POST /checkin-ips`, `PUT/DELETE /checkin-ips/{id}`; `WhitelistService::activeFor(branch)` cached, forgotten on any write.

## API Contract
### POST /api/v1/checkin-ips
Request: `{ "ip_address": "103.4.5.0/24", "label": "School WiFi" }` → 201 entry. Failures: invalid IP/CIDR format → 422 `errors.ip_address`; duplicate in branch → 422.
### GET /checkin-ips — 200 branch list. PUT — toggle `is_active`/edit. DELETE — 200.

## Success Criteria
- [ ] Both migrations exact; IP/CIDR format validation; cache invalidation proven by test; tests green

## Required Tests
1. CRUD happy paths; invalid CIDR 422; duplicate 422
2. cache: read → write → read reflects change
3. branch isolation (admin of branch 1 can't see branch 2 IPs)

## Out of Scope
Check-in logic (6.2) — including CIDR matching.

## Completion Protocol
Set Status `done`, tick 6.1, log surprises.
