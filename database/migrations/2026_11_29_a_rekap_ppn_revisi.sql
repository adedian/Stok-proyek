-- ============================================================
-- Revisi Rekap PPN (additive only):
--   1. clients.npwp           : Master NPWP Customer (diisi di Master Data > Client; dipakai PPN Keluaran).
--   2. ppn_keluaran.status    : 'draft' | 'lengkap'. Baris yang sudah ada = 'lengkap' (DEFAULT) -- tidak berubah perilaku.
--      ppn_keluaran.client_id : customer dari Master Client (dropdown). customer_name tetap disimpan sebagai salinan.
--      ppn_keluaran.sales_invoice_id : tanda baris hasil "Tarik dari Invoice Keluar" (mencegah ditarik dua kali).
--   3. ppn_masukan.supplier_id / project_id : vendor & project dari master (dropdown). vendor_name/project_name tetap salinan.
--   Backfill (COLLATE eksplisit: tabel master utf8mb4_unicode_ci, tabel Rekap PPN utf8mb4_general_ci): id master diisi dari kecocokan NAMA persis untuk baris yang sudah ada; yang tidak cocok dibiarkan NULL
--   (form edit menampilkannya sebagai "belum di master" dan tetap bisa disimpan).
-- ============================================================
ALTER TABLE `clients`
  ADD COLUMN `npwp` VARCHAR(30) NULL AFTER `contact_person`;

ALTER TABLE `ppn_keluaran`
  ADD COLUMN `status` ENUM('draft','lengkap') NOT NULL DEFAULT 'lengkap' AFTER `notes`,
  ADD COLUMN `client_id` INT(10) UNSIGNED NULL AFTER `customer_name`,
  ADD COLUMN `sales_invoice_id` INT(10) UNSIGNED NULL AFTER `client_id`,
  ADD KEY `idx_ppn_keluaran_status` (`status`),
  ADD KEY `idx_ppn_keluaran_invoice` (`sales_invoice_id`);

ALTER TABLE `ppn_masukan`
  ADD COLUMN `supplier_id` INT(10) UNSIGNED NULL AFTER `vendor_name`,
  ADD COLUMN `project_id` INT(10) UNSIGNED NULL AFTER `project_name`;

UPDATE `ppn_keluaran` k
  JOIN `clients` c ON c.client_name = k.customer_name COLLATE utf8mb4_unicode_ci AND c.deleted_at IS NULL
   SET k.client_id = c.id
 WHERE k.client_id IS NULL;

UPDATE `ppn_masukan` m
  JOIN `suppliers` s ON s.supplier_name = m.vendor_name COLLATE utf8mb4_unicode_ci AND s.deleted_at IS NULL
   SET m.supplier_id = s.id
 WHERE m.supplier_id IS NULL;

UPDATE `ppn_masukan` m
  JOIN `projects` p ON p.project_name = m.project_name COLLATE utf8mb4_unicode_ci AND p.deleted_at IS NULL
   SET m.project_id = p.id
 WHERE m.project_id IS NULL AND m.project_name IS NOT NULL;
