# Teachers API — Phase 2

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /teachers | teacher.view | Paginated; filters: status, search (name/email/phone/designation) |
| POST | /teachers | teacher.create | Create profile + user; queues credential email |
| GET | /teachers/{id} | teacher.view | Profile + assignments for current session |
| PUT | /teachers/{id} | teacher.update | Update profile fields |
| PATCH | /teachers/{id}/status | teacher.update | `{ "status": "active|inactive" }`; inactive disables login |
| POST | /teachers/{id}/resend-credentials | teacher.create | Regenerates password, queues email |
| POST | /teachers/{id}/photo | teacher.update | multipart `photo` (jpg/png, ≤2MB) |

## POST /teachers
Request: `{ "name", "email", "phone", "designation", "joining_date?" }`
Behavior: creates `users` row (random password) + `teachers` row + assigns teacher role, then dispatches `SendCredentials` job — all in one transaction (job dispatched after commit).
Response 201 with teacher resource. 422 on duplicate email/phone.
