-- ============================================================
-- KAS: SECOND-LEVEL AUTHENTICATION & ACCESS CONTROL (2026-08-31)
--
-- Menambah lapisan keamanan modul Kas di atas login aplikasi:
--   1) Kredensial PIC Kas (username + password/PIN ter-hash + status aktif)
--      DITEMPEL ke tabel mapping existing `user_pic_assignments` -- semua
--      logika scoping Kas yang sudah jalan tetap dipakai apa adanya.
--   2) Kolom `division` (snapshot saat transaksi dibuat) di `cash_transactions`
--      -- dipakai membatasi Project Manager ke divisi PROJECT saja & kartu
--      saldo per-divisi. Di-backfill dari role pembuat baris lama.
--   3) `cash_opening_balances` -- saldo awal per divisi (opsional; default 0).
--   4) Setting `kas_session_timeout_minutes` (auto-lock session Kas).
--
-- ADDITIVE ONLY. Guarded (aman dijalankan ulang) lewat information_schema.
-- Tidak menghapus data/tabel mana pun. Tidak menyentuh login utama.
-- ============================================================

USE `db_stok_proyek`;

-- ------------------------------------------------------------
-- 1. Kredensial PIC Kas pada user_pic_assignments
--    NULL = mapping lama tanpa kredensial (belum bisa dipakai login Kas).
-- ------------------------------------------------------------
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_pic_assignments' AND COLUMN_NAME = 'pic_username');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `user_pic_assignments`
     ADD COLUMN `pic_username` VARCHAR(100) NULL AFTER `pic_name`,
     ADD COLUMN `pic_password` VARCHAR(255) NULL AFTER `pic_username`,
     ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `pic_password`,
     ADD COLUMN `updated_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`,
     ADD UNIQUE KEY `uq_pic_username` (`pic_username`)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ------------------------------------------------------------
-- 2. Divisi transaksi Kas (snapshot; dipakai filter PM + kartu saldo)
-- ------------------------------------------------------------
SET @has_col := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cash_transactions' AND COLUMN_NAME = 'division');
SET @sql := IF(@has_col = 0,
  'ALTER TABLE `cash_transactions`
     ADD COLUMN `division` VARCHAR(20) NOT NULL DEFAULT ''umum'' AFTER `pic`,
     ADD KEY `idx_cash_division` (`division`)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Backfill divisi baris lama dari role pembuatnya (sekali; baris ber-divisi
-- 'umum' yang created_by-nya diketuahi role akan diselaraskan).
UPDATE `cash_transactions` c
  JOIN `users` u ON u.id = c.created_by
  JOIN `roles` r ON r.id = u.role_id
   SET c.division = CASE
     WHEN r.role_slug IN ('pic_project','admin_project','project_manager','gudang') THEN 'project'
     WHEN r.role_slug = 'accounting' THEN 'accounting'
     WHEN r.role_slug = 'purchase'   THEN 'purchase'
     ELSE 'umum'
   END
 WHERE c.division = 'umum' AND c.created_by IS NOT NULL;

-- ------------------------------------------------------------
-- 3. Saldo awal Kas per divisi (opsional). Kosong = semua divisi mulai 0.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cash_opening_balances` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `division` VARCHAR(20) NOT NULL UNIQUE,
  `opening_balance` DECIMAL(18,2) NOT NULL DEFAULT 0,
  `as_of_date` DATE NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_cash_opening_user` FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Timeout auto-lock session Kas (menit). Terpisah dari session login utama.
-- ------------------------------------------------------------
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
VALUES ('kas_session_timeout_minutes', '20', 'session')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
