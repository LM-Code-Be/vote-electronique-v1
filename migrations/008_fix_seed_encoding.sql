-- Normalize legacy mojibake value from old seed versions.
-- Safe/idempotent: only updates values that still end with "lection" but are not already correct.

UPDATE settings
SET title = 'Élection'
WHERE title <> 'Élection'
  AND LOWER(title) LIKE '%lection';