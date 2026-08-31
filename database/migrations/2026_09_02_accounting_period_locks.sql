-- ============================================================
-- TUTUP BULAN / Period Lock (2026-09-02)
-- ============================================================
-- Super Admin dapat "menutup" periode per-modul sampai tanggal tertentu.
-- Semua transaksi modul tsb dengan tanggal <= period_end menjadi LOCKED:
-- backend menolak CREATE / EDIT / DELETE (bukan sekadar disable tombol).
-- View / Print / Export / Laporan tetap jalan.
--
-- Satu baris = satu proses penutupan (modul + tanggal tutup). Bisa dibuka
-- kembali (status -> 'open') dan ditutup lagi (status -> 'closed', baris sama).
-- "Terkunci atau tidak" ditentukan oleh MAX(period_end) baris status='closed'
-- untuk modul tsb (lihat period_helper.php: isPeriodClosed()).
--
-- module (slug): purchase_order, payment, goods_receipt, validation,
--                stock_out, cash, offline_purchase, sales_invoice, stock_opname

CREATE TABLE IF NOT EXISTS `accounting_period_locks` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module`       VARCHAR(40) NOT NULL,
  `period_start` DATE NOT NULL,                 -- awal periode (informasi tampilan)
  `period_end`   DATE NOT NULL,                 -- KUNCI: transaksi tgl <= ini = locked
  `status`       ENUM('closed','open') NOT NULL DEFAULT 'closed',
  `closed_at`    DATETIME NULL,
  `closed_by`    INT UNSIGNED NULL,
  `reopened_at`  DATETIME NULL,
  `reopened_by`  INT UNSIGNED NULL,
  `note`         VARCHAR(255) NULL,
  `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_module_period_end` (`module`, `period_end`),
  KEY `idx_module_status` (`module`, `status`),
  CONSTRAINT `fk_apl_closed_by`   FOREIGN KEY (`closed_by`)   REFERENCES `users`(`id`),
  CONSTRAINT `fk_apl_reopened_by` FOREIGN KEY (`reopened_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
