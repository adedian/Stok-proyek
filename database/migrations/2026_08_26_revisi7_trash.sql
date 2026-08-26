-- ============================================================
-- REVISI 7 (2026-08-26): Tempat Sampah (Trash) - kolom deleted_by
--
-- Semua modul yang sudah soft-delete (core/Model.php: $softDelete = true,
-- kolom deleted_at) ditambah deleted_by supaya menu Tempat Sampah bisa
-- menampilkan "Dihapus oleh". ADDITIVE ONLY -- tidak ada kolom/tabel yang
-- diubah/dihapus. Restore cukup set deleted_at & deleted_by kembali NULL
-- (core/Model.php::restoreById), memakai ID record asli -- tidak membuat
-- baris baru, tidak mengganggu histori/relationship yang sudah ada.
-- ============================================================

ALTER TABLE `projects`             ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_projects_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `inventory`            ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_inventory_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `stock_out`            ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_stock_out_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `sales_invoices`       ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_sales_invoices_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `goods_receipts`       ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_goods_receipts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `offline_purchases`    ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_offline_purchases_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `stock_opname`         ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_stock_opname_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `purchase_orders`      ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_purchase_orders_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `dp_percentages`       ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_dp_percentages_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `collection_receipts`  ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_collection_receipts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `company_bank_accounts` ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_company_bank_accounts_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `delivery_notes`       ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_delivery_notes_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `items`                ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_items_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `warehouses`           ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_warehouses_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `clients`              ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_clients_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `suppliers`            ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_suppliers_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `payments`             ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_payments_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `signatures`           ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_signatures_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `payment_methods`      ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_payment_methods_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `units`                ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_units_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `item_categories`      ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_item_categories_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
ALTER TABLE `users`                ADD COLUMN `deleted_by` INT UNSIGNED NULL AFTER `deleted_at`, ADD CONSTRAINT `fk_users_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users`(`id`);
