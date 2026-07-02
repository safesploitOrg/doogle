-- Adds optional admin TOTP and admin login event audit storage.
-- Run once against existing databases created before TOTP support.

SET @totp_secret_column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'totp_secret'
);

SET @add_totp_secret_sql = IF(
  @totp_secret_column_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `totp_secret` varchar(64) DEFAULT NULL AFTER `role`',
  'SELECT 1'
);

PREPARE add_totp_secret_statement FROM @add_totp_secret_sql;
EXECUTE add_totp_secret_statement;
DEALLOCATE PREPARE add_totp_secret_statement;

SET @totp_enabled_column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'totp_enabled'
);

SET @add_totp_enabled_sql = IF(
  @totp_enabled_column_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `totp_enabled` tinyint(1) NOT NULL DEFAULT ''0'' AFTER `totp_secret`',
  'SELECT 1'
);

PREPARE add_totp_enabled_statement FROM @add_totp_enabled_sql;
EXECUTE add_totp_enabled_statement;
DEALLOCATE PREPARE add_totp_enabled_statement;

SET @totp_confirmed_at_column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'totp_confirmed_at'
);

SET @add_totp_confirmed_at_sql = IF(
  @totp_confirmed_at_column_exists = 0,
  'ALTER TABLE `users` ADD COLUMN `totp_confirmed_at` timestamp NULL DEFAULT NULL AFTER `totp_enabled`',
  'SELECT 1'
);

PREPARE add_totp_confirmed_at_statement FROM @add_totp_confirmed_at_sql;
EXECUTE add_totp_confirmed_at_statement;
DEALLOCATE PREPARE add_totp_confirmed_at_statement;

CREATE TABLE IF NOT EXISTS `admin_login_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `failure_reason` varchar(100) NOT NULL DEFAULT '',
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_admin_login_events_created_at` (`created_at`),
  KEY `idx_admin_login_events_username` (`username`),
  KEY `idx_admin_login_events_successful` (`successful`),
  KEY `idx_admin_login_events_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
