# Task 6.2 — Teacher Check-in / Check-out

| Field | Value |
|---|---|
| Phase | 6 — Teacher Attendance |
| Status | `todo` |
| Depends on | 6.1 |
| Blocks | 6.3 |
| Spec references | `docs/api/teacher-attendance.md` |
| Estimated size | One sitting |

## Background
The core requirement: a teacher may check in only from permitted IPs. Late threshold comes from settings (table arrives in 14.1 — until then read via a `SettingsRepository` stub returning config defaults; swap to DB in 14.1).

## Objective
`POST /teacher-attendance/check-in` and `/check-out`, teacher role only.

## What To Implement
1. `IpMatcher` utility: exact match + CIDR contains (IPv4 required, IPv6 exact-only acceptable) — unit tested.
2. `CheckinService::checkIn(teacher, ip)`: resolve request IP → match active whitelist of teacher's branch → reject or create today's record (`status = present`, or `late` if `now() > teacher_late_threshold` setting, default 09:00). `checkOut()` sets check_out_at on today's record.
3. Routes (teacher role middleware), no request body.

## API Contract
### POST /api/v1/teacher-attendance/check-in — 200:
```json
{ "success": true, "message": "Checked in", "data": { "id": 7, "date": "2026-06-11", "check_in_at": "2026-06-11T08:32:10+06:00", "status": "present" } }
```
Failures: IP not whitelisted → 403 `{ "success": false, "message": "Check-in is not permitted from this network" }` (no whitelist details leaked); already checked in → 409; non-teacher → 403.
### POST /check-out — 200 record with check_out_at. No check-in today → 409 ("Not checked in"); already out → 409.

## Success Criteria
- [ ] Exact + CIDR matching correct (unit tests incl. boundary addresses)
- [ ] Late threshold applied; all 403/409 cases; nothing about the whitelist leaks in errors
- [ ] Tests green

## Required Tests
1. IpMatcher unit: exact, inside-CIDR, outside-CIDR, boundary
2. check-in from allowed/blocked IP; late vs present by time (travel())
3. double check-in 409; checkout without checkin 409
4. non-teacher 403

## Out of Scope
Admin browse/correction (6.3) · settings table (14.1 — stub here).

## Completion Protocol
Set Status `done`, tick 6.2, log surprises.
