/*
V2.8.1 migration note

Do not use:

ALTER TABLE table ADD COLUMN created_at TEXT DEFAULT CURRENT_TIMESTAMP;

SQLite rejects this.

Use:

ALTER TABLE table ADD COLUMN created_at TEXT DEFAULT '';

Then populate:

UPDATE table
SET created_at = CURRENT_TIMESTAMP
WHERE created_at = '';

New rows should set timestamps from PHP.
*/
