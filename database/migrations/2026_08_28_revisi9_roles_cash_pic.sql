-- ============================================================
-- REVISI 9 (2026-08-28): Modul Kas + Sistem Role/Permission Baru
--
-- Bagian:
--   1) Role baru (Purchase, Accounting, PIC Project, Admin Project).
--      Role lama 'finance' & 'gudang' TIDAK dihapus dari tabel `roles`
--      (banyak baris histori created_by/activity_log tetap valid; hanya
--      tidak lagi ditawarkan sebagai pilihan -- lihat Role::assignableList()).
--   2) Migrasi akun demo lama: user berrole 'finance' -> 'accounting',
--      'gudang' -> 'pic_project'. Akun & seluruh histori TIDAK diubah,
--      hanya role_id-nya yang dipindah supaya tidak ada user "diam-diam"
--      nyangkut di role yang sudah tidak punya permission apa pun.
--   3) Master Kategori Kas (pola item_categories) + isi awal 4 kategori.
--   4) Mapping User -> PIC (user_pic_assignments) -- dipakai untuk
--      membatasi visibilitas Kas per role (Purchase/PIC Project/Admin
--      Project hanya lihat Kas milik PIC terkaitnya). Terpusat, dikelola
--      lewat Master Data > PIC Mapping, TIDAK di-hardcode di controller.
--   5) Tabel transaksi Kas (cash_transactions), soft-delete (ikut Trash).
--
-- ADDITIVE ONLY. Idempotent (aman dijalankan ulang). Tidak menghapus
-- data/tabel mana pun. Seed 5 akun user dilakukan di script PHP terpisah
-- (2026_08_28_revisi9_seed_users.php) supaya password ter-hash dengan
-- password_hash().
-- ============================================================

USE `db_stok_proyek`;

-- ------------------------------------------------------------
-- 1. Role baru
-- ------------------------------------------------------------
INSERT INTO `roles` (`role_name`, `role_slug`) VALUES
  ('Purchase',       'purchase'),
  ('Accounting',     'accounting'),
  ('PIC Project',    'pic_project'),
  ('Admin Project',  'admin_project')
ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`);

-- ------------------------------------------------------------
-- 2. Migrasi role akun demo lama (histori tetap utuh)
-- ------------------------------------------------------------
UPDATE `users`
   SET `role_id` = (SELECT `id` FROM `roles` WHERE `role_slug` = 'accounting')
 WHERE `role_id` = (SELECT `id` FROM `roles` WHERE `role_slug` = 'finance');

UPDATE `users`
   SET `role_id` = (SELECT `id` FROM `roles` WHERE `role_slug` = 'pic_project')
 WHERE `role_id` = (SELECT `id` FROM `roles` WHERE `role_slug` = 'gudang');

-- ------------------------------------------------------------
-- 3. Master: Kategori Kas
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `category_name` VARCHAR(100) NOT NULL UNIQUE,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT UNSIGNED NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_cash_categories_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `cash_categories` (`category_name`) VALUES
  ('Material Proyek'),
  ('Inventory Kantor'),
  ('Inventory Teknik'),
  ('Biaya Operasional')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- ------------------------------------------------------------
-- 4. Mapping User -> PIC (untuk pembatasan akses Kas)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_pic_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `pic_name` VARCHAR(150) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  UNIQUE KEY `uq_user_pic` (`user_id`, `pic_name`),
  CONSTRAINT `fk_user_pic_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Transaksi Kas
--    `amount` DISENGAJA disiapkan (NULL) untuk kebutuhan laporan saldo
--    di masa depan -- TIDAK dipakai/ditampilkan di UI Revisi 9.
--    Keunikan `no_bukti` diperiksa di aplikasi (mengabaikan baris
--    soft-deleted), jadi di DB cukup index biasa.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `trx_date` DATE NOT NULL,
  `pic` VARCHAR(150) NOT NULL,
  `no_bukti` VARCHAR(100) NOT NULL,
  `category_id` INT UNSIGNED NOT NULL,
  `uraian` TEXT NOT NULL,
  `qty` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `unit_id` INT UNSIGNED NULL,
  `mutasi` ENUM('masuk','keluar') NOT NULL,
  `amount` DECIMAL(18,2) NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT UNSIGNED NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  KEY `idx_cash_pic` (`pic`),
  KEY `idx_cash_date` (`trx_date`),
  KEY `idx_cash_mutasi` (`mutasi`),
  KEY `idx_cash_nobukti` (`no_bukti`),
  CONSTRAINT `fk_cash_category` FOREIGN KEY (`category_id`) REFERENCES `cash_categories`(`id`),
  CONSTRAINT `fk_cash_unit` FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`),
  CONSTRAINT `fk_cash_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
