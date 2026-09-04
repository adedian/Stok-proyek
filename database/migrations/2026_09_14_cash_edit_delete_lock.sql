-- ============================================================
-- Kas: edit & hapus hanya Super Admin + Accounting (2026-09-14)
-- ============================================================
-- Sebelumnya Purchase / PIC Project / Admin Project juga bisa mengedit &
-- menghapus transaksi Kas (baris role_permissions hasil seed lama). Sekarang
-- role selain Super Admin & Accounting hanya boleh MEMBUAT & MELIHAT.
--
-- Super Admin tidak butuh baris (selalu full-access lewat can()).
-- Matriks Hak Akses di UI ikut memperlihatkan perubahan ini.
-- ============================================================

USE `db_stok_proyek`;

-- Cabut edit & hapus Kas dari semua role selain Accounting.
UPDATE `role_permissions`
   SET `allowed` = 0
 WHERE `module` = 'cash'
   AND `action` IN ('edit', 'delete')
   AND `role_slug` NOT IN ('super_admin', 'accounting');

-- Pastikan Accounting punya edit + hapus Kas.
UPDATE `role_permissions`
   SET `allowed` = 1
 WHERE `module` = 'cash'
   AND `action` IN ('edit', 'delete')
   AND `role_slug` = 'accounting';

INSERT INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`)
SELECT 'accounting', 'cash', 'edit', 1
  FROM DUAL
 WHERE NOT EXISTS (
   SELECT 1 FROM `role_permissions`
    WHERE `role_slug` = 'accounting' AND `module` = 'cash' AND `action` = 'edit'
 );

INSERT INTO `role_permissions` (`role_slug`, `module`, `action`, `allowed`)
SELECT 'accounting', 'cash', 'delete', 1
  FROM DUAL
 WHERE NOT EXISTS (
   SELECT 1 FROM `role_permissions`
    WHERE `role_slug` = 'accounting' AND `module` = 'cash' AND `action` = 'delete'
 );

-- Bersihkan override per-user (user_permissions) untuk cash.edit / cash.delete
-- supaya tidak ada yang lolos lewat pengecualian per-orang.
DELETE FROM `user_permissions`
 WHERE `module` = 'cash' AND `action` IN ('edit', 'delete');
