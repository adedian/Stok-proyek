<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

class GoodsReceipt extends Model
{
    protected string $table = 'goods_receipts';
    protected bool $softDelete = true;

    /**
     * "001/LPB.HME/X/2026" -- lihat catatan lengkap di SalesInvoice::generateInvoiceNumber(),
     * pola & jaminan yang sama (atomic, reset per tahun).
     */
    public function generateReceiptNumber(?string $receiptDate = null): string
    {
        return (new DocumentNumber())->next('goods_receipt', 'prefix_gr', $receiptDate);
    }

    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT gr.*,
                       COALESCE(po.po_number, op.purchase_number, '-') AS po_number,
                       po.pembuat_po,
                       COALESCE(po.project_id, op.project_id, gr.project_id) AS effective_project_id,
                       COALESCE(s.supplier_name, op.supplier_name, gr.source_detail, 'Pemakai/Internal') AS supplier_name,
                       COALESCE(p.project_name, p3.project_name, p2.project_name) AS project_name,
                       COALESCE(gr.receiver_name, u.full_name) AS received_by_name
                FROM goods_receipts gr
                LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
                LEFT JOIN offline_purchases op ON op.id = gr.offline_purchase_id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN projects p ON p.id = po.project_id
                LEFT JOIN projects p3 ON p3.id = op.project_id
                LEFT JOIN projects p2 ON p2.id = gr.project_id
                LEFT JOIN users u ON u.id = gr.received_by
                WHERE gr.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['po_id'])) {
            $sql .= " AND gr.purchase_order_id = :po_id";
            $params['po_id'] = $filters['po_id'];
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND COALESCE(po.project_id, gr.project_id) = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['receipt_type'])) {
            $sql .= " AND gr.receipt_type = :receipt_type";
            $params['receipt_type'] = $filters['receipt_type'];
        }
        if (!empty($filters['stock_scope'])) {
            $sql .= " AND gr.stock_scope = :stock_scope";
            $params['stock_scope'] = $filters['stock_scope'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (gr.receipt_number LIKE :kw1 OR po.po_number LIKE :kw2 OR s.supplier_name LIKE :kw3)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND gr.receipt_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND gr.receipt_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY gr.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT gr.*,
                       COALESCE(po.po_number, op.purchase_number, '-') AS po_number,
                       po.pembuat_po,
                       COALESCE(po.project_id, op.project_id, gr.project_id) AS effective_project_id,
                       COALESCE(s.supplier_name, op.supplier_name, gr.source_detail, 'Pemakai/Internal') AS supplier_name,
                       COALESCE(p.project_name, p3.project_name, p2.project_name) AS project_name,
                       COALESCE(gr.receiver_name, u.full_name) AS received_by_name
                FROM goods_receipts gr
                LEFT JOIN purchase_orders po ON po.id = gr.purchase_order_id
                LEFT JOIN offline_purchases op ON op.id = gr.offline_purchase_id
                LEFT JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN projects p ON p.id = po.project_id
                LEFT JOIN projects p3 ON p3.id = op.project_id
                LEFT JOIN projects p2 ON p2.id = gr.project_id
                LEFT JOIN users u ON u.id = gr.received_by
                WHERE gr.id = :id AND gr.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Daftar penerimaan barang yang bersumber dari satu Pembelian Offline tertentu
     * -- dipakai di halaman Detail Pembelian Offline untuk menampilkan hubungan
     * Offline Purchase -> Goods Receipt (requirement #5).
     */
    public function byOfflinePurchase(int $offlinePurchaseId): array
    {
        $sql = "SELECT gr.*, u.full_name AS received_by_name
                FROM goods_receipts gr
                LEFT JOIN users u ON u.id = gr.received_by
                WHERE gr.offline_purchase_id = :id AND gr.deleted_at IS NULL
                ORDER BY gr.created_at DESC";
        return $this->db->fetchAll($sql, ['id' => $offlinePurchaseId]);
    }

}
