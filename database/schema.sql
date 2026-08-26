-- ============================================================
-- DATABASE: db_stok_proyek
-- Sistem Dashboard Kontrol Stok Proyek - Tim Finance
-- Phase 2 - Database Master
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

CREATE DATABASE IF NOT EXISTS `db_stok_proyek`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `db_stok_proyek`;

-- ============================================================
-- 1. ROLES
-- ============================================================
CREATE TABLE `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_name` VARCHAR(50) NOT NULL,      -- Super Admin, Finance, Gudang, Project Manager
  `role_slug` VARCHAR(50) NOT NULL UNIQUE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL
) ENGINE=InnoDB;

-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT UNSIGNED NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,       -- bcrypt hash
  `status` ENUM('active','inactive') DEFAULT 'active',
  `last_login` DATETIME NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 3. SUPPLIERS
-- ============================================================
CREATE TABLE `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supplier_code` VARCHAR(30) NOT NULL UNIQUE,
  `supplier_name` VARCHAR(150) NOT NULL,
  `address` TEXT NULL,
  `phone` VARCHAR(30) NULL,
  `email` VARCHAR(150) NULL,
  `contact_person` VARCHAR(100) NULL,
  `status` ENUM('active','inactive') DEFAULT 'active',
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_suppliers_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 4. PROJECTS
-- ============================================================
CREATE TABLE `projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_code` VARCHAR(30) NOT NULL UNIQUE,
  `project_name` VARCHAR(150) NOT NULL,
  `location` VARCHAR(200) NULL,
  `pm_id` INT UNSIGNED NULL,             -- Project Manager (users.id)
  `start_date` DATE NULL,
  `end_date` DATE NULL,
  `status` ENUM('planning','ongoing','closed') DEFAULT 'planning',
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_projects_pm` FOREIGN KEY (`pm_id`) REFERENCES `users`(`id`),
  CONSTRAINT `fk_projects_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 5. PURCHASE ORDERS
-- ============================================================
CREATE TABLE `purchase_orders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `po_number` VARCHAR(50) NOT NULL UNIQUE,   -- auto generate: PO/2026/08/0001
  `supplier_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `po_date` DATE NOT NULL,
  `status` ENUM('draft','waiting_approval','approved','partial_received','completed','cancelled') DEFAULT 'draft',
  `total_amount` DECIMAL(18,2) DEFAULT 0,
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_po_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
  CONSTRAINT `fk_po_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
  CONSTRAINT `fk_po_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 6. PURCHASE ORDER ITEMS
-- ============================================================
CREATE TABLE `purchase_order_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT UNSIGNED NOT NULL,
  `item_name` VARCHAR(200) NOT NULL,
  `unit` VARCHAR(30) NOT NULL,            -- pcs, kg, m3, dll
  `qty_order` DECIMAL(15,2) NOT NULL,
  `price` DECIMAL(18,2) NOT NULL,
  `subtotal` DECIMAL(18,2) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_poi_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 7. PAYMENTS
-- ============================================================
CREATE TABLE `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT UNSIGNED NOT NULL,
  `payment_number` VARCHAR(50) NOT NULL UNIQUE,
  `termin` INT NOT NULL DEFAULT 1,        -- termin ke berapa
  `amount` DECIMAL(18,2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `proof_file` VARCHAR(255) NULL,         -- bukti transfer
  `status` ENUM('pending','partial','paid') DEFAULT 'pending',
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_payments_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`),
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 8. GOODS RECEIPTS (Penerimaan Barang)
-- ============================================================
CREATE TABLE `goods_receipts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT UNSIGNED NOT NULL,
  `receipt_number` VARCHAR(50) NOT NULL UNIQUE,
  `receipt_date` DATE NOT NULL,
  `received_by` INT UNSIGNED NOT NULL,     -- users.id (Gudang)
  `photo_goods` VARCHAR(255) NULL,
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_gr_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`),
  CONSTRAINT `fk_gr_user_recv` FOREIGN KEY (`received_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 9. GOODS RECEIPT ITEMS
-- ============================================================
CREATE TABLE `goods_receipt_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `goods_receipt_id` INT UNSIGNED NOT NULL,
  `purchase_order_item_id` INT UNSIGNED NOT NULL,
  `qty_received` DECIMAL(15,2) NOT NULL,
  `comparison_status` ENUM('sesuai','kurang','lebih') NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_gri_gr` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gri_poi` FOREIGN KEY (`purchase_order_item_id`) REFERENCES `purchase_order_items`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 10. DELIVERY DOCUMENTS (Surat Jalan)
-- ============================================================
CREATE TABLE `delivery_documents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `goods_receipt_id` INT UNSIGNED NOT NULL,
  `document_number` VARCHAR(50) NULL,
  `file_path` VARCHAR(255) NOT NULL,       -- upload foto surat jalan
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_dd_gr` FOREIGN KEY (`goods_receipt_id`) REFERENCES `goods_receipts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 11. INVENTORY (Stok Realtime)
-- ============================================================
CREATE TABLE `inventory` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `item_name` VARCHAR(200) NOT NULL,
  `unit` VARCHAR(30) NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `qty_available` DECIMAL(15,2) NOT NULL DEFAULT 0,
  `min_stock` DECIMAL(15,2) DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_inventory_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 12. STOCK TRANSACTIONS (Kartu Stok / Mutasi)
-- ============================================================
CREATE TABLE `stock_transactions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `inventory_id` INT UNSIGNED NOT NULL,
  `transaction_type` ENUM('in','out','adjustment') NOT NULL,
  `reference_type` VARCHAR(50) NULL,       -- goods_receipt / stock_out / stock_opname
  `reference_id` INT UNSIGNED NULL,
  `qty` DECIMAL(15,2) NOT NULL,
  `qty_before` DECIMAL(15,2) NOT NULL,
  `qty_after` DECIMAL(15,2) NOT NULL,
  `transaction_date` DATETIME NOT NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_st_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`),
  CONSTRAINT `fk_st_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 13. STOCK OUT (Pengeluaran Barang)
-- ============================================================
CREATE TABLE `stock_out` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `inventory_id` INT UNSIGNED NOT NULL,
  `project_id` INT UNSIGNED NOT NULL,
  `pic_name` VARCHAR(150) NOT NULL,
  `destination` VARCHAR(200) NOT NULL,
  `qty` DECIMAL(15,2) NOT NULL,
  `out_date` DATE NOT NULL,
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_so_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`),
  CONSTRAINT `fk_so_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
  CONSTRAINT `fk_so_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 14. STOCK OPNAME
-- ============================================================
CREATE TABLE `stock_opname` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `opname_number` VARCHAR(50) NOT NULL UNIQUE,
  `project_id` INT UNSIGNED NOT NULL,
  `opname_date` DATE NOT NULL,
  `status` ENUM('draft','completed') DEFAULT 'draft',
  `notes` TEXT NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_opname_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 15. STOCK OPNAME ITEMS
-- ============================================================
CREATE TABLE `stock_opname_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `stock_opname_id` INT UNSIGNED NOT NULL,
  `inventory_id` INT UNSIGNED NOT NULL,
  `qty_system` DECIMAL(15,2) NOT NULL,     -- stok menurut sistem
  `qty_actual` DECIMAL(15,2) NOT NULL,     -- stok fisik hasil hitung
  `difference` DECIMAL(15,2) NOT NULL,     -- qty_actual - qty_system
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_soi_opname` FOREIGN KEY (`stock_opname_id`) REFERENCES `stock_opname`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_soi_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 16. INVOICES
-- ============================================================
CREATE TABLE `invoices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT UNSIGNED NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT UNSIGNED NOT NULL,
  `invoice_amount` DECIMAL(18,2) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `file_path` VARCHAR(255) NULL,
  `status` ENUM('pending','valid') DEFAULT 'pending',
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_invoice_po` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`),
  CONSTRAINT `fk_invoice_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 17. INVOICE VALIDATIONS
-- ============================================================
CREATE TABLE `invoice_validations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` INT UNSIGNED NOT NULL,
  `po_match` TINYINT(1) NOT NULL DEFAULT 0,
  `payment_match` TINYINT(1) NOT NULL DEFAULT 0,
  `goods_receipt_match` TINYINT(1) NOT NULL DEFAULT 0,
  `final_status` ENUM('valid','pending') NOT NULL DEFAULT 'pending',
  `mismatch_reason` TEXT NULL,
  `validated_by` INT UNSIGNED NULL,
  `validated_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_iv_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_iv_user` FOREIGN KEY (`validated_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 18. OFFLINE PURCHASES (Pembelian Offline)
-- ============================================================
CREATE TABLE `offline_purchases` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT UNSIGNED NOT NULL,
  `supplier_name` VARCHAR(150) NOT NULL,   -- manual, bukan FK suppliers
  `item_name` VARCHAR(200) NOT NULL,
  `qty` DECIMAL(15,2) NOT NULL,
  `price` DECIMAL(18,2) NOT NULL,
  `total` DECIMAL(18,2) NOT NULL,
  `purchase_date` DATE NOT NULL,
  `proof_file` VARCHAR(255) NULL,
  `photo_file` VARCHAR(255) NULL,
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_op_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`),
  CONSTRAINT `fk_op_user` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

-- ============================================================
-- 19. ACTIVITY LOGS (Audit Trail)
-- ============================================================
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL,
  `module` VARCHAR(50) NOT NULL,           -- purchase_order, payment, dll
  `action` VARCHAR(50) NOT NULL,           -- create, update, delete, login, logout
  `description` TEXT NULL,
  `ip_address` VARCHAR(45) NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA AWAL (roles + super admin)
-- ============================================================
INSERT INTO `roles` (`role_name`, `role_slug`) VALUES
('Super Admin', 'super_admin'),
('Finance', 'finance'),
('Gudang', 'gudang'),
('Project Manager', 'project_manager');

-- password default: "admin123" (GANTI setelah login pertama)
INSERT INTO `users` (`role_id`, `full_name`, `username`, `email`, `password`) VALUES
(1, 'Super Admin', 'admin', 'admin@stokproyek.local', '$2y$10$oUkAKMBHJN0H2etzuMb5xukQu0YkeqNdJTfi93aaylNqAuR/yRkSG');