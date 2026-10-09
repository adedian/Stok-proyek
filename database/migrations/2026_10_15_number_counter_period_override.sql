-- ============================================================
-- Penomoran Dokumen: Bulan & Tahun pada nomor bisa di-reset manual dari
-- Pengaturan Sistem > Penomoran (tabel "Reset Nomor Urut").
--
-- period_month (1-12) / period_year: kalau diisi, nomor berikutnya memakai
-- bulan romawi / tahun ini sebagai GANTI bulan/tahun dari tanggal dokumen.
-- NULL = otomatis (perilaku lama). Kunci counter tetap (doc_type, year) --
-- year di situ masih tahun TANGGAL dokumen, jadi urutan tidak ikut tergeser.
-- Additive only; semua counter yang ada tetap otomatis.
-- ============================================================
ALTER TABLE `document_number_counters`
  ADD COLUMN `period_month` TINYINT UNSIGNED NULL DEFAULT NULL AFTER `next_number`,
  ADD COLUMN `period_year`  SMALLINT UNSIGNED NULL DEFAULT NULL AFTER `period_month`;
