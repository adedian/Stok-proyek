-- Nomor urut kode barang TERPISAH per (prefix, Kode Akhir) untuk kelompok ber-pilihan Kode Akhir
-- (Inventory Kantor: INV.HME / INV.EXGF / INV.EXSS). Sebelumnya ketiganya berbagi satu counter
-- code_configs.next_number per prefix, jadi INV.EXGF ikut nomor INV.HME.
-- Baris counter dibuat otomatis saat kode pertama diterbitkan; sebelum itu, nomor berikutnya
-- dihitung dari kode yang sudah ada (MAX+1) atau 1 kalau belum ada -- tanpa backfill di sini.
CREATE TABLE IF NOT EXISTS code_config_suffix_counters (
  config_id   INT UNSIGNED NOT NULL,
  master_code VARCHAR(20)  NOT NULL,
  next_number INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (config_id, master_code),
  CONSTRAINT fk_ccsc_config FOREIGN KEY (config_id) REFERENCES code_configs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
