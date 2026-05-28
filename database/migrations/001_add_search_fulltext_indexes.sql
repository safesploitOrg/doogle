-- Adds MySQL full-text indexes used by the phase 9 search ranking queries.
-- Run once against existing databases created before this migration.

ALTER TABLE `sites`
  ADD FULLTEXT KEY `ft_sites_search` (`title`, `description`, `keywords`, `url`);

ALTER TABLE `images`
  ADD FULLTEXT KEY `ft_images_search` (`title`, `alt`, `imageUrl`);
