-- Link enterprise candidates to existing users (optional link, keeps backward compatibility)

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='candidates'
    AND COLUMN_NAME='user_id'
);
SET @sql := IF(@col=0, 'ALTER TABLE candidates ADD COLUMN user_id INT NULL AFTER election_id', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE()
    AND TABLE_NAME='candidates'
    AND INDEX_NAME='idx_candidates_user'
);
SET @sql := IF(@idx=0, 'ALTER TABLE candidates ADD KEY idx_candidates_user (user_id)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk := (
  SELECT COUNT(*)
  FROM information_schema.REFERENTIAL_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA=DATABASE()
    AND TABLE_NAME='candidates'
    AND CONSTRAINT_NAME='fk_candidates_user'
);
SET @sql := IF(@fk=0, 'ALTER TABLE candidates ADD CONSTRAINT fk_candidates_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
