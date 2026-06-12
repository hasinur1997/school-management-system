# Academic Structure API — Phase 1

CRUD follows the standard pattern: `index` (paginated, filterable), `store`, `show`, `update`, `destroy`. Destroy is RESTRICT-protected — deleting a record in use returns 409.

| Method | URI | Permission | Notes |
|---|---|---|---|
| GET/POST | /branches, /branches/{id} (GET/PUT/DELETE) | branch.manage | Super admin only |
| GET/POST | /sessions, /sessions/{id} | session.manage | `is_current` switch sets all others false |
| GET/POST | /classes, /classes/{id} | class.manage | Body: name, numeric_level |
| GET/POST | /classes/{class}/sections, /sections/{id} | class.manage | Body: name, class_teacher_id? |
| GET/POST | /classes/{class}/subjects, /subjects/{id} | subject.manage | Body: name, code?, full_marks, pass_marks |
| GET/POST | /teacher-assignments, /teacher-assignments/{id} | teacher.manage | Body: teacher_id, session_id, class_id, section_id?, subject_id? |

## Read endpoints for all roles
`GET /classes`, `GET /classes/{class}/sections`, `GET /classes/{class}/subjects` are readable by any authenticated user (dropdown data) — write operations need the permissions above. Responses are cached server-side per `CLAUDE.md`.

Filters: `GET /teacher-assignments?teacher_id=&class_id=&session_id=`.
