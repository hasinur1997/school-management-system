# Teacher Attendance API — Phase 6

| Method | URI | Permission | Description |
|---|---|---|---|
| POST | /teacher-attendance/check-in | teacher role | Self check-in; request IP validated against branch whitelist |
| POST | /teacher-attendance/check-out | teacher role | Self check-out (same day's record) |
| GET | /me/teacher-attendance | teacher role | Own records: `?month=&year=` |
| GET | /teacher-attendance | teacher_attendance.view | Browse all; filters: teacher_id, date, status, month, year |
| PUT | /teacher-attendance/{id} | teacher_attendance.manage | Admin correction: status, check_in_at, check_out_at; stamps corrected_by |
| GET | /checkin-ips | teacher_attendance.manage | List branch whitelist |
| POST | /checkin-ips | teacher_attendance.manage | `{ "ip_address": "exact or CIDR", "label?" }` |
| PUT | /checkin-ips/{id} | teacher_attendance.manage | Update ip/label/is_active |
| DELETE | /checkin-ips/{id} | teacher_attendance.manage | Remove |

## POST /teacher-attendance/check-in
No body. Behavior: resolve request IP → match against active whitelist entries for the teacher's branch (exact or CIDR) → create today's record with `check_in_at = now()`, `check_in_ip`, status `present` (or `late` after the branch's late-threshold setting).
Errors: 403 `IP not permitted` (message includes no whitelist details); 409 already checked in today.
Whitelist lookups are cached; cache invalidated on any /checkin-ips write.
