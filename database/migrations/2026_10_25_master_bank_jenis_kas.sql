-- Master Bank: tambah jenis 'kas' (selain 'loan' & 'hr'). Jenis = klasifikasi rekening, bukan nama;
-- boleh banyak rekening per jenis. Nilai lama (loan/hr) dan seluruh transaksi tidak berubah.
ALTER TABLE master_banks
  MODIFY COLUMN jenis ENUM('loan','hr','kas') NOT NULL;
