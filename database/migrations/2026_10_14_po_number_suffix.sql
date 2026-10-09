-- ============================================================
-- Purchase Order: akhiran nomor PO (mis. "rev1") yang ditambahkan di BELAKANG
-- nomor otomatis -> 147/PO.HME/X/2026/rev1. Disimpan terpisah supaya saat edit
-- bagian nomor otomatisnya bisa dipisahkan lagi dari akhirannya. po_number tetap
-- menyimpan nomor LENGKAP (dipakai cetak, laporan, pembayaran, dsb). Additive only.
-- ============================================================
ALTER TABLE `purchase_orders`
  ADD COLUMN `po_number_suffix` VARCHAR(30) NULL DEFAULT NULL AFTER `po_number`;
