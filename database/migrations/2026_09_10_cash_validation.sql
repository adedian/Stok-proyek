-- Validasi Kas: status persetujuan per transaksi Kas.
--
-- Routing validator berdasarkan `division` (snapshot role pembuat/PIC):
--   division 'accounting' -> divalidasi role accounting  (+ super_admin)
--   division 'purchase'   -> divalidasi role purchase     (+ super_admin)
--   division 'project'    -> divalidasi role project_manager (+ super_admin)
--   division 'umum'       -> hanya super_admin
--
-- Efek: transaksi 'tervalidasi' terkunci (hanya Super Admin yang bisa
-- edit/hapus). 'ditolak' bisa diedit pembuatnya -> status balik 'menunggu'.
-- Stok TIDAK terpengaruh (Kas tetap kredit stok saat disimpan).

ALTER TABLE cash_transactions
    ADD COLUMN validation_status ENUM('menunggu','tervalidasi','ditolak') NOT NULL DEFAULT 'menunggu' AFTER division,
    ADD COLUMN validated_by INT UNSIGNED NULL AFTER validation_status,
    ADD COLUMN validated_at DATETIME NULL AFTER validated_by,
    ADD COLUMN validation_note VARCHAR(255) NULL AFTER validated_at,
    ADD KEY idx_cash_validation_status (validation_status),
    ADD CONSTRAINT fk_cash_validated_by FOREIGN KEY (validated_by) REFERENCES users (id) ON DELETE SET NULL;

-- Data lama di-grandfather sebagai 'tervalidasi' supaya workflow hanya berlaku
-- untuk transaksi baru (tanpa validated_by -- ditandai lewat catatan).
UPDATE cash_transactions
   SET validation_status = 'tervalidasi',
       validated_at      = COALESCE(updated_at, created_at),
       validation_note   = 'Otomatis: data sebelum fitur Validasi Kas'
 WHERE deleted_at IS NULL;

-- Hak akses modul baru (INSERT IGNORE -- aman diulang; tidak menimpa edit admin).
INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES
    ('accounting',      'cash_validation', 'view',     1),
    ('accounting',      'cash_validation', 'validate', 1),
    ('purchase',        'cash_validation', 'view',     1),
    ('purchase',        'cash_validation', 'validate', 1),
    ('project_manager', 'cash_validation', 'view',     1),
    ('project_manager', 'cash_validation', 'validate', 1),
    ('pic_project',     'cash_validation', 'view',     0),
    ('pic_project',     'cash_validation', 'validate', 0),
    ('admin_project',   'cash_validation', 'view',     0),
    ('admin_project',   'cash_validation', 'validate', 0);
