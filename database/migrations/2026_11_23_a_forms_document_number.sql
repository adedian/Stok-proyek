-- ============================================================
-- Menu Form: nomor dokumen otomatis untuk 4 jenis form
--   Kwitansi             -> 001/KWT.HME/XI/2026
--   Pengajuan Bon Sementara -> 001/BS.HME/XI/2026
--   Tanda Terima Dokumen -> 001/TD.HME/XI/2026
--   Form Order Barang (dulu "Order Form") -> 001/PB.HME/XI/2026
-- Form Pengiriman Dokumen tidak bernomor.
--
-- Nomor dibuat SEKALI saat form disimpan (DocumentNumber::next, counter atomik per jenis & tahun),
-- lalu disimpan permanen di kolom ini. Form lama (sebelum migrasi ini) dibiarkan tanpa nomor (NULL)
-- supaya dokumen yang sudah pernah dicetak tidak berubah. Additive only.
-- ============================================================
ALTER TABLE `forms`
  ADD COLUMN `form_number` VARCHAR(50) NULL AFTER `form_type`,
  ADD KEY `idx_forms_number` (`form_number`);
