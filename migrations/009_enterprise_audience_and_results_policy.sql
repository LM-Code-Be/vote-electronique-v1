-- Enterprise audience/voter model hardening (idempotent)
-- Adds:
-- - users.user_type: INTERNAL|EXTERNAL
-- - elections.audience_mode: INTERNAL|HYBRID|EXTERNAL
-- - elections.results_visibility: AFTER_CLOSE (voter-facing policy)

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='user_type'
);
SET @sql := IF(
  @col=0,
  "ALTER TABLE users ADD COLUMN user_type ENUM('INTERNAL','EXTERNAL') NOT NULL DEFAULT 'INTERNAL' AFTER employee_id",
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND INDEX_NAME='idx_users_user_type'
);
SET @sql := IF(@idx=0, 'ALTER TABLE users ADD KEY idx_users_user_type (user_type)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='elections' AND COLUMN_NAME='audience_mode'
);
SET @sql := IF(
  @col=0,
  "ALTER TABLE elections ADD COLUMN audience_mode ENUM('INTERNAL','HYBRID','EXTERNAL') NOT NULL DEFAULT 'INTERNAL' AFTER timezone",
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='elections' AND COLUMN_NAME='results_visibility'
);
SET @sql := IF(
  @col=0,
  "ALTER TABLE elections ADD COLUMN results_visibility ENUM('AFTER_CLOSE') NOT NULL DEFAULT 'AFTER_CLOSE' AFTER audience_mode",
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='elections' AND INDEX_NAME='idx_elections_audience_mode'
);
SET @sql := IF(@idx=0, 'ALTER TABLE elections ADD KEY idx_elections_audience_mode (audience_mode)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
