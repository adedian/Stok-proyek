-- ============================================================
-- Invoice Keluar: status Draft ("Simpan sebagai Draft" di form Tambah/Edit).
-- Draft = invoice yang belum final: nomor sudah ditetapkan, tetapi BELUM dihitung
-- sebagai tagihan (tidak masuk "Belum Tertagih"/kartu dashboard, tidak bisa dipilih
-- di Tanda Terima, tanpa notifikasi). DEFAULT 'final' supaya SEMUA invoice yang
-- sudah ada otomatis tetap berstatus final. Additive only.
-- ============================================================
ALTER TABLE `sales_invoices`
  ADD COLUMN `status` ENUM('draft','final') NOT NULL DEFAULT 'final' AFTER `document_kind`;
