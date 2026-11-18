-- ============================================================
-- Invoice Keluar: perlakuan PPN per invoice (2 pilihan di form Tambah/Edit):
--   'termasuk' = Harga Termasuk PPN  -> DPP = harga / (1 + PPN%), PPN = harga - DPP, total = harga
--                                       (PPN TIDAK ditambahkan lagi -- sudah ada di dalam harga)
--   'tanpa'    = Harga Tidak Termasuk PPN -> PPN tidak dihitung & tidak dicetak, total = harga
-- Nilai ketiga 'tambah' = format LAMA (PPN% ditambahkan di atas harga). TIDAK ditawarkan di form
-- untuk invoice baru; ada hanya supaya SEMUA invoice yang sudah ada (dan invoice yang terbit dari
-- Order Penjualan) tetap dihitung & dicetak persis seperti saat dibuat. DEFAULT 'tambah' =>
-- baris lama otomatis aman, tanpa backfill ke nilai lain.
--
-- dpp_amount = agregat DPP (SUM 'amount' tiap termin), disimpan eksplisit supaya detail/cetak/
-- laporan tidak perlu menghitung ulang. Backfill: untuk invoice lama sama dengan dp_amount.
-- Additive only.
-- ============================================================
ALTER TABLE `sales_invoices`
  ADD COLUMN `ppn_mode` ENUM('tambah','termasuk','tanpa') NOT NULL DEFAULT 'tambah' AFTER `ppn_percent`,
  ADD COLUMN `dpp_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER `ppn_mode`;

UPDATE `sales_invoices` SET `dpp_amount` = `dp_amount`;
