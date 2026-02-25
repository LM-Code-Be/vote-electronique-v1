-- Add enterprise fields to existing candidates table (keeps backward compatibility)

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidates' AND COLUMN_NAME='election_id');
SET @sql := IF(@col=0, 'ALTER TABLE candidates ADD COLUMN election_id INT NULL', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidates' AND COLUMN_NAME='category');
SET @sql := IF(@col=0, 'ALTER TABLE candidates ADD COLUMN category VARCHAR(80) NULL', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidates' AND COLUMN_NAME='display_order');
SET @sql := IF(@col=0, 'ALTER TABLE candidates ADD COLUMN display_order INT NOT NULL DEFAULT 0', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidates' AND COLUMN_NAME='is_active');
SET @sql := IF(@col=0, 'ALTER TABLE candidates ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidates' AND COLUMN_NAME='is_validated');
SET @sql := IF(@col=0, 'ALTER TABLE candidates ADD COLUMN is_validated TINYINT(1) NOT NULL DEFAULT 1', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill election_id to 1 if elections table exists and has id=1
SET @hasElections := (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='elections');
SET @sql := IF(@hasElections>0, 'UPDATE candidates SET election_id=COALESCE(election_id,1)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Indexes
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='candidates' AND INDEX_NAME='idx_candidates_election');
SET @sql := IF(@idx=0, 'ALTER TABLE candidates ADD KEY idx_candidates_election (election_id)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

