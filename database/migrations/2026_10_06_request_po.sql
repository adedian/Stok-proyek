-- ============================================================
-- Modul Request PO (2026-10-06)
-- ============================================================
-- Modul MANDIRI: permintaan pembuatan PO oleh tim Purchase, disetujui/ditolak Andy.
-- SENGAJA tidak berelasi ke Request Budget / PO / Invoice / Kas / Stok:
-- tidak ada FK atau transaksi otomatis ke modul tersebut. Berhenti di
-- DISETUJUI atau DITOLAK.
--
-- HANYA 4 status (nilai DB; label Indonesia hanya di UI):
--   DRAFT, PENDING_APPROVAL, APPROVED, REJECTED
-- Item barang hanya Nama Barang + Qty -- tanpa harga/vendor/PPN/diskon.
-- ============================================================

CREATE TABLE IF NOT EXISTS request_pos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_po_number VARCHAR(30) NOT NULL,
    request_date DATE NOT NULL,
    requester_name VARCHAR(100) NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    purpose VARCHAR(200) NOT NULL,
    notes TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',

    submitted_at DATETIME NULL,

    approved_by INT UNSIGNED NULL,
    approved_by_role VARCHAR(60) NULL,
    approved_at DATETIME NULL,
    approval_notes VARCHAR(500) NULL,

    rejected_by INT UNSIGNED NULL,
    rejected_by_role VARCHAR(60) NULL,
    rejected_at DATETIME NULL,
    rejection_reason TEXT NULL,

    deleted_at DATETIME NULL,
    deleted_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_rpo_number (request_po_number),
    KEY idx_rpo_project (project_id),
    KEY idx_rpo_creator (created_by),
    KEY idx_rpo_status (status),
    KEY idx_rpo_date (request_date),
    CONSTRAINT fk_rpo_project FOREIGN KEY (project_id) REFERENCES projects (id),
    CONSTRAINT fk_rpo_creator FOREIGN KEY (created_by) REFERENCES users (id),
    CONSTRAINT fk_rpo_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rpo_rejected_by FOREIGN KEY (rejected_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_po_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_po_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    qty DECIMAL(15,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rpoi_rpo (request_po_id),
    CONSTRAINT fk_rpoi_rpo FOREIGN KEY (request_po_id) REFERENCES request_pos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_po_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_po_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    role_name VARCHAR(60) NULL,
    action VARCHAR(40) NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rpoh_rpo (request_po_id),
    CONSTRAINT fk_rpoh_rpo FOREIGN KEY (request_po_id) REFERENCES request_pos (id) ON DELETE CASCADE,
    CONSTRAINT fk_rpoh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Counter nomor (RPO-0001, RPO-0002, ...) -- atomik lewat SELECT ... FOR UPDATE
-- (lihat RequestPo::nextNumber()). Berkelanjutan, tidak reset per tahun.
INSERT INTO document_number_counters (doc_type, year, next_number)
SELECT 'request_po', 0, 1 FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM document_number_counters WHERE doc_type = 'request_po' AND year = 0);
