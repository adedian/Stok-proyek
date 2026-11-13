-- Label Pengiriman: tambah Nama Perusahaan (opsional) di bawah nama, untuk Kepada dan Pengirim.
ALTER TABLE `shipping_labels`
  ADD COLUMN `to_company` VARCHAR(150) NULL AFTER `to_name`,
  ADD COLUMN `from_company` VARCHAR(150) NULL AFTER `from_name`;
