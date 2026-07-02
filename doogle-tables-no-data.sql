-- phpMyAdmin SQL Dump - No Data

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- User Creation: `doogle`
--
CREATE USER IF NOT EXISTS 'doogle'@'%' IDENTIFIED BY 'PASSWORD_HERE';
GRANT SELECT, INSERT, UPDATE ON `doogle`.* TO 'doogle'@'%';

--
-- Database: `doogle`
--
CREATE DATABASE IF NOT EXISTS `doogle` DEFAULT CHARACTER SET utf8mb4;
USE `doogle`;

-- --------------------------------------------------------

--
-- Table structure for table `images`
--

CREATE TABLE IF NOT EXISTS `images` (
  `id` int(11) NOT NULL,
  `siteUrl` varchar(512) NOT NULL,
  `imageUrl` varchar(512) NOT NULL,
  `alt` varchar(512) NOT NULL,
  `title` varchar(512) NOT NULL,
  `clicks` int(11) NOT NULL DEFAULT '0',
  `broken` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `videos`
--

CREATE TABLE IF NOT EXISTS `videos` (
  `id` int(11) NOT NULL,
  `siteUrl` varchar(512) NOT NULL,
  `videoUrl` varchar(512) NOT NULL,
  `thumbnailUrl` varchar(512) NOT NULL DEFAULT '',
  `title` varchar(512) NOT NULL DEFAULT '',
  `description` varchar(512) NOT NULL DEFAULT '',
  `source` varchar(100) NOT NULL DEFAULT '',
  `clicks` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE IF NOT EXISTS `sites` (
  `id` int(11) NOT NULL,
  `url` varchar(512) NOT NULL,
  `title` varchar(512) NOT NULL,
  `description` varchar(512) NOT NULL,
  `keywords` varchar(512) NOT NULL,
  `clicks` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `totp_secret` varchar(64) DEFAULT NULL,
  `totp_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `totp_confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `admin_login_events`
--

CREATE TABLE IF NOT EXISTS `admin_login_events` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) NOT NULL DEFAULT '',
  `successful` tinyint(1) NOT NULL DEFAULT '0',
  `failure_reason` varchar(100) NOT NULL DEFAULT '',
  `ip_address` varchar(45) NOT NULL DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `crawl_jobs`
--

CREATE TABLE IF NOT EXISTS `crawl_jobs` (
  `id` int(11) NOT NULL,
  `start_url` varchar(512) NOT NULL,
  `requested_by_user_id` int(11) DEFAULT NULL,
  `status` enum('pending', 'running', 'completed', 'failed', 'rejected') NOT NULL DEFAULT 'pending',
  `pages_discovered` int(11) NOT NULL DEFAULT '0',
  `pages_indexed` int(11) NOT NULL DEFAULT '0',
  `images_indexed` int(11) NOT NULL DEFAULT '0',
  `videos_indexed` int(11) NOT NULL DEFAULT '0',
  `urls_rejected` int(11) NOT NULL DEFAULT '0',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `search_queries`
--

CREATE TABLE IF NOT EXISTS `search_queries` (
  `id` int(11) NOT NULL,
  `term` varchar(255) NOT NULL,
  `type` varchar(20) NOT NULL,
  `result_count` int(11) NOT NULL DEFAULT '0',
  `page` int(11) NOT NULL DEFAULT '1',
  `ip_hash` varchar(64) NOT NULL DEFAULT '',
  `user_agent_hash` varchar(64) NOT NULL DEFAULT '',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `ranking_settings`
--

CREATE TABLE IF NOT EXISTS `ranking_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(100) NOT NULL,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


--
-- Indexes for dumped tables
--

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD FULLTEXT KEY `ft_images_search` (`title`, `alt`, `imageUrl`);

--
-- Indexes for table `videos`
--
ALTER TABLE `videos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_videos_video_url` (`videoUrl`),
  ADD FULLTEXT KEY `ft_videos_search` (`title`, `description`, `videoUrl`, `siteUrl`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD FULLTEXT KEY `ft_sites_search` (`title`, `description`, `keywords`, `url`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `unique_email` (`email`);

--
-- Indexes for table `admin_login_events`
--
ALTER TABLE `admin_login_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_login_events_created_at` (`created_at`),
  ADD KEY `idx_admin_login_events_username` (`username`),
  ADD KEY `idx_admin_login_events_successful` (`successful`),
  ADD KEY `idx_admin_login_events_user_id` (`user_id`);

--
-- Indexes for table `crawl_jobs`
--
ALTER TABLE `crawl_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_crawl_jobs_status` (`status`),
  ADD KEY `idx_crawl_jobs_requested_by` (`requested_by_user_id`);

--
-- Indexes for table `search_queries`
--
ALTER TABLE `search_queries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_search_queries_created_at` (`created_at`),
  ADD KEY `idx_search_queries_term_type` (`term`, `type`),
  ADD KEY `idx_search_queries_type` (`type`),
  ADD KEY `idx_search_queries_result_count` (`result_count`);

--
-- Indexes for table `ranking_settings`
--
ALTER TABLE `ranking_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13003;

--
-- AUTO_INCREMENT for table `videos`
--
ALTER TABLE `videos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5297;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1382;

--
-- AUTO_INCREMENT for table `admin_login_events`
--
ALTER TABLE `admin_login_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crawl_jobs`
--
ALTER TABLE `crawl_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `search_queries`
--
ALTER TABLE `search_queries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
