-- PIC Penerimaan per Project: user yang berhak membuat Penerimaan Barang untuk
-- PO milik project ini (lihat PurchaseOrder::receivablePoList()). SENGAJA
-- kolom & nama baru -- BUKAN reuse `projects.pic_name` (teks bebas, sudah
-- terbukti tidak reliable utk akses -- lihat migrasi project_user_access),
-- role `pic_project`, atau `user_pic_assignments` (PIC Kas) -- tiga konsep
-- "PIC" lain yang sudah ada di app ini, supaya tidak tumpang tindih makna.
-- NULL = project itu belum di-assign PIC Penerimaan -> semua user ber-akses
-- goods_receipt.create tetap bisa lihat PO-nya (fallback terbuka, lihat
-- PurchaseOrderController).

ALTER TABLE projects
    ADD COLUMN receipt_pic_user_id INT UNSIGNED NULL AFTER pic_name,
    ADD CONSTRAINT fk_project_receipt_pic FOREIGN KEY (receipt_pic_user_id)
        REFERENCES users (id) ON DELETE SET NULL;
