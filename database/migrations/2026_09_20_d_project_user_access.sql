-- =========================================================================
-- 2026_09_20_project_user_access.sql
--
-- Mapping User <-> Project (BARU -- belum pernah ada sebelumnya, sudah
-- diaudit: projects.pic_name hanya teks bebas, tidak reliable untuk akses).
--
-- Dipakai gerbang Kas berbasis Project ("pilih Project lalu password akun
-- sendiri") KHUSUS role purchase / pic_project / admin_project -- lihat
-- app/helpers/kas_auth_helper.php (kasProjectGateRoles). Diatur oleh Super
-- Admin lewat halaman Project > Akses (permission 'project.manage_access').
--
-- CATATAN OPERASIONAL: setelah migration ini, user existing dengan role di
-- atas TIDAK OTOMATIS punya baris di sini (tidak bisa diturunkan aman dari
-- projects.pic_name yang cuma teks bebas) -- Super Admin WAJIB meng-assign
-- manual lewat UI baru supaya mereka tidak ter-lock-out dari Kas.
-- =========================================================================

CREATE TABLE IF NOT EXISTS `project_user_access` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  UNIQUE KEY `uq_project_user` (`project_id`, `user_id`),
  KEY `idx_pua_user` (`user_id`),
  CONSTRAINT `fk_pua_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pua_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pua_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
