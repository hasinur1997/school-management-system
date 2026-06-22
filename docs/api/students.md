# Students API — Phase 4

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /students | student.view | Filters: class_id, section_id, session_id, status, search (name_en/name_bn/admission_no/father_mobile) |
| GET | /students/{id} | student.view + policy | Full profile; students/parents: own/linked only |
| PUT | /students/{id} | student.update | Update profile fields (not admission_no) |
| PATCH | /students/{id}/status | student.update | `{ "status": "active|inactive" }` (tc only via TC module) |
| POST | /students/{id}/photo | student.update | multipart `photo` |
| GET | /students/{id}/enrollments | student.view + policy | Class history, newest first |
| GET | /parents | parent.manage | Paginated; search by name/phone |
| POST | /parents | parent.manage | `{ name, phone, email?, relation, student_ids: [] }` → user + parent + links; queues credentials |
| POST | /parents/{id}/students | parent.manage | `{ "student_id" }` — link |
| DELETE | /parents/{id}/students/{student} | parent.manage | Unlink |
| GET | /me/students | parent role | Parent's linked students (id, name, class, section, photo) |

Student list rows return the compact shape: id, admission_no, name_en, name_bn, current class/section/roll, status, photo URL. Full structured address data only on `show`.
