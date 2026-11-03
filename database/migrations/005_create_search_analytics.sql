CREATE TABLE IF NOT EXISTS `search_queries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `term` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `result_count` int(11) NOT NULL DEFAULT '0',
  `page` int(11) NOT NULL DEFAULT '1',
  `ip_hash` varchar(64) NOT NULL DEFAULT '',
  `user_agent_hash` varchar(64) NOT NULL DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_queries_created_at` (`created_at`),
  KEY `idx_search_queries_term_type` (`term`, `type`),
  KEY `idx_search_queries_type` (`type`),
  KEY `idx_search_queries_result_count` (`result_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `ranking_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(100) NOT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
