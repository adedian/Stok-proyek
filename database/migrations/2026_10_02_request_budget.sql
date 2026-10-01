-- ============================================================
-- Modul Request Budget (2026-10-02)
-- ============================================================
-- Modul MANDIRI untuk pengajuan budget project: Tim Project -> approval
-- PM/Purchase -> diajukan ke Accounting -> diproses -> dana diterima -> selesai.
-- SENGAJA tidak berelasi ke PO / Pembayaran / Kas / Invoice / Stok: tidak ada
-- transaksi otomatis ke modul lain. Hanya mencatat alur pengajuan budget.
--
-- Nilai status (konsisten di DB, label Indonesia hanya di UI):
--   DRAFT, PENDING_APPROVAL, APPROVED, REJECTED, SUBMITTED_ACCOUNTING,
--   ACCOUNTING_PROCESS, FUNDS_RECEIVED, COMPLETED, ACCOUNTING_REJECTED
-- ============================================================

CREATE TABLE IF NOT EXISTS request_budgets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_number VARCHAR(30) NOT NULL,
    request_date DATE NOT NULL,
    project_id INT UNSIGNED NOT NULL,
    requester_user_id INT UNSIGNED NOT NULL,
    purpose VARCHAR(200) NOT NULL,
    period_label VARCHAR(100) NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    total_amount DECIMAL(18,2) NOT NULL DEFAULT 0,

    submitted_at DATETIME NULL,

    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejected_by INT UNSIGNED NULL,
    rejected_at DATETIME NULL,
    rejection_reason TEXT NULL,

    submitted_accounting_by INT UNSIGNED NULL,
    submitted_accounting_at DATETIME NULL,

    accounting_processed_by INT UNSIGNED NULL,
    accounting_processed_at DATETIME NULL,
    accounting_rejected_by INT UNSIGNED NULL,
    accounting_rejected_at DATETIME NULL,
    accounting_rejection_reason TEXT NULL,

    funds_received_by INT UNSIGNED NULL,
    funds_received_at DATETIME NULL,
    completed_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,

    deleted_at DATETIME NULL,
    deleted_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_rb_number (request_number),
    KEY idx_rb_project (project_id),
    KEY idx_rb_requester (requester_user_id),
    KEY idx_rb_status (status),
    KEY idx_rb_date (request_date),
    CONSTRAINT fk_rb_project FOREIGN KEY (project_id) REFERENCES projects (id),
    CONSTRAINT fk_rb_requester FOREIGN KEY (requester_user_id) REFERENCES users (id),
    CONSTRAINT fk_rb_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_rejected_by FOREIGN KEY (rejected_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_sub_acc_by FOREIGN KEY (submitted_accounting_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_acc_proc_by FOREIGN KEY (accounting_processed_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_acc_rej_by FOREIGN KEY (accounting_rejected_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_funds_by FOREIGN KEY (funds_received_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_rb_completed_by FOREIGN KEY (completed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_budget_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_budget_id INT UNSIGNED NOT NULL,
    item_name VARCHAR(200) NOT NULL,
    description VARCHAR(500) NULL,
    qty DECIMAL(15,2) NOT NULL DEFAULT 0,
    unit_name VARCHAR(50) NULL,
    estimated_unit_price DECIMAL(18,2) NOT NULL DEFAULT 0,
    estimated_total DECIMAL(18,2) NOT NULL DEFAULT 0,
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rbi_rb (request_budget_id),
    CONSTRAINT fk_rbi_rb FOREIGN KEY (request_budget_id) REFERENCES request_budgets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_budget_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_budget_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(40) NOT NULL,
    old_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_rbh_rb (request_budget_id),
    CONSTRAINT fk_rbh_rb FOREIGN KEY (request_budget_id) REFERENCES request_budgets (id) ON DELETE CASCADE,
    CONSTRAINT fk_rbh_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Counter nomor (RB-0001, RB-0002, ...) -- atomik lewat SELECT ... FOR UPDATE
-- (lihat RequestBudget::nextNumber()). Berkelanjutan (tidak reset per tahun).
INSERT INTO document_number_counters (doc_type, year, next_number)
SELECT 'request_budget', 0, 1 FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM document_number_counters WHERE doc_type = 'request_budget' AND year = 0);
