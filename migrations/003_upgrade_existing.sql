-- Upgrade existing installations (idempotent via information_schema checks)

-- Ensure admin_users exists
CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(32) NOT NULL DEFAULT 'admin',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  UNIQUE KEY uniq_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- logs: add columns if missing
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs' AND COLUMN_NAME='admin_user_id');
SET @sql := IF(@col=0, 'ALTER TABLE logs ADD COLUMN admin_user_id INT NULL', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs' AND COLUMN_NAME='ip');
SET @sql := IF(@col=0, 'ALTER TABLE logs ADD COLUMN ip VARCHAR(45) NULL', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs' AND COLUMN_NAME='user_agent');
SET @sql := IF(@col=0, 'ALTER TABLE logs ADD COLUMN user_agent VARCHAR(255) NULL', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- logs: indexes
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs' AND INDEX_NAME='idx_logs_created_at');
SET @sql := IF(@idx=0, 'ALTER TABLE logs ADD KEY idx_logs_created_at (created_at)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs' AND INDEX_NAME='idx_logs_action_type');
SET @sql := IF(@idx=0, 'ALTER TABLE logs ADD KEY idx_logs_action_type (action_type)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs' AND INDEX_NAME='idx_logs_email');
SET @sql := IF(@idx=0, 'ALTER TABLE logs ADD KEY idx_logs_email (email)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- logs: FK admin_user_id
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='logs' AND CONSTRAINT_NAME='fk_logs_admin_user');
SET @sql := IF(@fk=0, 'ALTER TABLE logs ADD CONSTRAINT fk_logs_admin_user FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE SET NULL', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- votes: unique voter_id
SET @uniq := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='votes' AND INDEX_NAME='uniq_votes_voter');
SET @sql := IF(@uniq=0, 'ALTER TABLE votes ADD UNIQUE KEY uniq_votes_voter (voter_id)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- votes: add indexes if missing
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='votes' AND INDEX_NAME='idx_votes_voted_at');
SET @sql := IF(@idx=0, 'ALTER TABLE votes ADD KEY idx_votes_voted_at (voted_at)', 'DO 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- settings: ensure row id=1 exists
INSERT INTO settings(id, title, description, election_date, vote_start, vote_end, max_votes, allow_vote_change)
VALUES (1, 'Élection', '', CURDATE(), NOW(), DATE_ADD(NOW(), INTERVAL 1 DAY), NULL, 0)
ON DUPLICATE KEY UPDATE id=id;
