-- Updates the legacy users table for ARCHITECTURE2 authenticated admin access.
-- Run once against existing databases created before the auth layer.

ALTER TABLE `users`
  MODIFY `email` varchar(255) NOT NULL,
  ADD COLUMN `role` varchar(50) NOT NULL DEFAULT 'admin',
  ADD COLUMN `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  ADD UNIQUE KEY `unique_username` (`username`),
  ADD UNIQUE KEY `unique_email` (`email`);
