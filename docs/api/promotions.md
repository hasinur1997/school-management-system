# Promotions API — Phase 9

| Method | URI | Permission | Description |
|---|---|---|---|
| GET | /promotions/preview | promotion.execute | Who will be promoted: `?session_id=&class_id=` |
| POST | /promotions/bulk | promotion.execute | Promote all passed students of a class |
| POST | /promotions/individual | promotion.execute | Promote (or hold) one student |
| GET | /promotions | promotion.view | History; filters: session_id, class_id, type |

## GET /promotions/preview
Response `data`: `{ eligible: [ { student, annual_gpa } ], not_eligible: [ { student, reason: "failed|no_result|tc" } ], to_class: { id, name } }` — `to_class` resolved by `numeric_level + 1`.

## POST /promotions/bulk
Request: `{ "from_session_id", "from_class_id", "to_session_id", "to_section_id", "roll_strategy": "by_merit|keep" }`
Behavior, one transaction (chunked bulk inserts): for each passed annual result → close old enrollment (`promoted`), create new enrollment in next class, log promotion. Failed students' enrollments → `failed` (they get a fresh enrollment in the same class for the new session). Requires annual results published; otherwise 409.
Response: `{ "promoted": n, "held": m }`.

## POST /promotions/individual
Request: `{ "student_id", "to_session_id", "to_class_id", "to_section_id", "roll_no" }` — allows overriding (e.g., promoting despite a fail, with promotion.override permission).
