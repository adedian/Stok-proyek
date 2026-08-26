-- ============================================================
-- REVISI (2026-08-21): Nama Penerima manual pada Penerimaan Barang
-- + checkbox Select All pada Laporan Stok Barang (tidak butuh perubahan DB,
-- hanya dicatat di sini untuk riwayat revisi).
--
-- Konteks: goods_receipts.received_by adalah FK wajib ke users.id (mengharuskan
-- penerima barang jadi akun sistem terdaftar). Requirement baru: penerima barang
-- adalah ORANG NYATA yang menerima barang secara fisik, belum tentu punya akun
-- sistem -- jadi field ini diganti jadi input teks bebas. `received_by` TIDAK
-- di-drop (data lama & FK-nya tetap utuh untuk histori/audit trail), hanya
-- dilonggarkan jadi nullable karena form baru tidak lagi mengisinya. Kolom baru
-- `receiver_name` menyimpan nama manual untuk data BARU. Query baca menggunakan
-- COALESCE(receiver_name, users.full_name) supaya data lama tetap terbaca lewat
-- fallback ke nama user yang dulu tercatat.
-- ============================================================

ALTER TABLE `goods_receipts`
  MODIFY COLUMN `received_by` INT UNSIGNED NULL,
  ADD COLUMN `receiver_name` VARCHAR(150) NULL AFTER `received_by`;
