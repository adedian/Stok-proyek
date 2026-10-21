-- Invoice Keluar jenis Lampu (FKT.HME) dapat diterbitkan dari sebuah Surat Jalan.
-- Kolom NULLABLE (invoice lama & jenis Project tidak terpengaruh). Satu Surat Jalan hanya boleh punya
-- satu invoice aktif -- ditegakkan di aplikasi (SalesInvoiceController, di bawah kunci baris Surat Jalan),
-- bukan UNIQUE, supaya invoice yang masuk Tempat Sampah membebaskan Surat Jalannya kembali.
ALTER TABLE `sales_invoices`
  ADD COLUMN `delivery_note_id` int(10) unsigned DEFAULT NULL AFTER `sales_order_id`,
  ADD KEY `idx_si_delivery_note` (`delivery_note_id`),
  ADD CONSTRAINT `fk_si_delivery_note` FOREIGN KEY (`delivery_note_id`) REFERENCES `delivery_notes` (`id`);
