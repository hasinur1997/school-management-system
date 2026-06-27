# Students API — Phase 4

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /students | student.view | Filters: class_id, section_id, session_id, status, search (name_en/name_bn/admission_no/father_mobile) |
| GET | /students/trash | student.delete | Paginated trashed students; same filters as list |
| GET | /students/{id} | student.view + policy | Full profile; students/parents: own/linked only |
| PUT | /students/{id} | student.update | Update profile fields (not admission_no) |
| PATCH | /students/{id}/status | student.update | `{ "status": "active|inactive" }` (tc only via TC module) |
| POST | /students/{id}/photo | student.update | multipart `photo` |
| GET | /students/{id}/enrollments | student.view + policy | Class history, newest first |
| DELETE | /students/{id} | student.delete | Soft delete; moves student to trash and disables login |
| POST | /students/bulk-delete | student.delete | `{ "ids": ["public_id", ...] }` → `{ "deleted": n }` |
| POST | /students/{id}/restore | student.delete | Restore from trash; re-enables login when status is active |
| POST | /students/bulk-restore | student.delete | `{ "ids": [...] }` → `{ "restored": n }` |
| DELETE | /students/{id}/force | student.delete | Permanently delete a trashed student with no dependent history |
| POST | /students/bulk-force-delete | student.delete | `{ "ids": [...] }` → `{ "deleted": n }` |
| GET | /parents | parent.manage | Paginated; search by name/phone |
| GET | /parents/trash | parent.manage | Paginated trashed parents; search by name/phone |
| POST | /parents | parent.manage | `{ name, phone, email?, relation, student_ids: [] }` → user + parent + links; queues credentials |
| POST | /parents/{id}/students | parent.manage | `{ "student_id" }` — link |
| DELETE | /parents/{id}/students/{student} | parent.manage | Unlink |
| DELETE | /parents/{id} | parent.manage | Soft delete; moves parent to trash and disables login |
| POST | /parents/bulk-delete | parent.manage | `{ "ids": ["public_id", ...] }` → `{ "deleted": n }` |
| POST | /parents/{id}/restore | parent.manage | Restore from trash; re-enables login |
| POST | /parents/bulk-restore | parent.manage | `{ "ids": [...] }` → `{ "restored": n }` |
| DELETE | /parents/{id}/force | parent.manage | Permanently delete a trashed parent and its login |
| POST | /parents/bulk-force-delete | parent.manage | `{ "ids": [...] }` → `{ "deleted": n }` |
| GET | /me/students | parent role | Parent's linked students (id, name, class, section, photo) |

Student list rows return the compact shape: id, admission_no, name_en, name_bn, current class/section/roll, status, photo URL. Full structured address data only on `show`.

Students are soft-deleted. Deleted rows disappear from `/students` and appear in `/students/trash` with `deleted_at`; restore brings the profile and enrollment shell back. Permanent deletion is only for trashed students and is blocked when dependent financial or academic history exists.

Parents are soft-deleted. Deleted rows disappear from `/parents` and appear in `/parents/trash` with `deleted_at`; restore brings the profile and linked-student rows back and re-enables login. Permanent deletion is only for trashed parents and removes the parent login plus pivot links; student records are never deleted by parent deletion.
