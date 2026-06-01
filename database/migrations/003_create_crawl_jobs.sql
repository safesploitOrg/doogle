-- Adds crawl job history for ARCHITECTURE2 Phase G.

CREATE TABLE IF NOT EXISTS `crawl_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start_url` varchar(512) NOT NULL,
  `requested_by_user_id` int(11) DEFAULT NULL,
  `status` enum('pending', 'running', 'completed', 'failed', 'rejected') NOT NULL DEFAULT 'pending',
  `pages_discovered` int(11) NOT NULL DEFAULT '0',
  `pages_indexed` int(11) NOT NULL DEFAULT '0',
  `images_indexed` int(11) NOT NULL DEFAULT '0',
  `urls_rejected` int(11) NOT NULL DEFAULT '0',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_crawl_jobs_status` (`status`),
  KEY `idx_crawl_jobs_requested_by` (`requested_by_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
