-- Adds the Videos search vertical.
-- Run once against existing databases created before the video vertical.

CREATE TABLE IF NOT EXISTS `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `siteUrl` varchar(512) NOT NULL,
  `videoUrl` varchar(512) NOT NULL,
  `thumbnailUrl` varchar(512) NOT NULL DEFAULT '',
  `title` varchar(512) NOT NULL DEFAULT '',
  `description` varchar(512) NOT NULL DEFAULT '',
  `source` varchar(100) NOT NULL DEFAULT '',
  `clicks` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_videos_video_url` (`videoUrl`),
  FULLTEXT KEY `ft_videos_search` (`title`, `description`, `videoUrl`, `siteUrl`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @videos_indexed_column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crawl_jobs'
    AND COLUMN_NAME = 'videos_indexed'
);

SET @add_videos_indexed_sql = IF(
  @videos_indexed_column_exists = 0,
  'ALTER TABLE `crawl_jobs` ADD COLUMN `videos_indexed` int(11) NOT NULL DEFAULT ''0'' AFTER `images_indexed`',
  'SELECT 1'
);

PREPARE add_videos_indexed_statement FROM @add_videos_indexed_sql;
EXECUTE add_videos_indexed_statement;
DEALLOCATE PREPARE add_videos_indexed_statement;
