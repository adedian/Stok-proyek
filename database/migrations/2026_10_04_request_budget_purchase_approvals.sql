-- ============================================================
-- Request Budget: approval ganda + proses Purchase (2026-10-04)
-- ============================================================
-- 1. request_budget_approvals : 1 baris per "slot" approval (pm = Project Manager,
--    purchase = Purchase). Request baru dianggap APPROVED hanya kalau KEDUA slot
--    APPROVED. Disimpan terpisah (siapa, role, kapan, catatan) per slot.
-- 2. request_budget_pos / _invoices / _attachments : data & dokumen yang dilengkapi
--    Andy (Purchase) setelah approval selesai. Semuanya berelasi ke request_budgets.id.
-- 3. request_budgets : kolom penanda "dilengkapi Purchase".
--
-- Status baru PURCHASE_COMPLETED ("Dilengkapi Andy") di antara APPROVED
-- (= menunggu proses Purchase) dan FORWARDED (diajukan ke Purwati/Nissa).
-- ============================================================

ALTER TABLE request_budgets
    ADD COLUMN purchase_completed_by INT UNSIGNED NULL AFTER approved_by_role,
    ADD COLUMN purchase_completed_at DATETIME NULL AFTER purchase_completed_by,
    ADD COLUMN purchase_notes TEXT NULL AFTER purchase_completed_at,
    ADD CONSTRAINT fk_rb_purch_done_by FOREIGN KEY (purchase_completed_by) REFERENCES users (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS request_budget_approvals (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_budget_id INT UNSIGNED NOT NULL,
    slot VARCHAR(20) NOT NULL,                 -- 'pm' | 'purchase'
    status VARCHAR(20) NOT NULL DEFAULT 'PENDING',   -- PENDING | APPROVED | REJECTED
    approver_user_id INT UNSIGNED NULL,
    approver_role VARCHAR(50) NULL,
    note TEXT NULL,
    acted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rba_slot (request_budget_id, slot),
    CONSTRAINT fk_rba_rb FOREIGN KEY (request_budget_id) REFERENCES request_budgets (id) ON DELETE CASCADE,
    CONSTRAINT fk_rba_user FOREIGN KEY (approver_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_budget_pos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_budget_id INT UNSIGNED NOT NULL,
    po_number VARCHAR(80) NOT NULL,
    po_date DATE NOT NULL,
    vendor_name VARCHAR(200) NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    file_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rbpo_rb (request_budget_id),
    KEY idx_rbpo_number (po_number),
    CONSTRAINT fk_rbpo_rb FOREIGN KEY (request_budget_id) REFERENCES request_budgets (id) ON DELETE CASCADE,
    CONSTRAINT fk_rbpo_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_budget_invoices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_budget_id INT UNSIGNED NOT NULL,
    invoice_number VARCHAR(80) NOT NULL,
    invoice_date DATE NOT NULL,
    vendor_name VARCHAR(200) NULL,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    file_path VARCHAR(255) NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rbinv_rb (request_budget_id),
    KEY idx_rbinv_number (invoice_number),
    CONSTRAINT fk_rbinv_rb FOREIGN KEY (request_budget_id) REFERENCES request_budgets (id) ON DELETE CASCADE,
    CONSTRAINT fk_rbinv_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_budget_attachments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_budget_id INT UNSIGNED NOT NULL,
    doc_name VARCHAR(200) NOT NULL,
    description VARCHAR(500) NULL,
    file_path VARCHAR(255) NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rbatt_rb (request_budget_id),
    CONSTRAINT fk_rbatt_rb FOREIGN KEY (request_budget_id) REFERENCES request_budgets (id) ON DELETE CASCADE,
    CONSTRAINT fk_rbatt_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
