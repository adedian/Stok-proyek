<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

class StockOut extends Model
{
    protected string $table = 'stock_out';
    protected bool $softDelete = true;

    /**
     * "001/STO.HME/X/2026" -- lihat catatan lengkap di SalesInvoice::generateInvoiceNumber(),
     * pola & jaminan yang sama (atomic, reset per tahun).
     */
    public function generateStockOutNumber(?string $outDate = null): string
    {
        return (new DocumentNumber())->next('stock_out', 'prefix_sto', $outDate);
    }

    public function listWithRelations(array $filters = []): array
    {
        // LEFT JOIN projects (bukan INNER) -- so.project_id NULL untuk pengeluaran
        // tujuan Client (destination_type='client'), lihat sales_invoice_id/clients.
        $sql = "SELECT so.*, inv.item_name, inv.unit, p.project_name, dn.delivery_number,
                       si.invoice_number, COALESCE(c.client_name, soc.client_name) AS client_name,
                       sord.order_number AS sales_order_number, sord.po_number AS sales_order_po_number,
                       swh.warehouse_name AS source_warehouse_name, dwh.warehouse_name AS dest_warehouse_name
                FROM stock_out so
                JOIN inventory inv ON inv.id = so.inventory_id
                LEFT JOIN warehouses swh ON swh.id = so.source_warehouse_id
                LEFT JOIN warehouses dwh ON dwh.id = so.dest_warehouse_id
                LEFT JOIN projects p ON p.id = so.project_id
                LEFT JOIN sales_invoices si ON si.id = so.sales_invoice_id
                LEFT JOIN clients c ON c.id = si.client_id
                LEFT JOIN sales_orders sord ON sord.id = so.sales_order_id
                LEFT JOIN clients soc ON soc.id = sord.client_id
                LEFT JOIN delivery_notes dn ON dn.id = so.delivery_note_id AND dn.deleted_at IS NULL
                WHERE so.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND so.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['warehouse_id'])) {
            // Gudang asal ATAU gudang tujuan (riwayat mutasi + pengeluaran dari gudang tsb).
            $sql .= " AND (so.source_warehouse_id = :wh_src OR so.dest_warehouse_id = :wh_dst)";
            $params['wh_src'] = (int) $filters['warehouse_id'];
            $params['wh_dst'] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['destination_type']) && in_array($filters['destination_type'], ['project', 'client', 'gudang'], true)) {
            $sql .= " AND so.destination_type = :dest_type";
            $params['dest_type'] = $filters['destination_type'];
        }
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['inv.item_name', 'so.destination', 'so.pic_name'],
                ['so.stock_out_number'],
                'sokw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND so.out_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND so.out_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY so.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Semua baris (barang) milik SATU dokumen pengeluaran -- diidentifikasi lewat document_id
     * (id baris kepala), BUKAN lewat nomor dokumen. Baris lama tanpa document_id dianggap
     * dokumen sendiri (COALESCE). Hanya baris yang belum dihapus.
     */
    public function documentRows(int $documentId): array
    {
        return $this->db->fetchAll(
            "SELECT so.*, inv.item_name, inv.unit, inv.qty_available, inv.project_id AS inv_project_id,
                    inv.stock_scope AS inv_stock_scope, inv.stock_type AS inv_stock_type, inv.warehouse_id AS inv_warehouse_id,
                    swh.warehouse_name AS source_warehouse_name, dwh.warehouse_name AS dest_warehouse_name
               FROM stock_out so
               JOIN inventory inv ON inv.id = so.inventory_id
               LEFT JOIN warehouses swh ON swh.id = so.source_warehouse_id
               LEFT JOIN warehouses dwh ON dwh.id = so.dest_warehouse_id
              WHERE COALESCE(so.document_id, so.id) = :d AND so.deleted_at IS NULL
              ORDER BY so.id ASC",
            ['d' => $documentId]
        );
    }

    /** Label status mutasi antar gudang (stock_out.transfer_status). */
    public static function transferStatusLabel(?string $status): string
    {
        return [
            'dalam_perjalanan' => 'Dalam Perjalanan',
            'diterima'         => 'Diterima',
            'dibatalkan'       => 'Dibatalkan',
        ][(string) $status] ?? '-';
    }

    /** Baris dokumen yang dikunci (FOR UPDATE) -- dipakai Terima/Batalkan mutasi agar tidak diproses dobel. */
    public function documentRowsForUpdate(int $documentId): array
    {
        return $this->db->fetchAll(
            "SELECT so.*, inv.item_name, inv.unit, inv.project_id AS inv_project_id,
                    inv.stock_scope AS inv_stock_scope, inv.stock_type AS inv_stock_type
               FROM stock_out so
               JOIN inventory inv ON inv.id = so.inventory_id
              WHERE COALESCE(so.document_id, so.id) = :d AND so.deleted_at IS NULL
              ORDER BY so.id ASC
              FOR UPDATE",
            ['d' => $documentId]
        );
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT so.*, inv.item_name, inv.unit, inv.qty_available, p.project_name,
                       si.invoice_number, c.client_name,
                       swh.warehouse_name AS source_warehouse_name, dwh.warehouse_name AS dest_warehouse_name
                FROM stock_out so
                JOIN inventory inv ON inv.id = so.inventory_id
                LEFT JOIN warehouses swh ON swh.id = so.source_warehouse_id
                LEFT JOIN warehouses dwh ON dwh.id = so.dest_warehouse_id
                LEFT JOIN projects p ON p.id = so.project_id
                LEFT JOIN sales_invoices si ON si.id = so.sales_invoice_id
                LEFT JOIN clients c ON c.id = si.client_id
                WHERE so.id = :id AND so.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Lepaskan semua baris stock_out yang terpasang ke satu Surat Jalan --
     * dipakai saat Surat Jalan dihapus (DeliveryNoteController::delete()).
     * Baris pengeluaran barangnya sendiri TIDAK ikut dihapus/diubah, cuma
     * dilepas supaya bisa dikelompokkan ulang ke Surat Jalan lain.
     */
    public function unlinkDeliveryNote(int $deliveryNoteId): void
    {
        $this->db->query(
            "UPDATE stock_out SET delivery_note_id = NULL WHERE delivery_note_id = :id",
            ['id' => $deliveryNoteId]
        );
    }

    /**
     * Pasang baris ke Surat Jalan HANYA kalau belum terpasang ke Surat Jalan manapun (atomik).
     * false = sudah diambil Surat Jalan lain (mis. dua pengguna membuat SJ bersamaan).
     */
    public function claimForDeliveryNote(int $stockOutId, int $deliveryNoteId): bool
    {
        $stmt = $this->db->query(
            "UPDATE stock_out SET delivery_note_id = :dn
              WHERE id = :id AND delivery_note_id IS NULL AND deleted_at IS NULL",
            ['dn' => $deliveryNoteId, 'id' => $stockOutId]
        );
        return $stmt->rowCount() === 1;
    }

    // ===================== Validasi Pengeluaran Barang =====================

    /**
     * Aturan tunggal: pengeluaran barang WAJIB divalidasi hanya kalau punya
     * project (project_id valid). Tanpa project (mis. tujuan Client) tidak perlu.
     * Murni data-driven -- tidak bergantung nama project/kategori.
     */
    public static function requiresValidation($projectId): bool
    {
        return (int) $projectId > 0;
    }

    /** Status validasi awal saat pengeluaran dibuat / project-nya berubah. */
    public static function initialValidationStatus($projectId): string
    {
        return self::requiresValidation($projectId) ? 'menunggu' : 'tidak_perlu';
    }

    /**
     * Daftar stock_out untuk halaman Validasi Pengeluaran Barang.
     * $status: 'menunggu' | 'tervalidasi' | 'ditolak' | '' (semua).
     * SELALU hanya pengeluaran project (project_id NOT NULL) -- pengeluaran
     * non-project tidak pernah muncul di sini.
     */
    public function listForValidation(string $status = 'menunggu', array $filters = []): array
    {
        $sql = "SELECT so.*, inv.item_name, inv.unit, p.project_name,
                       si.invoice_number, c.client_name,
                       cb.full_name AS created_by_name, vb.full_name AS validated_by_name
                FROM stock_out so
                JOIN inventory inv ON inv.id = so.inventory_id
                LEFT JOIN projects p ON p.id = so.project_id
                LEFT JOIN sales_invoices si ON si.id = so.sales_invoice_id
                LEFT JOIN clients c ON c.id = si.client_id
                LEFT JOIN users cb ON cb.id = so.created_by
                LEFT JOIN users vb ON vb.id = so.validated_by
                WHERE so.deleted_at IS NULL AND so.project_id IS NOT NULL";
        $params = [];

        if (in_array($status, ['menunggu', 'tervalidasi', 'ditolak'], true)) {
            $sql .= " AND so.validation_status = :st";
            $params['st'] = $status;
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND so.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['inv.item_name', 'so.destination', 'so.pic_name'],
                ['so.stock_out_number'],
                'sovkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND so.out_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND so.out_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY so.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function countPendingValidation(): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM stock_out
              WHERE deleted_at IS NULL AND project_id IS NOT NULL AND validation_status = 'menunggu'"
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Set hasil validasi. $status = 'tervalidasi' | 'ditolak'.
     * ATOMIK & idempotent: UPDATE bersyarat (masih 'menunggu', punya project,
     * belum dihapus) -- dua request bersamaan/berulang hanya 1 yang berhasil.
     * Mengembalikan true kalau baris benar-benar berubah. Tidak menyentuh stok.
     */
    public function setValidation(int $id, string $status, int $userId, ?string $note): bool
    {
        $stmt = $this->db->query(
            "UPDATE stock_out
                SET validation_status = :st, validated_by = :uid, validated_at = NOW(),
                    validation_note = :note, updated_at = NOW()
              WHERE id = :id
                AND deleted_at IS NULL
                AND project_id IS NOT NULL
                AND validation_status = 'menunggu'",
            ['st' => $status, 'uid' => $userId, 'note' => ($note !== '' ? $note : null), 'id' => $id]
        );
        return $stmt->rowCount() === 1;
    }
}
