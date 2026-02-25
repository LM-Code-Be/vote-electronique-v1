-- In-app push notifications (user inbox) + richer notification metadata.
-- Idempotent migration.

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='target_url'
);
SET @sql := IF(
  @col=0,
  'ALTER TABLE notifications ADD COLUMN target_url VARCHAR(255) NULL AFTER body',
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='audience_scope'
);
SET @sql := IF(
  @col=0,
  "ALTER TABLE notifications ADD COLUMN audience_scope ENUM('ALL','VOTER','ADMIN','SCRUTATEUR','SUPERADMIN') NOT NULL DEFAULT 'ALL' AFTER target_url",
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='is_push'
);
SET @sql := IF(
  @col=0,
  'ALTER TABLE notifications ADD COLUMN is_push TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active',
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='sent_count'
);
SET @sql := IF(
  @col=0,
  'ALTER TABLE notifications ADD COLUMN sent_count INT NOT NULL DEFAULT 0 AFTER is_push',
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='notifications' AND COLUMN_NAME='sent_at'
);
SET @sql := IF(
  @col=0,
  'ALTER TABLE notifications ADD COLUMN sent_at DATETIME NULL AFTER sent_count',
  'DO 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS user_notifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  source_notification_id BIGINT NULL,
  election_id INT NULL,
  event_type VARCHAR(64) NULL,
  title VARCHAR(200) NOT NULL,
  body TEXT NULL,
  level ENUM('INFO','SUCCESS','WARNING','ERROR') NOT NULL DEFAULT 'INFO',
  target_url VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  KEY idx_user_notifications_user_time (user_id, delivered_at),
  KEY idx_user_notifications_user_read (user_id, is_read),
  KEY idx_user_notifications_event (event_type, election_id),
  CONSTRAINT fk_user_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_notifications_source FOREIGN KEY (source_notification_id) REFERENCES notifications(id) ON DELETE SET NULL,
  CONSTRAINT fk_user_notifications_election FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

