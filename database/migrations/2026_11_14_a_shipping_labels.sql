-- Label Pengiriman: form alamat "Kepada" + "Pengirim" yang dicetak sebagai dua kotak (mis. untuk ditempel di paket).
-- Berdiri sendiri (tanpa nomor dokumen, tanpa relasi ke Client/Supplier) -- alamat diketik bebas per label.
-- Soft delete (deleted_at) supaya ikut Tempat Sampah seperti dokumen lain.
CREATE TABLE IF NOT EXISTS `shipping_labels` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `to_name` VARCHAR(150) NOT NULL COMMENT 'Kepada: nama penerima (mis. Bapak Ade)',
  `to_address` VARCHAR(255) NOT NULL COMMENT 'Kepada: alamat jalan',
  `to_city` VARCHAR(150) NULL COMMENT 'Kepada: kelurahan/kecamatan - kota',
  `to_postal_code` VARCHAR(20) NULL,
  `to_phone` VARCHAR(50) NULL,
  `from_name` VARCHAR(150) NOT NULL COMMENT 'Pengirim: nama',
  `from_address` VARCHAR(255) NOT NULL,
  `from_city` VARCHAR(150) NULL,
  `from_postal_code` VARCHAR(20) NULL,
  `from_phone` VARCHAR(50) NULL,
  `deleted_at` DATETIME NULL,
  `deleted_by` INT UNSIGNED NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT UNSIGNED NULL,
  KEY `idx_sl_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
