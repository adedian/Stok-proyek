-- =========================================================================
-- 2026_10_21_information_image.sql
--
-- Pusat Informasi: gambar pendukung (opsional). Disimpan SUDAH DIKOMPRES di server
-- (< 300 KB, lihat compressImageToLimit() di app/helpers/image_helper.php) pada
-- public/uploads/information/<32 hex>.<jpg|png|webp>; kolom ini hanya menyimpan
-- path relatif-nya. NULL = informasi tanpa gambar. Additive only -- data lama tidak berubah.
-- =========================================================================

ALTER TABLE `information`
  ADD COLUMN `image_path` VARCHAR(255) NULL DEFAULT NULL AFTER `content`;
