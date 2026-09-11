-- =========================================================================
-- 2026_09_11_push_subscriptions.sql
--
-- Fitur Push Notification (Web Push) -- notifikasi muncul di HP walau
-- aplikasi sedang tertutup (Android penuh, iOS wajib "Tambah ke Layar
-- Utama" dulu). 1 baris = 1 langganan per PERANGKAT/BROWSER (bukan per
-- akun) -- satu user login di 2 HP = 2 baris, wajar & didukung.
--
-- endpoint TEXT (bukan VARCHAR) karena URL push service browser bisa
-- panjang (>500 char, terutama Firefox). endpoint_hash = SHA-256 dari
-- endpoint dipakai untuk UNIQUE KEY (MySQL tidak bisa index TEXT penuh).
-- =========================================================================

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT UNSIGNED NOT NULL,
  `endpoint`       TEXT NOT NULL,
  `endpoint_hash`  CHAR(64) NOT NULL COMMENT 'SHA-256(endpoint), untuk UNIQUE KEY',
  `p256dh`         VARCHAR(255) NOT NULL,
  `auth`           VARCHAR(255) NOT NULL,
  `user_agent`     VARCHAR(255) NULL DEFAULT NULL,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_push_endpoint` (`endpoint_hash`),
  KEY `idx_push_user` (`user_id`),
  CONSTRAINT `fk_push_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
