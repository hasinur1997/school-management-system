# Results API — Phase 8

| Method | URI | Permission | Description |
|---|---|---|---|
| POST | /exams/{id}/results/generate | result.generate | Compute exam_results for all enrollments (overwrites unpublished) |
| POST | /exams/{id}/results/publish | result.generate | Freeze: sets published_at, exam status `published` |
| GET | /exams/{id}/results | result.view | Tabular results; filters: section_id, is_passed |
| POST | /annual-results/generate | result.generate | `{ session_id, class_id }` — requires all 3 exams published |
| POST | /annual-results/publish | result.generate | `{ session_id, class_id }` |
| GET | /results/search | result.view | `?admission_no=` or `?session_id=&class_id=&section_id=&roll_no=` |
| GET | /enrollments/{id}/results | result.view + policy | All 3 exam results + annual for that enrollment |
| GET | /enrollments/{id}/results/{exam}/pdf | result.view + policy | Per-exam marksheet PDF (stream) |
| GET | /enrollments/{id}/annual-result/pdf | result.view + policy | Annual result sheet PDF (stream) |
| GET | /me/results | student/parent | Own (or linked child via `?student_id=`) results by session |

## Computation rules (delegated to ResultService — single source)
- Per-exam GPA = average of subject grade_points; `is_passed = false` if any subject grade is F.
- Annual GPA = 0.25·S1 + 0.25·S2 + 0.50·Final, rounded to 2 dp; `is_passed` requires final exam passed and annual grade not F.
- Generate is idempotent until publish; after publish → 409 on regenerate.
- Students/parents can only read results where `published_at` is set; staff can preview unpublished.
