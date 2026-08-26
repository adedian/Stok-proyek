-- ============================================================
-- REVISI 7 (2026-08-26): Project - PIC jadi input Nama bebas
--
-- Sebelumnya PIC Project (pm_id) adalah dropdown wajib merujuk ke akun
-- user (users.id) -- padahal PIC di lapangan sering bukan user aplikasi
-- ini. ADDITIVE ONLY: tambah pic_name (free text), backfill dari data
-- pm_id yang sudah ada supaya tidak hilang. Kolom pm_id & FK-nya TIDAK
-- dihapus (riwayat tetap ada), hanya sudah tidak dipakai di form/list.
-- created_by TIDAK diubah -- tetap murni "akun yang membuat data".
-- ============================================================

ALTER TABLE `projects`
  ADD COLUMN `pic_name` VARCHAR(150) NULL AFTER `pm_id`;

UPDATE `projects` p
  JOIN `users` u ON u.id = p.pm_id
  SET p.pic_name = u.full_name
  WHERE p.pic_name IS NULL AND p.pm_id IS NOT NULL;
