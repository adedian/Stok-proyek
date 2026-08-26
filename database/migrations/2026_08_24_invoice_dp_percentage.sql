-- ============================================================
-- REVISI (2026-08-24, lanjutan): Tagihan DP pada Invoice Keluar.
--
-- Rumus BARU (menggantikan "Total = Jumlah + PPN" lama):
--   Jumlah      = SUM(harga jumlah tiap item)          -- kolom `subtotal`, TIDAK berubah maknanya
--   Tagihan DP  = Jumlah x DP%                          -- kolom baru `dp_amount`
--   PPN         = Tagihan DP x PPN%  (BUKAN dari Jumlah) -- kolom `ppn_amount` (existing), rumus sumbernya berubah
--   Total       = Tagihan DP + PPN                       -- kolom `total_amount` (existing), rumus berubah
--
-- Persentase DP TIDAK boleh hardcode -- diambil dari master baru `dp_percentages`
-- (pola sama dengan `signatures`: name + percentage + status aktif/nonaktif,
-- soft delete). Invoice menyimpan SNAPSHOT (`dp_percentage_id` + `dp_percentage`
-- + `dp_amount`) supaya invoice yang sudah terbit tidak ikut berubah kalau
-- master DP diedit/dinonaktifkan/dihapus belakangan -- `dp_percentage_id`
-- boleh jadi NULL (ON DELETE SET NULL) tanpa mempengaruhi angka yang sudah
-- tersimpan di `dp_percentage`/`dp_amount`.
--
-- Additive only. Tidak ada DROP/TRUNCATE/hapus histori.
-- ============================================================

CREATE TABLE IF NOT EXISTS `dp_percentages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `percentage` DECIMAL(5,2) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `deleted_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `sales_invoices`
  ADD COLUMN `dp_percentage_id` INT UNSIGNED NULL AFTER `client_id`,
  ADD COLUMN `dp_percentage` DECIMAL(5,2) NOT NULL DEFAULT 100.00 AFTER `dp_percentage_id`,
  ADD COLUMN `dp_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`,
  ADD CONSTRAINT `fk_si_dp_percentage` FOREIGN KEY (`dp_percentage_id`) REFERENCES `dp_percentages`(`id`) ON DELETE SET NULL;

-- Seed pilihan standar (10%-100%, kelipatan 10) -- admin bebas tambah/nonaktifkan
-- lewat Master Data > Persentase DP. SENGAJA tidak menyertakan 0% (butuh audit
-- business rule dulu apakah 0% relevan -- lihat catatan di prompt revisi).
INSERT INTO `dp_percentages` (`name`, `percentage`, `status`) VALUES
  ('DP 10%', 10.00, 'active'),
  ('DP 20%', 20.00, 'active'),
  ('DP 30%', 30.00, 'active'),
  ('DP 40%', 40.00, 'active'),
  ('DP 50%', 50.00, 'active'),
  ('DP 60%', 60.00, 'active'),
  ('DP 70%', 70.00, 'active'),
  ('DP 80%', 80.00, 'active'),
  ('DP 90%', 90.00, 'active'),
  ('DP 100% (Full/Tanpa DP)', 100.00, 'active');

-- Invoice yang SUDAH ADA sebelum revisi ini (dibuat dengan rumus lama Total =
-- Jumlah + PPN) di-backfill sebagai DP 100% -- itu satu-satunya nilai yang
-- membuat rumus baru (Tagihan DP = Jumlah x 100% = Jumlah, PPN dari Tagihan DP)
-- menghasilkan angka PERSIS SAMA dengan yang sudah tercetak/tersimpan
-- sebelumnya, jadi dokumen lama tidak "berubah" secara retroaktif.
UPDATE `sales_invoices` SET `dp_percentage` = 100.00, `dp_amount` = `subtotal` WHERE `dp_amount` = 0.00;
