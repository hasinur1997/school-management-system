# Task 8.4 — Result Sheet PDFs

| Field | Value |
|---|---|
| Phase | 8 — Results |
| Status | `todo` |
| Depends on | 8.3 |
| Blocks | — |
| Spec references | `docs/api/results.md`, `architecture-context.md` → PDFs on demand |
| Estimated size | One sitting |

## Background
Printable marksheets — the school's hard-copy requirement. Rendered on demand with dompdf, streamed, never stored.

## Objective
`GET /enrollments/{id}/results/{exam}/pdf` and `GET /enrollments/{id}/annual-result/pdf`.

## What To Implement
1. Blade PDF templates (A4): school/branch header + logo, student block (bilingual name, admission no, class/section/roll, session), subject table (marks/grade/point), GPA + grade + pass status, signature placeholders (class teacher / head). Annual version adds the three component GPAs and weighting line.
2. Controller methods: same policy as 8.3; published-only for students/parents; 404 if result missing; `->stream()` with filename `result-{admission_no}-{exam_type}.pdf`.

## API Contract
Success — 200, `Content-Type: application/pdf`, `Content-Disposition: inline; filename="result-MP-2026-0009-final.pdf"`.
Failures (JSON envelope): result not generated → 404 `{ "message": "Result not available" }`; unpublished requested by student/parent → 404; policy fail → 404.

## Success Criteria
- [ ] Both PDFs render with real data (smoke test asserts 200 + pdf content-type + non-trivial size)
- [ ] Bangla text renders (embed a Bangla-capable TTF — note font choice in Decisions Log)
- [ ] Policy/published rules identical to 8.3; tests green

## Required Tests
1. exam + annual PDF 200/pdf-type for staff; 2. student own published 200; unpublished 404; 3. missing result 404

## Out of Scope
Report PDFs (13.4) · ID cards (12.x).

## Completion Protocol
Set Status `done`, tick 8.4, log surprises.
