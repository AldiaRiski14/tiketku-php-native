-- Migrasi untuk fitur Kelola User (Ban / Inactive)
-- Jalankan di phpMyAdmin jika database sudah ada

ALTER TABLE users
ADD COLUMN account_status ENUM('active','banned','inactive') DEFAULT 'active' AFTER role,
ADD COLUMN banned_until DATETIME DEFAULT NULL AFTER account_status,
ADD COLUMN ban_reason VARCHAR(255) DEFAULT NULL AFTER banned_until;
