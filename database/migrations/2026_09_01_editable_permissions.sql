-- ============================================================
-- Hak Akses bisa diedit lewat UI (2026-09-01)
-- ============================================================
-- Sebelumnya matrix RBAC 100% statis di config/permissions.php. Dua tabel di
-- bawah membuat Super Admin bisa mengubah izin lewat UI:
--
--   role_permissions  -- matrix per-role yang bisa dicentang di
--                        Pengaturan Sistem > Hak Akses. Di-seed dari
--                        config/permissions.php oleh
--                        2026_09_01_seed_permissions.php supaya perilaku
--                        hari pertama SAMA PERSIS dengan sebelumnya.
--
--   user_permissions  -- override per-user (allow/deny) di atas matrix
--                        role-nya. Dipakai panel "Hak Akses" di form User
--                        Management. Hanya menyimpan SELISIH dari default role.
--
-- Modul 'settings', 'user', 'trash' TETAP dikunci ke Super Admin lewat
-- config/permissions.php (PERMISSION_LOCKED_MODULES di permission_helper.php) --
-- tidak pernah dibaca dari dua tabel ini, tidak bisa dicentang / di-override.
--
-- Jalankan: import file ini, lalu:
--   php database/migrations/2026_09_01_seed_permissions.php

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_slug`  VARCHAR(50) NOT NULL,
  `module`     VARCHAR(50) NOT NULL,
  `action`     VARCHAR(30) NOT NULL,
  `allowed`    TINYINT(1)  NOT NULL DEFAULT 0,
  `updated_at` DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  UNIQUE KEY `uq_role_module_action` (`role_slug`, `module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `module`     VARCHAR(50) NOT NULL,
  `action`     VARCHAR(30) NOT NULL,
  `effect`     ENUM('allow','deny') NOT NULL,
  `updated_at` DATETIME    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  UNIQUE KEY `uq_user_module_action` (`user_id`, `module`, `action`),
  CONSTRAINT `fk_uperm_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
