-- ============================================================
-- MASTER KODE: multi-prefix per kategori + format PREFIX.0001.MASTERCODE
-- (2026-09-02)
-- ============================================================
-- Sebelumnya: 1 config per entity (entity_type UNIQUE), format PREFIX-NNNNN.
-- Sekarang: BANYAK prefix per entity, tiap prefix punya counter sendiri.
-- Format kode baru: PREFIX.NOMOR.MASTERCODE  (mis. ME.0001.ITM)
--   PREFIX     = diinput admin saat menambah prefix / membuat data
--   NOMOR      = otomatis, per (entity_type, prefix)
--   MASTERCODE = kode akhir per entity (ITM/SUP/CLI/WH/PRJ), diatur di Master Kode
--
-- Kode LAMA (format PREFIX-NNNNN, mis. ITM-00025) TIDAK diubah -- dua format
-- hidup berdampingan. Baris config lama dipertahankan sebagai "prefix legacy"
-- (counter-nya lanjut); admin bisa rename/ tambah prefix lain lewat UI.

ALTER TABLE `code_configs`
  ADD COLUMN `master_code` VARCHAR(10) NOT NULL DEFAULT '' AFTER `prefix`;

UPDATE `code_configs` SET `master_code` = 'ITM'
 WHERE `entity_type` IN ('item_stok_proyek','item_stok_lampu','item_inventory_kantor');
UPDATE `code_configs` SET `master_code` = 'SUP' WHERE `entity_type` = 'supplier';
UPDATE `code_configs` SET `master_code` = 'CLI' WHERE `entity_type` = 'client';
UPDATE `code_configs` SET `master_code` = 'WH'  WHERE `entity_type` = 'warehouse';
UPDATE `code_configs` SET `master_code` = 'PRJ' WHERE `entity_type` = 'project';

-- entity_type tidak lagi unik; kombinasi (entity_type, prefix) yang unik.
ALTER TABLE `code_configs`
  DROP INDEX `entity_type`,
  ADD UNIQUE KEY `uq_entity_prefix` (`entity_type`, `prefix`),
  ADD KEY `idx_entity` (`entity_type`);
