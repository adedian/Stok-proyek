-- ============================================================
-- Penerima Barang per Purchase Order (2026-10-01)
-- ============================================================
-- Penerima Barang ditentukan LANGSUNG di PO (bukan lagi per Project lewat
-- projects.receipt_pic_user_id). Hanya user ini (plus Super Admin) yang melihat
-- PO tsb di Tambah Penerimaan Barang. Relasi ke users.id (FK), bukan teks nama.
--
-- DATA LAMA: kolom NULL = "Belum ditentukan". TIDAK diisi otomatis (tidak
-- boleh menebak user). PO tanpa penerima tidak muncul di Tambah Penerimaan
-- sampai Super Admin/Purchase menentukan penerimanya lewat Edit PO.
--
-- projects.receipt_pic_user_id dibiarkan di DB (tidak dihapus, data historis)
-- tetapi tidak lagi dipakai untuk memfilter PO.
-- ============================================================

ALTER TABLE purchase_orders
    ADD COLUMN receiver_user_id INT UNSIGNED NULL AFTER project_id,
    ADD KEY idx_po_receiver_user (receiver_user_id),
    ADD CONSTRAINT fk_po_receiver_user FOREIGN KEY (receiver_user_id)
        REFERENCES users (id) ON DELETE SET NULL;
