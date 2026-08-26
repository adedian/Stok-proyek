-- Revisi lanjutan: pilih Tanda Tangan secara eksplisit saat Tambah/Edit PO
-- (menggantikan pencocokan otomatis berdasarkan nama), supaya tanda tangan yang
-- tercetak di dokumen PO pasti sesuai pilihan user, bukan tebakan nama.
ALTER TABLE purchase_orders
    ADD COLUMN signature_id INT UNSIGNED NULL AFTER pembuat_po,
    ADD CONSTRAINT fk_po_signature FOREIGN KEY (signature_id) REFERENCES signatures(id) ON DELETE SET NULL;
