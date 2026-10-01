-- ============================================================
-- Revisi Request Budget (2026-10-03)
-- ============================================================
-- Tahap Accounting (diproses / dana diterima / ditolak Accounting) DIHAPUS.
-- Alur baru: DRAFT -> PENDING_APPROVAL -> APPROVED (menunggu pengajuan Purchase)
--            -> FORWARDED (diteruskan Andy ke Purwati/Nissa) -> COMPLETED
--            PENDING_APPROVAL -> REJECTED -> (revisi) DRAFT
-- Approval boleh oleh Andy (Purchase) ATAU Vicky (PM); HANYA Andy yang meneruskan.
--
-- Request lama (kalau ada) dipetakan: tahap Accounting manapun -> FORWARDED,
-- ACCOUNTING_REJECTED -> APPROVED (kembali menunggu pengajuan Purchase).
-- ============================================================

UPDATE request_budgets SET status = 'FORWARDED'
 WHERE status IN ('SUBMITTED_ACCOUNTING', 'ACCOUNTING_PROCESS', 'FUNDS_RECEIVED');
UPDATE request_budgets SET status = 'APPROVED' WHERE status = 'ACCOUNTING_REJECTED';

ALTER TABLE request_budgets
    DROP FOREIGN KEY fk_rb_sub_acc_by,
    DROP FOREIGN KEY fk_rb_acc_proc_by,
    DROP FOREIGN KEY fk_rb_acc_rej_by,
    DROP FOREIGN KEY fk_rb_funds_by;

ALTER TABLE request_budgets
    DROP COLUMN accounting_processed_by,
    DROP COLUMN accounting_processed_at,
    DROP COLUMN accounting_rejected_by,
    DROP COLUMN accounting_rejected_at,
    DROP COLUMN accounting_rejection_reason,
    DROP COLUMN funds_received_by,
    DROP COLUMN funds_received_at,
    CHANGE COLUMN submitted_accounting_by forwarded_by INT UNSIGNED NULL,
    CHANGE COLUMN submitted_accounting_at forwarded_at DATETIME NULL,
    ADD COLUMN forwarded_to VARCHAR(100) NULL AFTER forwarded_at,
    ADD COLUMN approved_by_role VARCHAR(50) NULL AFTER approved_at,
    ADD COLUMN forwarded_by_role VARCHAR(50) NULL AFTER forwarded_to,
    ADD CONSTRAINT fk_rb_forwarded_by FOREIGN KEY (forwarded_by) REFERENCES users (id) ON DELETE SET NULL;

-- Snapshot role pelaku di tiap riwayat (audit trail: siapa + sebagai apa).
ALTER TABLE request_budget_history
    ADD COLUMN role_name VARCHAR(50) NULL AFTER user_id;
