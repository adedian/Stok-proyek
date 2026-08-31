-- ============================================================
-- Surat Jalan: tujuan pengiriman "Lainnya / Manual" (2026-09-01)
-- ============================================================
-- Sebelumnya destination_type hanya 'project' atau 'client' -- keduanya WAJIB
-- pilih dari data terdaftar. Tambah opsi 'manual' supaya Surat Jalan bisa
-- dibuat ke tujuan bebas (bengkel, kantor lain, sewa alat, dll) cukup dengan
-- mengisi Nama Tujuan + Kota, tanpa memilih Project/Client.
--
-- Additive: baris lama tetap 'project'/'client', default tidak berubah.

ALTER TABLE `delivery_notes`
  MODIFY COLUMN `destination_type` ENUM('project','client','manual') NOT NULL DEFAULT 'project';
