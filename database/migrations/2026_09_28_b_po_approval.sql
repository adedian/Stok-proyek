-- Alur approval PO: setelah disetujui, PO terkunci (tidak bisa diedit/dihapus
-- lagi kecuali oleh Super Admin) -- mengunci FIELD STATUS + data PO secara
-- keseluruhan, bukan cuma item barang (yang sudah lebih dulu dikunci oleh
-- hasReceipts() sejak ada penerimaan barang).
--
-- Pola sama persis dengan Validasi Kas (cash_transactions.validated_by/at +
-- CashController::assertValidationAllowsChange()): kolom pencatat siapa &
-- kapan approve, dan guard di controller yang membandingkan
-- currentUserRole() !== ROLE_SUPER_ADMIN.

ALTER TABLE purchase_orders
    ADD COLUMN approved_by INT UNSIGNED NULL AFTER status,
    ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
    ADD CONSTRAINT fk_po_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL;

-- Grandfather: PO yang statusnya sudah approved/partial_received/completed HARI
-- INI (sebelum fitur approval ada) dianggap sudah "disetujui" sejak update
-- terakhirnya -- supaya PO lama otomatis ikut terkunci (konsisten dgn aturan
-- baru), bukan malah jadi lubang bebas-edit selamanya.
UPDATE purchase_orders
   SET approved_at = updated_at,
       approved_by = created_by
 WHERE deleted_at IS NULL
   AND status IN ('approved', 'partial_received', 'completed');

-- Hak akses baru: hanya Super Admin default-nya (aman/konservatif -- bisa
-- diperluas ke Purchase/Accounting lewat Hak Akses kalau dibutuhkan).
INSERT IGNORE INTO role_permissions (role_slug, module, action, allowed) VALUES
    ('super_admin',   'purchase_order', 'approve', 1),
    ('purchase',      'purchase_order', 'approve', 0),
    ('accounting',    'purchase_order', 'approve', 0);
