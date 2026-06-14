# Task 7.2 — Exams CRUD

| Field | Value |
|---|---|
| Phase | 7 — Exams & Marks |
| Status | `done` |
| Depends on | 7.1 |
| Blocks | 7.3, Phase 8 |
| Spec references | `docs/api/exams-marks.md`, schema → `exams` |
| Estimated size | One sitting |

## Background
Three exams per class per session: first_semester, second_semester, final — enforced unique. Status lifecycle: upcoming → ongoing → completed → published (publish itself happens in 8.1; here `published` is only guarded against).

## Objective
Exams CRUD (`exam.manage` writes, `exam.view` reads).

## What To Implement
Migration per schema (`ExamType`, `ExamStatus` enums); CRUD routes `GET/POST /exams`, `GET/PUT /exams/{id}`; filters session_id/class_id/type/status; PUT cannot change session/class/type, cannot regress status, cannot edit a published exam (409).

## API Contract
### POST /api/v1/exams
Request: `{ "session_id": 1, "class_id": 7, "type": "first_semester", "name": "First Semester 2026", "start_date": "2026-04-01", "end_date": "2026-04-10" }`
Success — 201 exam. Failures: duplicate (session,class,type) → 422 "This exam already exists for the class"; invalid type → 422; dates inverted → 422.
### PUT /exams/{id} — name/dates/status only; editing published exam → 409 `{ "message": "Published exams cannot be modified" }`; status regression (completed → ongoing) → 422.

## Success Criteria
- [x] Uniqueness + immutability rules + status transition guard; filters; tests green

## Required Tests
1. create happy + duplicate 422; 2. update name ok; type change rejected; 3. published 409; regression 422; 4. filters

## Out of Scope
Publishing (8.1) · marks (7.3).

## Completion Protocol
Set Status `done`, tick 7.2, log surprises.
