-- ============================================================
-- Invoice Keluar > Termin Tagihan: checkbox "pakai persentase" per baris termin.
--   use_percentage = 1 (default) : nominal termin = Jumlah x persentase (perilaku lama).
--   use_percentage = 0           : persentase tidak diisi; NOMINAL termin diketik manual
--                                  (kolom amount). percentage tetap diisi otomatis (amount/Jumlah)
--                                  hanya sebagai informasi/validasi total <= 100%.
-- DEFAULT 1 => semua termin yang sudah ada tetap dihitung & dicetak persis seperti semula.
-- Additive only.
-- ============================================================
ALTER TABLE `sales_invoice_terms`
  ADD COLUMN `use_percentage` TINYINT(1) NOT NULL DEFAULT 1 AFTER `percentage`;
