# Task 8.3.1 — Public Result Reads

| Field | Value |
|---|---|
| Phase | 8 — Results |
| Status | `done` |
| Depends on | 8.3 |
| Blocks | 8.4 |
| Spec references | `docs/api/results.md` |
| Estimated size | One sitting |

## Background
The public site needs an unauthenticated result lookup where a visitor selects branch from a dropdown, then provides roll no, class, year, and semester. Because this is public, the endpoint must expose only published results and only the fields required to identify the result.

## Objective
`GET /api/v1/public/results?branch_id=&roll_no=&class_id=&year=&semester=`

## What To Implement
1. Public, unauthenticated result endpoint under the existing `/public` API group.
2. Validate required `branch_id`, `roll_no`, `class_id`, `year`, and `semester`.
3. Resolve `year` to `academic_sessions.name`.
4. Resolve the enrollment by selected branch, `session_id`, `class_id`, and `roll_no`.
5. Return only the selected published semester result and its subject marks.
6. Rate-limit the endpoint.
7. If the tuple matches multiple sections, return 422 instead of guessing.
8. Accept `semister` as a backwards-compatible alias for `semester`.
9. Use `/api/v1/public/settings` as the dropdown source for active branches and their active classes.

## API Contract
### GET /api/v1/public/results?branch_id={branch_public_id}&roll_no=12&class_id={class_public_id}&year=2026&semester=final — 200:
```json
{
  "success": true,
  "message": "OK",
  "data": {
    "student_information": {
      "roll_no": 12,
      "student_name": "Rahima Khatun",
      "father_name": "Abdul Karim",
      "mother_name": "Amena Begum",
      "class": "Class 7",
      "section": "A",
      "session": "2026",
      "semester": "final",
      "date_of_birth": "2014-03-09",
      "result": "5.00"
    },
    "subjects": [
      { "subject_code": "MATH7", "subject_name": "Mathematics", "marks": "90.00", "grade": "A+" }
    ]
  }
}
```

Valid `semester` values: `first_semester`, `second_semester`, `final`. The misspelled query key `semister` is also accepted.

Failures: missing/invalid input → 422; selected class outside selected branch → 422; no match or unpublished result → 404; duplicate roll across sections for the same class/year → 422.

## Success Criteria
- [x] Public API exists and is throttled.
- [x] Branch dropdown source exists via `/api/v1/public/settings`; lookup accepts selected `branch_id`.
- [x] Published-only selected semester result + subject marks.
- [x] No internal ids or unpublished draft state exposed.
- [x] Exact-match lookup is eager-loaded and ambiguity-safe.

## Required Tests
1. successful public lookup returns student information and subject marks
2. unpublished result is hidden
3. duplicate roll across sections is rejected
4. selected class outside branch is rejected
5. missing input validates

## Out of Scope
PDFs (8.4).

## Completion Protocol
Set Status `done`, tick 8.3.1, log surprises.
