<?php
require_once ROOT_PATH . '/core/Model.php';

class GoodsReceiptItem extends Model
{
    protected string $table = 'goods_receipt_items';

    public array $statusLabels = [
        'sesuai'      => 'Sesuai',
        'kurang'      => 'Kurang',
        'lebih'       => 'Lebih',
        'barang_lain' => 'Barang Lain',
    ];

    public array $statusBadgeClass = [
        'sesuai'      => 'success',
        'kurang'      => 'warning',
        'lebih'       => 'danger',
        'barang_lain' => 'dark',
    ];

    /**
     * Ambil semua item PO untuk sebuah PO, dilengkapi info:
     * - qty_order (dari purchase_order_items)
     * - qty_received_before (total sudah diterima di receipt2 SEBELUMNYA, exclude $excludeReceiptId)
     * - qty_remaining (sisa yang belum diterima)
     * Dipakai untuk menyusun form input qty penerimaan.
     */
    public function poItemsForReceipt(int $poId, ?int $excludeReceiptId = null): array
    {
        $sql = "SELECT poi.id AS po_item_id, poi.item_name, poi.unit, poi.qty_order,
                       COALESCE(SUM(CASE WHEN gr.id IS NOT NULL THEN gri.qty_received ELSE 0 END), 0) AS qty_received_before
                FROM purchase_order_items poi
                LEFT JOIN goods_receipt_items gri ON gri.purchase_order_item_id = poi.id
                LEFT JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                    AND gr.deleted_at IS NULL"
                    . ($excludeReceiptId ? " AND gr.id != :exclude_id" : "") . "
                WHERE poi.purchase_order_id = :po_id
                GROUP BY poi.id, poi.item_name, poi.unit, poi.qty_order
                ORDER BY poi.id ASC";

        $params = ['po_id' => $poId];
        if ($excludeReceiptId) {
            $params['exclude_id'] = $excludeReceiptId;
        }

        $rows = $this->db->fetchAll($sql, $params);

        foreach ($rows as &$row) {
            $row['qty_order'] = (float) $row['qty_order'];
            $row['qty_received_before'] = (float) $row['qty_received_before'];
            $row['qty_remaining'] = max(0, $row['qty_order'] - $row['qty_received_before']);
        }

        return $rows;
    }

    /**
     * Ambil semua item Pembelian Offline untuk satu offline_purchase_id, dilengkapi info:
     * - qty_order (dari offline_purchase_items.qty)
     * - qty_received_before (total sudah diterima di receipt2 SEBELUMNYA, exclude $excludeReceiptId)
     * - qty_remaining (sisa yang belum diterima)
     * Meniru persis poItemsForReceipt() -- dipakai untuk menyusun form input qty penerimaan
     * saat Sumber Penerimaan = "Pembelian Offline".
     */
    public function offlineItemsForReceipt(int $offlinePurchaseId, ?int $excludeReceiptId = null): array
    {
        $sql = "SELECT opi.id AS offline_item_id, opi.item_name, opi.unit, opi.qty AS qty_order,
                       COALESCE(SUM(CASE WHEN gr.id IS NOT NULL THEN gri.qty_received ELSE 0 END), 0) AS qty_received_before
                FROM offline_purchase_items opi
                LEFT JOIN goods_receipt_items gri ON gri.offline_purchase_item_id = opi.id
                LEFT JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                    AND gr.deleted_at IS NULL"
                    . ($excludeReceiptId ? " AND gr.id != :exclude_id" : "") . "
                WHERE opi.offline_purchase_id = :offline_purchase_id
                GROUP BY opi.id, opi.item_name, opi.unit, opi.qty
                ORDER BY opi.id ASC";

        $params = ['offline_purchase_id' => $offlinePurchaseId];
        if ($excludeReceiptId) {
            $params['exclude_id'] = $excludeReceiptId;
        }

        $rows = $this->db->fetchAll($sql, $params);

        foreach ($rows as &$row) {
            $row['qty_order'] = (float) $row['qty_order'];
            $row['qty_received_before'] = (float) $row['qty_received_before'];
            $row['qty_remaining'] = max(0, $row['qty_order'] - $row['qty_received_before']);
        }

        return $rows;
    }

    /**
     * Item BELI BARANG dari satu transaksi Kas (baris kategori ber-affects_stock,
     * tertaut master Barang) untuk form Penerimaan Barang -- cerminan
     * offlineItemsForReceipt(). qty_order = cash_transaction_items.qty.
     */
    public function cashItemsForReceipt(int $cashTransactionId, ?int $excludeReceiptId = null): array
    {
        $sql = "SELECT cti.id AS cash_item_id, it.item_name, cti.unit, cti.qty AS qty_order,
                       COALESCE(SUM(CASE WHEN gr.id IS NOT NULL THEN gri.qty_received ELSE 0 END), 0) AS qty_received_before
                FROM cash_transaction_items cti
                JOIN cash_categories cc ON cc.id = cti.cash_category_id AND cc.affects_stock = 1
                JOIN items it ON it.id = cti.item_id
                LEFT JOIN goods_receipt_items gri ON gri.cash_transaction_item_id = cti.id
                LEFT JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                    AND gr.deleted_at IS NULL"
                    . ($excludeReceiptId ? " AND gr.id != :exclude_id" : "") . "
                WHERE cti.cash_transaction_id = :cash_transaction_id
                GROUP BY cti.id, it.item_name, cti.unit, cti.qty
                ORDER BY cti.id ASC";

        $params = ['cash_transaction_id' => $cashTransactionId];
        if ($excludeReceiptId) {
            $params['exclude_id'] = $excludeReceiptId;
        }

        $rows = $this->db->fetchAll($sql, $params);

        foreach ($rows as &$row) {
            $row['qty_order'] = (float) $row['qty_order'];
            $row['qty_received_before'] = (float) $row['qty_received_before'];
            $row['qty_remaining'] = max(0, $row['qty_order'] - $row['qty_received_before']);
        }

        return $rows;
    }

    /**
     * Tandai delta stok yang sudah diterapkan Validasi untuk item Kas (idempoten).
     */
    public function markStockDeltaApplied(int $id, float $delta): void
    {
        $this->db->query(
            "UPDATE goods_receipt_items SET stock_delta_applied = :d, stock_posted_at = NOW() WHERE id = :id",
            ['d' => $delta, 'id' => $id]
        );
    }

    /**
     * Tentukan status perbandingan qty untuk satu item, berdasarkan TOTAL kumulatif
     * (qty diterima sebelumnya + qty diterima di penerimaan ini) dibanding qty_order.
     */
    public function resolveComparisonStatus(float $qtyOrder, float $totalReceivedAfter): string
    {
        if ($totalReceivedAfter == $qtyOrder) {
            return 'sesuai';
        }
        return $totalReceivedAfter < $qtyOrder ? 'kurang' : 'lebih';
    }

    public function itemsByReceipt(int $receiptId): array
    {
        $sql = "SELECT gri.*,
                       COALESCE(gri.actual_item_name, poi.item_name, opi.item_name, it.item_name) AS item_name,
                       COALESCE(gri.actual_unit, poi.unit, opi.unit, cti.unit) AS unit,
                       COALESCE(poi.item_name, opi.item_name, it.item_name) AS po_item_name,
                       COALESCE(poi.unit, opi.unit, cti.unit) AS po_unit,
                       COALESCE(poi.qty_order, opi.qty, cti.qty) AS qty_order,
                       cti.qty AS cash_qty, cti.project_id AS cash_project_id, cc.stock_scope AS cash_stock_scope
                FROM goods_receipt_items gri
                LEFT JOIN purchase_order_items poi ON poi.id = gri.purchase_order_item_id
                LEFT JOIN offline_purchase_items opi ON opi.id = gri.offline_purchase_item_id
                LEFT JOIN cash_transaction_items cti ON cti.id = gri.cash_transaction_item_id
                LEFT JOIN items it ON it.id = cti.item_id
                LEFT JOIN cash_categories cc ON cc.id = cti.cash_category_id
                WHERE gri.goods_receipt_id = :receipt_id
                ORDER BY gri.id ASC";
        return $this->db->fetchAll($sql, ['receipt_id' => $receiptId]);
    }

    public function deleteByReceipt(int $receiptId): int
    {
        return $this->db->query(
            "DELETE FROM goods_receipt_items WHERE goods_receipt_id = :receipt_id",
            ['receipt_id' => $receiptId]
        )->rowCount();
    }

    /**
     * List semua item penerimaan barang + relasi PO/supplier/project, untuk halaman Validasi.
     * Filter opsional: validated (true/false/null=semua), status (sesuai/kurang/lebih/barang_lain)
     */
    public function listForValidation(array $filters = []): array
    {
        $sql = "SELECT gri.*,
                       COALESCE(gri.actual_item_name, poi.item_name, opi.item_name, it.item_name) AS item_name,
                       COALESCE(gri.actual_unit, poi.unit, opi.unit, cti.unit) AS unit,
                       COALESCE(poi.qty_order, opi.qty, cti.qty) AS qty_order,
                       gr.receipt_number, gr.receipt_date, gr.purchase_order_id, gr.offline_purchase_id,
                       gr.cash_transaction_id, cti.qty AS cash_qty,
                       gr.receipt_type, gr.stock_scope,
                       COALESCE(po.po_number, op.purchase_number, ct.no_bukti, '-') AS po_number,
                       po.pembuat_po,
                       COALESCE(s.supplier_name, op.supplier_name, cti.supplier_name, gr.source_detail, 'Pemakai/Internal') AS supplier_name,
                       COALESCE(p.project_name, p2.project_name, p3.project_name, '-') AS project_name,
                       v.full_name AS validated_by_name
                FROM goods_receipt_items gri
                LEFT JOIN purchase_order_items poi ON poi.id = gri.purchase_order_item_id
                LEFT JOIN offline_purchase_items opi ON opi.id = gri.offline_purchase_item_id
                LEFT JOIN cash_transaction_items cti ON cti.id = gri.cash_transaction_item_id
                LEFT JOIN items it ON it.id = cti.item_id
                JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
                LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
                LEFT JOIN offline_purchases op ON op.id = gr.offline_purchase_id
                LEFT JOIN cash_transactions ct ON ct.id = gr.cash_transaction_id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN projects p ON p.id = COALESCE(po.project_id, gr.project_id)
                LEFT JOIN projects p2 ON p2.id = op.project_id
                LEFT JOIN projects p3 ON p3.id = cti.project_id
                LEFT JOIN users v ON v.id = gri.validated_by
                WHERE 1=1";
        $params = [];

        if (isset($filters['validated']) && $filters['validated'] === 'yes') {
            $sql .= " AND gri.validated_at IS NOT NULL";
        } elseif (isset($filters['validated']) && $filters['validated'] === 'no') {
            $sql .= " AND gri.validated_at IS NULL";
        }
        if (!empty($filters['problem'])) {
            $sql .= " AND (gri.validated_at IS NULL OR gri.comparison_status != 'sesuai')";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND gri.comparison_status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (gr.receipt_number LIKE :kw1 OR po.po_number LIKE :kw2
                           OR poi.item_name LIKE :kw3 OR gri.actual_item_name LIKE :kw4)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
            $params['kw4'] = $kw;
        }

        $sql .= " ORDER BY gr.receipt_date DESC, gri.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Laporan selisih: hanya item dengan status BUKAN 'sesuai' (kurang/lebih/barang_lain)
     */
    public function selisihReport(array $filters = []): array
    {
        $sql = "SELECT gri.*,
                       COALESCE(gri.actual_item_name, poi.item_name, opi.item_name) AS item_name,
                       COALESCE(gri.actual_unit, poi.unit, opi.unit) AS unit,
                       COALESCE(poi.qty_order, opi.qty) AS qty_order,
                       gr.receipt_number, gr.receipt_date, gr.purchase_order_id, gr.offline_purchase_id,
                       COALESCE(po.po_number, op.purchase_number, '-') AS po_number,
                       po.pembuat_po,
                       COALESCE(po.project_id, op.project_id, gr.project_id) AS project_id,
                       COALESCE(s.supplier_name, op.supplier_name, gr.source_detail) AS supplier_name,
                       COALESCE(p.project_name, p2.project_name) AS project_name
                FROM goods_receipt_items gri
                LEFT JOIN purchase_order_items poi ON poi.id = gri.purchase_order_item_id
                LEFT JOIN offline_purchase_items opi ON opi.id = gri.offline_purchase_item_id
                JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
                LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
                LEFT JOIN offline_purchases op ON op.id = gr.offline_purchase_id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN projects p ON p.id = po.project_id
                LEFT JOIN projects p2 ON p2.id = op.project_id
                WHERE gri.comparison_status != 'sesuai'";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND COALESCE(po.project_id, op.project_id, gr.project_id) = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND gr.receipt_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND gr.receipt_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY gr.receipt_date DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Jumlah item yang punya selisih & belum divalidasi -- dipakai untuk badge notifikasi
     */
    public function countPendingSelisih(): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM goods_receipt_items gri
             JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
             WHERE gri.comparison_status != 'sesuai' AND gri.validated_at IS NULL"
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Baris per-item untuk Laporan Penerimaan Barang: No PO, Supplier, Tgl, status
     * "Diterima" (hasil validasi aktual -- bukan asumsi), Nama Barang, Qty. Barang
     * yang diterima berbeda dari PO (is_item_mismatch) ikut ditandai di sini.
     */
    public function reportRows(array $filters = []): array
    {
        $sql = "SELECT gri.id, gr.receipt_number, gr.receipt_date, gr.receipt_type, gr.stock_scope,
                       COALESCE(po.po_number, op.purchase_number, '-') AS po_number,
                       po.pembuat_po,
                       COALESCE(s.supplier_name, op.supplier_name, gr.source_detail, 'Pemakai/Internal') AS supplier_name,
                       COALESCE(gri.actual_item_name, poi.item_name, opi.item_name) AS item_name,
                       COALESCE(gri.actual_unit, poi.unit, opi.unit) AS unit,
                       gri.qty_received, gri.comparison_status, gri.is_item_mismatch,
                       gri.validated_at, gri.stock_posted_at,
                       COALESCE(po.project_id, op.project_id, gr.project_id) AS project_id
                FROM goods_receipt_items gri
                LEFT JOIN purchase_order_items poi ON poi.id = gri.purchase_order_item_id
                LEFT JOIN offline_purchase_items opi ON opi.id = gri.offline_purchase_item_id
                JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
                LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
                LEFT JOIN offline_purchases op ON op.id = gr.offline_purchase_id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND COALESCE(po.project_id, op.project_id, gr.project_id) = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (gr.receipt_number LIKE :kw1 OR po.po_number LIKE :kw2 OR poi.item_name LIKE :kw3 OR gri.actual_item_name LIKE :kw4)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
            $params['kw4'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND gr.receipt_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND gr.receipt_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY gr.receipt_date DESC, gri.id DESC";

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$row) {
            if (empty($row['validated_at'])) {
                $row['diterima_label'] = 'Belum Divalidasi';
            } else {
                $row['diterima_label'] = $this->statusLabels[$row['comparison_status']] ?? $row['comparison_status'];
            }
        }
        return $rows;
    }

    /**
     * Jumlah item yang "bermasalah" secara luas: belum divalidasi ATAU statusnya
     * bukan 'sesuai' -- dipakai untuk section "Validasi Belum Sesuai" (lihat
     * requirement: item yang belum selesai/belum sesuai tidak boleh hilang dari list).
     */
    public function countProblem(): int
    {
        $result = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM goods_receipt_items gri
             JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
             WHERE gri.validated_at IS NULL OR gri.comparison_status != 'sesuai'"
        );
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Simpan hasil validasi manual untuk satu item penerimaan.
     * Validator boleh mengoreksi status otomatis (misalnya jadi 'barang_lain')
     * dan wajib mengisi catatan kalau statusnya bukan 'sesuai'.
     */
    public function validateItem(int $id, string $status, string $notes, int $userId): void
    {
        $this->updateById($id, [
            'comparison_status' => $status,
            'validation_notes'  => $notes,
            'validated_by'      => $userId,
            'validated_at'      => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Tandai/batalkan penanda "sudah dikreditkan ke stok" untuk satu item -- dipakai
     * oleh ValidationController supaya kredit/reverse stok idempotent (tidak dobel).
     */
    public function markStockPosted(int $id, bool $posted): void
    {
        $this->updateById($id, ['stock_posted_at' => $posted ? date('Y-m-d H:i:s') : null]);
    }

    /**
     * Ambil satu item penerimaan lengkap dengan semua info yang dibutuhkan untuk
     * memutuskan kredit/reverse stok: nama & satuan barang AKTUAL (bukan barang PO
     * kalau ada override), project & stock_scope (dari goods_receipts, atau dari PO
     * kalau receipt_type = 'purchase_order'), dan status posting stok saat ini.
     */
    public function findFullById(int $id)
    {
        $sql = "SELECT gri.*,
                       COALESCE(gri.actual_item_name, poi.item_name, opi.item_name, it.item_name) AS item_name,
                       COALESCE(gri.actual_unit, poi.unit, opi.unit, cti.unit) AS unit,
                       gr.receipt_number, gr.receipt_type, gr.receipt_date,
                       COALESCE(cc.stock_scope, gr.stock_scope) AS stock_scope,
                       COALESCE(gr.project_id, po.project_id, op.project_id, cti.project_id) AS project_id,
                       gr.purchase_order_id, po.po_number,
                       gr.offline_purchase_id, op.purchase_number,
                       gr.cash_transaction_id, ct.no_bukti AS cash_no_bukti,
                       cti.qty AS cash_qty
                FROM goods_receipt_items gri
                LEFT JOIN purchase_order_items poi ON poi.id = gri.purchase_order_item_id
                LEFT JOIN offline_purchase_items opi ON opi.id = gri.offline_purchase_item_id
                LEFT JOIN cash_transaction_items cti ON cti.id = gri.cash_transaction_item_id
                LEFT JOIN items it ON it.id = cti.item_id
                LEFT JOIN cash_categories cc ON cc.id = cti.cash_category_id
                JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id
                LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
                LEFT JOIN offline_purchases op ON op.id = gr.offline_purchase_id
                LEFT JOIN cash_transactions ct ON ct.id = gr.cash_transaction_id
                WHERE gri.id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
