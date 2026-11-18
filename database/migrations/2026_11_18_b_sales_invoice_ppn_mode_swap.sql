-- ============================================================
-- Invoice Keluar: arti pilihan PPN DIPERBAIKI (migrasi 2026_11_18_a salah arti).
--   Harga Termasuk PPN       ('termasuk')       : harga sudah memuat PPN -> PPN tidak dihitung/tidak dicetak, total = harga.
--   Harga Tidak Termasuk PPN ('tidak_termasuk') : PPN dihitung & ditambahkan di atas harga (perilaku asli aplikasi).
-- Hanya 2 nilai; nilai ketiga 'tambah' dihapus karena kini sama dengan 'tidak_termasuk'.
--
-- Pemetaan baris yang sudah ada (urutan penting karena nama 'termasuk' dipakai ulang):
--   1) 'termasuk' lama (PPN diekstrak dari harga, total = harga) -> jadi 'termasuk' BARU: total TETAP, PPN
--      dihilangkan (amount = total, ppn = 0). Pemisahan DPP/PPN lama tidak dipertahankan.
--   2) 'tanpa'  -> 'termasuk'       (PPN memang tidak dihitung; angka sudah benar)
--   3) 'tambah' -> 'tidak_termasuk' (PPN ditambahkan; angka sudah benar)
-- ============================================================
ALTER TABLE `sales_invoices`
  MODIFY COLUMN `ppn_mode` ENUM('tambah','termasuk','tanpa','tidak_termasuk') NOT NULL DEFAULT 'tidak_termasuk';

-- 1) termasuk lama -> collapse ke total (termin dulu, sebelum header ikut berubah)
UPDATE `sales_invoice_terms` t
  JOIN `sales_invoices` s ON s.id = t.sales_invoice_id AND s.ppn_mode = 'termasuk'
   SET t.amount = t.total_amount, t.ppn_amount = 0;
UPDATE `sales_invoices`
   SET dp_amount = total_amount, dpp_amount = total_amount, ppn_amount = 0, ppn_percent = 0
 WHERE ppn_mode = 'termasuk';

-- 2) & 3)
UPDATE `sales_invoices` SET ppn_mode = 'termasuk'       WHERE ppn_mode = 'tanpa';
UPDATE `sales_invoices` SET ppn_mode = 'tidak_termasuk' WHERE ppn_mode = 'tambah';

ALTER TABLE `sales_invoices`
  MODIFY COLUMN `ppn_mode` ENUM('termasuk','tidak_termasuk') NOT NULL DEFAULT 'tidak_termasuk';
