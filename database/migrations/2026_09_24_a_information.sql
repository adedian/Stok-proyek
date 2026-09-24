-- =========================================================================
-- 2026_09_24_a_information.sql
--
-- Modul baru "Pusat Informasi" -- tempat SA memasukkan
-- pengumuman/keterangan yang perlu diketahui seluruh pengguna aplikasi
-- (mis. maintenance sistem, perubahan prosedur, pengumuman internal).
--
-- Kategori dibuat ENUM tetap (bukan master data terpisah) -- sesuai instruksi
-- "jangan membuat sistem terlalu kompleks jika belum diperlukan", cukup 6
-- pilihan tetap. status 'tidak_aktif' = tersimpan tapi tidak tampil ke user
-- biasa (hanya SA yang bisa lihat & kelola).
-- =========================================================================

CREATE TABLE IF NOT EXISTS `information` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`         VARCHAR(200) NOT NULL,
  `category`      ENUM('umum','pengumuman','sistem','prosedur','maintenance','lainnya') NOT NULL DEFAULT 'umum',
  `content`       TEXT NOT NULL,
  `status`        ENUM('aktif','tidak_aktif') NOT NULL DEFAULT 'aktif',
  `publish_date`  DATE NOT NULL,
  `created_by`    INT UNSIGNED NULL,
  `deleted_at`    DATETIME NULL,
  `deleted_by`    INT UNSIGNED NULL,
  `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_information_status` (`status`),
  KEY `idx_information_category` (`category`),
  KEY `idx_information_publish_date` (`publish_date`),
  KEY `idx_information_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_information_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_information_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
