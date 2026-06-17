# Task 12.2 — Batch ID Cards (Queued)

| Field | Value |
|---|---|
| Phase | 12 — Documents |
| Status | `done` |
| Depends on | 12.1 |
| Blocks | — |
| Spec references | `docs/api/documents.md`, CLAUDE.md → queue rule |
| Estimated size | One sitting |

## Background
A whole class's cards = heavy PDF work → queued job + poll, per the no-bulk-PDF-in-request rule.

## Objective
`POST /id-cards/batch` (202) and `GET /id-cards/batch/{batch_id}`.

## What To Implement
`id_card_batches` table (id uuid, branch_id, class_id, section_id null, session_id, status processing|done|failed, file_path null, requested_by, timestamps); `BuildIdCardBatch` job: chunk eligible students (active enrollment, non-TC), render merged PDF (12.1 template), store on local disk `idcards/batches/{uuid}.pdf`, mark done. Poll endpoint returns status; when done, `url` = authenticated download route streaming the file. Cleanup note: files older than 7 days pruned by scheduled command.

## API Contract
### POST /api/v1/id-cards/batch
Request: `{ "class_id": 7, "section_id": 12, "session_id": 1 }`
Success — 202 `{ "data": { "batch_id": "9f1c-...", "status": "processing" } }`. Empty cohort → 422 "No eligible students".
### GET /id-cards/batch/9f1c-... — 200 `{ "data": { "status": "done", "url": "/api/v1/id-cards/batch/9f1c-.../download" } }` | processing (no url) | failed (+message). Foreign batch id → 404.
### GET .../download — 200 pdf | 409 if not done.

## Success Criteria
- [x] Job offloaded (Queue::fake assertion + real run in test queue), chunked, TC excluded
- [x] Poll lifecycle states; download guard; cleanup command scheduled; tests green

## Required Tests
1. dispatch 202 + job queued; run job → done + file exists
2. poll states; download before done 409; foreign batch 404
3. TC student absent from merged set (count assertion)

## Out of Scope
Teacher ID cards (not in spec — open question).

## Completion Protocol
Set Status `done`, tick 12.2, log surprises.
