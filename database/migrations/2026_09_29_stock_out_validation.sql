-- Validasi Pengeluaran Barang: status persetujuan per baris stock_out.
--
-- Pola identik Validasi Kas (2026_09_10_cash_validation.sql): kolom
-- validation_status/validated_by/validated_at/validation_note, data lama
-- di-grandfather 'tervalidasi' supaya workflow hanya berlaku transaksi baru.
--
-- Stok TIDAK terpengaruh oleh validasi ini -- stock_out sudah mendebit stok
-- SAAT DIBUAT (StockOutController::store(), lihat Inventory::debitStock()).
-- Validasi di sini murni lapisan approval/audit di atasnya (fallback aman
-- sesuai instruksi: kalau sistem existing sudah mengurangi stok saat dibuat,
-- validasi jadi approval layer saja -- supaya tidak ada risiko stok
-- terpotong dua kali saat approve/reject).
--
-- Hanya Super Admin yang otomatis berwenang (lihat permission_helper.php --
-- module tak dikenal di role_permissions = tidak ada yang boleh, Super Admin
-- selalu ditambahkan otomatis). Untuk memberi akses ke user tertentu (mis.
-- pemilik/"Gusti") TANPA hardcode nama di kode, beri override per-user lewat
-- User Management > (user terkait) > Hak Akses > centang
-- "Validasi Pengeluaran Barang" (module stock_out_validation).

ALTER TABLE stock_out
    ADD COLUMN validation_status ENUM('menunggu','tervalidasi','ditolak') NOT NULL DEFAULT 'menunggu' AFTER notes,
    ADD COLUMN validated_by INT UNSIGNED NULL AFTER validation_status,
    ADD COLUMN validated_at DATETIME NULL AFTER validated_by,
    ADD COLUMN validation_note VARCHAR(255) NULL AFTER validated_at,
    ADD KEY idx_stock_out_validation_status (validation_status),
    ADD CONSTRAINT fk_stock_out_validated_by FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL;

UPDATE stock_out
   SET validation_status = 'tervalidasi',
       validated_at      = COALESCE(updated_at, created_at),
       validation_note   = 'Otomatis: data sebelum fitur Validasi Pengeluaran Barang'
 WHERE deleted_at IS NULL;

-- ============================================================
-- Revisi Kas: kategori "Inventory Teknik" SEHARUSNYA tidak wajib Project
-- (sama seperti "Inventory Kantor"), tapi migrasi 2026_09_03 keliru
-- menyamakannya dengan stock_scope 'proyek'. CashController sudah 100%
-- data-driven dari cash_categories.stock_scope (lihat saveItems()/
-- validateInput()/_item_row.php) -- jadi cukup perbaiki datanya, tidak perlu
-- ubah kode apa pun.
-- ============================================================
UPDATE cash_categories
   SET stock_scope = 'kantor'
 WHERE category_name = 'Inventory Teknik' AND deleted_at IS NULL;
