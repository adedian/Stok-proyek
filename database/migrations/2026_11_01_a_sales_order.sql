-- ============================================================================
-- Order Penjualan (Sales Order) -- pesanan customer berdasarkan PO Customer.
--
-- Alur: Purchase input Order -> Accounting periksa stok & proses PEMENUHAN
-- (membuat dokumen Pengeluaran Barang yang SAH, memotong stok lewat jalur
-- Inventory::debitStock yang sudah ada) -> Invoice Keluar -> Surat Jalan.
--
-- Prinsip desain:
--   * Customer memakai master `clients` (tidak ada master baru).
--   * Barang memakai master `items`; stok dibaca LIVE dari `inventory` (cocok
--     lewat nama barang, sama seperti Stok Barang). Order TIDAK pernah memotong
--     atau mereservasi stok.
--   * "Jumlah Terpenuhi" TIDAK disimpan -- dihitung dari stock_out (baris yang
--     menunjuk ke sales_order_item_id), sehingga tidak bisa melenceng dari
--     transaksi stok yang sebenarnya.
--   * Pemenuhan = 1 baris sales_order_fulfillments + N baris stock_out. Kolom
--     process_token UNIQUE mencegah klik ganda / refresh membuat pemenuhan ganda.
--   * Invoice Keluar & Surat Jalan yang sudah ada diberi relasi opsional ke
--     order (kolom NULLABLE) -- tidak ada modul duplikat, data lama utuh.
--
-- ADDITIVE ONLY: tidak ada kolom/tabel lama yang diubah atau dihapus.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `sales_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(50) NOT NULL,
  `order_date` date NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `customer_address` text DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `po_number` varchar(100) NOT NULL COMMENT 'Nomor PO milik customer (BUKAN nomor order internal)',
  `po_date` date NOT NULL COMMENT 'Tanggal PO milik customer',
  `ppn_percent` decimal(5,2) NOT NULL DEFAULT 11.00,
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `ppn_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `status` enum('draft','aktif','dibatalkan') NOT NULL DEFAULT 'aktif'
      COMMENT 'Status tersimpan. Status tampilan (menunggu stok/diproses sebagian/selesai dst) DIHITUNG dari pemenuhan, invoice & surat jalan',
  `stock_checked_at` datetime DEFAULT NULL COMMENT 'Terakhir kali Accounting membuka halaman Proses (stok diperiksa)',
  `stock_checked_by` int(10) unsigned DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_so_number` (`order_number`),
  KEY `idx_so_client` (`client_id`),
  KEY `idx_so_po` (`client_id`, `po_number`),
  KEY `idx_so_date` (`order_date`),
  KEY `idx_so_status` (`status`),
  KEY `fk_so_created_by` (`created_by`),
  CONSTRAINT `fk_so_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_so_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_order_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(10) unsigned NOT NULL,
  `item_id` int(10) unsigned NOT NULL,
  `item_code` varchar(30) NOT NULL,
  `item_name` varchar(200) NOT NULL,
  `specification` text DEFAULT NULL,
  `unit` varchar(30) NOT NULL,
  `qty` decimal(15,2) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `net_price` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'Harga satuan setelah diskon (dibulatkan 2 desimal)',
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00 COMMENT 'qty x net_price',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_soi_order` (`sales_order_id`),
  KEY `idx_soi_item` (`item_id`),
  CONSTRAINT `fk_soi_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_soi_item` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_order_fulfillments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(10) unsigned NOT NULL,
  `fulfillment_date` date NOT NULL,
  `stock_out_number` varchar(50) DEFAULT NULL COMMENT 'Nomor dokumen Pengeluaran Barang yang dibuat pemenuhan ini',
  `process_token` char(32) NOT NULL COMMENT 'Token sekali-pakai per tampilan form -- UNIQUE mencegah proses ganda',
  `notes` text DEFAULT NULL,
  `status` enum('aktif','dibatalkan') NOT NULL DEFAULT 'aktif',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sof_token` (`process_token`),
  KEY `idx_sof_order` (`sales_order_id`),
  KEY `fk_sof_created_by` (`created_by`),
  CONSTRAINT `fk_sof_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`),
  CONSTRAINT `fk_sof_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sales_order_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sales_order_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `role_name` varchar(60) DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_soh_order` (`sales_order_id`),
  KEY `fk_soh_user` (`user_id`),
  CONSTRAINT `fk_soh_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_soh_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relasi dari transaksi yang sudah ada ke Order Penjualan (semua NULLABLE).
ALTER TABLE `stock_out`
  ADD COLUMN `sales_order_id` int(10) unsigned DEFAULT NULL AFTER `sales_invoice_id`,
  ADD COLUMN `sales_order_item_id` int(10) unsigned DEFAULT NULL AFTER `sales_order_id`,
  ADD COLUMN `sales_order_fulfillment_id` int(10) unsigned DEFAULT NULL AFTER `sales_order_item_id`,
  ADD KEY `idx_stock_out_so_item` (`sales_order_item_id`),
  ADD KEY `idx_stock_out_so` (`sales_order_id`),
  ADD KEY `idx_stock_out_so_fulfillment` (`sales_order_fulfillment_id`),
  ADD CONSTRAINT `fk_stock_out_so` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`),
  ADD CONSTRAINT `fk_stock_out_so_item` FOREIGN KEY (`sales_order_item_id`) REFERENCES `sales_order_items` (`id`),
  ADD CONSTRAINT `fk_stock_out_so_fulfillment` FOREIGN KEY (`sales_order_fulfillment_id`) REFERENCES `sales_order_fulfillments` (`id`);

ALTER TABLE `sales_invoices`
  ADD COLUMN `sales_order_id` int(10) unsigned DEFAULT NULL AFTER `project_id`,
  ADD KEY `idx_si_sales_order` (`sales_order_id`),
  ADD CONSTRAINT `fk_si_sales_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`);

ALTER TABLE `sales_invoice_items`
  ADD COLUMN `sales_order_item_id` int(10) unsigned DEFAULT NULL AFTER `item_id`,
  ADD KEY `idx_sii_so_item` (`sales_order_item_id`),
  ADD CONSTRAINT `fk_sii_so_item` FOREIGN KEY (`sales_order_item_id`) REFERENCES `sales_order_items` (`id`);

ALTER TABLE `delivery_notes`
  ADD COLUMN `sales_order_id` int(10) unsigned DEFAULT NULL AFTER `project_id`,
  ADD KEY `idx_dn_sales_order` (`sales_order_id`),
  ADD CONSTRAINT `fk_dn_sales_order` FOREIGN KEY (`sales_order_id`) REFERENCES `sales_orders` (`id`);

-- Kode penomoran Order Penjualan (001/SR.HME/X/2026). Bisa diubah di Pengaturan > Penomoran.
INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
VALUES ('prefix_op', 'SR.HME', 'numbering');
