-- Master Kode v2: konfigurasi prefix per kelompok (BUKAN tabel kode duplicate).
-- Kode aktual TETAP di items.item_code / suppliers.supplier_code / clients.client_code /
-- warehouses.warehouse_code / projects.project_code -- tabel ini hanya menyimpan pola
-- (prefix/digit_length) + counter next_number per kelompok untuk generate otomatis yang
-- aman dari race condition (lihat CodeConfig::nextCode(), pakai transaction + row lock,
-- BUKAN naive MAX(code)+1).
CREATE TABLE IF NOT EXISTS code_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(30) NOT NULL UNIQUE,
    prefix VARCHAR(20) NOT NULL,
    digit_length TINYINT UNSIGNED NOT NULL DEFAULT 4,
    next_number INT UNSIGNED NOT NULL DEFAULT 1,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,
    CONSTRAINT fk_code_configs_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed konfigurasi dari prefix yang SUDAH dipakai LIVE saat ini (ITM/SUP/CLI/WH/PRJ --
-- bukan contoh BRG/GDG di spec, supaya generate kode tidak terhenti di hari pertama
-- fitur ini aktif dan kode baru tetap nyambung dengan histori). next_number dihitung
-- dari MAX(nomor existing)+1. INSERT IGNORE + UNIQUE(entity_type) supaya idempotent
-- (aman dijalankan ulang, tidak menimpa konfigurasi yang sudah diubah admin).
INSERT IGNORE INTO code_configs (entity_type, prefix, digit_length, next_number, status)
SELECT 'item', 'ITM', 5, COALESCE(MAX(CAST(SUBSTRING_INDEX(item_code, '-', -1) AS UNSIGNED)), 0) + 1, 'active'
FROM items WHERE deleted_at IS NULL;

INSERT IGNORE INTO code_configs (entity_type, prefix, digit_length, next_number, status)
SELECT 'supplier', 'SUP', 5, COALESCE(MAX(CAST(SUBSTRING_INDEX(supplier_code, '-', -1) AS UNSIGNED)), 0) + 1, 'active'
FROM suppliers WHERE deleted_at IS NULL;

INSERT IGNORE INTO code_configs (entity_type, prefix, digit_length, next_number, status)
SELECT 'client', 'CLI', 5, COALESCE(MAX(CAST(SUBSTRING_INDEX(client_code, '-', -1) AS UNSIGNED)), 0) + 1, 'active'
FROM clients WHERE deleted_at IS NULL;

INSERT IGNORE INTO code_configs (entity_type, prefix, digit_length, next_number, status)
SELECT 'warehouse', 'WH', 4, COALESCE(MAX(CAST(SUBSTRING_INDEX(warehouse_code, '-', -1) AS UNSIGNED)), 0) + 1, 'active'
FROM warehouses WHERE deleted_at IS NULL;

-- Project: kode lama berformat PRJ-2026-NNN (bukan PREFIX-NNNN sederhana). Config baru
-- ini hanya berlaku untuk project BARU ke depan (PRJ-0004 dst) -- kode lama TIDAK diubah,
-- sesuai aturan "prefix baru hanya berlaku untuk data baru".
INSERT IGNORE INTO code_configs (entity_type, prefix, digit_length, next_number, status)
SELECT 'project', 'PRJ', 4, COALESCE(MAX(CAST(SUBSTRING_INDEX(project_code, '-', -1) AS UNSIGNED)), 0) + 1, 'active'
FROM projects WHERE deleted_at IS NULL;
