# Task 12.1 — Single ID Card PDF

| Field | Value |
|---|---|
| Phase | 12 — Documents |
| Status | `todo` |
| Depends on | 11.4 |
| Blocks | 12.2 |
| Spec references | `docs/api/documents.md` |
| Estimated size | One sitting |

## Background
On-demand ID card: photo, name_en, admission no, current class/section/roll, session, branch name + logo, validity (session end date). No table — rendered from live data.

## Objective
`GET /students/{id}/id-card` (`idcard.generate`) streaming a card-sized PDF.

## What To Implement
Blade template (CR80-ish card layout, front only, school colors), `IdCardService::render(student)` gathering active enrollment + branch + photo (placeholder silhouette if missing); dompdf stream `idcard-{admission_no}.pdf`.

## API Contract
Success — 200 application/pdf, `inline; filename="idcard-MP-2026-0009.pdf"`.
Failures: student with no active enrollment → 422 `{ "message": "Student has no active enrollment" }`; TC/inactive student → 422; unknown/other-branch id → 404.

## Success Criteria
- [ ] Renders with and without photo; correct enrollment data; TC blocked; tests green

## Required Tests
1. 200 pdf-type happy; 2. missing photo still renders; 3. TC student 422; cross-branch 404

## Out of Scope
Batch (12.2) · back side / barcode (open question if wanted).

## Completion Protocol
Set Status `done`, tick 12.1, log surprises.
