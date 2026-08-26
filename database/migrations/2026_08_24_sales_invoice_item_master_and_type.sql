-- ============================================================
-- REVISI (2026-08-24, revisi ke-3 hari ini): Item Invoice Keluar pakai Master
-- Barang + kategori Invoice Project/Lampu (numbering terpisah).
--
-- AUDIT sebelum menulis migration ini (lihat data live db_stok_proyek):
-- 1) sales_invoice_items SELAMA INI cuma description bebas (VARCHAR) -- data
--    live sudah campur barang fisik ("Inverter Deye 5kW...") DAN baris jasa
--    freetext ("Jasa konsultasi teknis", "Sewa Crane/Skylift... mob demob").
--    item_id ditambahkan NULLABLE (bukan wajib) supaya baris jasa yang memang
--    bukan Barang gudang tetap valid, tidak memaksa dibuatkan Master Barang.
-- 2) document_number_counters.doc_type='sales_invoice' sudah next_number=4
--    (3 invoice Project asli sudah dibuat pakai format 001-003/INV.HME/VIII/2026).
--    Konter ini DIPERTAHANKAN sebagai jalur "project" (tidak di-reset). Kategori
--    "lampu" pakai doc_type BARU 'sales_invoice_lampu' -- otomatis mulai dari 1
--    saat invoice Lampu pertama dibuat (DocumentNumber::next() insert baris baru
--    kalau doc_type+year belum ada), TANPA butuh baris seed manual di sini.
-- 3) code_configs (Master Kode: Barang/Supplier/Client/Gudang/Project) SUDAH
--    persis sesuai audit Master Data terbaru -- TIDAK diubah sama sekali.
--
-- Additive only: 2 kolom nullable/default + 1 baris system_settings baru.
-- ============================================================

ALTER TABLE `sales_invoice_items`
  ADD COLUMN `item_id` INT UNSIGNED NULL AFTER `sales_invoice_id`,
  ADD CONSTRAINT `fk_sii_item` FOREIGN KEY (`item_id`) REFERENCES `items`(`id`);

-- DEFAULT 'project' supaya 3 invoice yang sudah ada (001-003/INV.HME/VIII/2026)
-- otomatis terklasifikasi benar tanpa UPDATE manual terpisah.
ALTER TABLE `sales_invoices`
  ADD COLUMN `invoice_type` ENUM('project','lampu') NOT NULL DEFAULT 'project' AFTER `invoice_number`;

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`)
SELECT 'prefix_fkt', 'FKT.HME', 'numbering'
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'prefix_fkt');
