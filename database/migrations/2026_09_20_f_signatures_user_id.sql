-- =========================================================================
-- 2026_09_20_signatures_user_id.sql
--
-- Tambah `user_id` (nullable, UNIQUE) ke `signatures` -- menautkan 1 baris
-- signature ke 1 akun (dikelola user sendiri lewat Profil > Tanda Tangan
-- Saya). Dipakai untuk auto-resolve tanda tangan PO dari user yang login
-- (lihat PurchaseOrderController::store/update).
--
-- ADDITIVE: kolom NULL-able, seluruh signature lama (dibuat manual lewat
-- Master Data > Tanda Tangan, tidak terkait akun manapun) tetap berfungsi
-- apa adanya untuk Invoice Keluar / Surat Jalan / Tanda Terima -- modul-modul
-- itu TIDAK disentuh oleh revisi ini.
-- =========================================================================

ALTER TABLE `signatures`
  ADD COLUMN `user_id` INT UNSIGNED NULL AFTER `id`,
  ADD UNIQUE KEY `uq_signatures_user` (`user_id`),
  ADD CONSTRAINT `fk_signatures_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`);
