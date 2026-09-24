-- =========================================================================
-- 2026_09_24_c_information_end_date.sql
--
-- Tambah "Tanggal Berakhir" (opsional) ke Pusat Informasi -- dipakai untuk
-- menentukan sampai kapan sebuah informasi (khususnya Maintenance/Pengumuman)
-- masih layak tampil sebagai warning di Dashboard. NULL = tidak ada batas
-- akhir, tetap aktif sampai admin menonaktifkan manual.
-- =========================================================================

ALTER TABLE `information`
  ADD COLUMN `end_date` DATE NULL AFTER `publish_date`;
