-- ============================================================
-- MIGRASI: Account Settings (profil akun sendiri + ganti password).
-- Tabel `users` existing sudah cukup untuk fitur ini -- cukup tambah
-- 3 kolom baru, TIDAK membuat tabel baru. ADDITIVE, semua nullable,
-- tidak ada kolom/tabel lama yang diubah tipe/dihapus.
-- ============================================================

USE `db_stok_proyek`;

ALTER TABLE `users`
  ADD COLUMN `phone` VARCHAR(20) NULL AFTER `email`,
  ADD COLUMN `profile_photo` VARCHAR(255) NULL AFTER `phone`,
  ADD COLUMN `password_changed_at` DATETIME NULL AFTER `password`;
