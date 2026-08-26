<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

class OfflinePurchase extends Model
{
    protected string $table = 'offline_purchases';
    protected bool $softDelete = true;

    public array $statusLabels = [
        'belum_diterima'    => 'Belum Diterima',
        'menunggu_validasi' => 'Menunggu Validasi',
        'diterima_sebagian' => 'Diterima Sebagian',
        'selesai'           => 'Selesai',
    ];

    public array $statusBadgeClass = [
        'belum_diterima'    => 'secondary',
        'menunggu_validasi' => 'warning',
        'diterima_sebagian' => 'info',
        'selesai'           => 'success',
    ];

    /**
     * "001/OFF.HME/X/2026" -- lihat catatan lengkap di SalesInvoice::generateInvoiceNumber(),
     * pola & jaminan yang sama (atomic, reset per tahun).
     */
    public function generatePurchaseNumber(?string $purchaseDate = null): string
    {
        return (new DocumentNumber())->next('offline_purchase', 'prefix_off', $purchaseDate);
    }

    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT op.*, p.project_name,
                       (SELECT COUNT(*) FROM offline_purchase_items opi WHERE opi.offline_purchase_id = op.id) AS item_count
                FROM offline_purchases op
                JOIN projects p ON p.id = op.project_id
                WHERE op.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND op.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (op.purchase_number LIKE :kw1 OR op.supplier_name LIKE :kw2
                           OR EXISTS (SELECT 1 FROM offline_purchase_items opi2 WHERE opi2.offline_purchase_id = op.id AND opi2.item_name LIKE :kw3))";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
        }
        if (!empty($filters['status'])) {
            $sql .= " AND op.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND op.purchase_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND op.purchase_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY op.purchase_date DESC, op.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT op.*, p.project_name
                FROM offline_purchases op
                JOIN projects p ON p.id = op.project_id
                WHERE op.id = :id AND op.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Pembelian Offline yang boleh dibuatkan penerimaan barang: masih ada sisa
     * qty yang belum diterima sepenuhnya (dipakai untuk dropdown di modul
     * Penerimaan Barang, meniru PurchaseOrder::receivablePoList()).
     */
    public function receivableList(): array
    {
        $sql = "SELECT op.id, op.purchase_number, op.supplier_name
                FROM offline_purchases op
                WHERE op.deleted_at IS NULL
                  AND op.status IN ('belum_diterima', 'menunggu_validasi', 'diterima_sebagian')
                ORDER BY op.created_at DESC";
        return $this->db->fetchAll($sql);
    }

    public function recalculateTotal(int $offlinePurchaseId): void
    {
        $sql = "SELECT COALESCE(SUM(subtotal), 0) AS total
                FROM offline_purchase_items WHERE offline_purchase_id = :id";
        $total = $this->db->fetchOne($sql, ['id' => $offlinePurchaseId])['total'];

        $this->updateById($offlinePurchaseId, ['total_amount' => $total]);
    }

    /**
     * Evaluasi ulang status Pembelian Offline berdasarkan qty item yang SUDAH
     * divalidasi & benar-benar masuk stok (stock_posted_at terisi) vs qty
     * dibeli -- meniru persis PurchaseOrder::refreshReceiptStatus().
     * - Semua item qty diterima(valid) >= qty dibeli -> selesai
     * - Sebagian item sudah ada penerimaan tervalidasi -> diterima_sebagian
     * - Sudah ada penerimaan tapi belum ada yang tervalidasi -> menunggu_validasi
     * - Belum ada penerimaan sama sekali -> belum_diterima
     */
    public function refreshReceiptStatus(int $offlinePurchaseId): void
    {
        $current = $this->find($offlinePurchaseId);
        if (!$current) {
            return;
        }

        $sql = "SELECT opi.qty, COALESCE(SUM(CASE WHEN gri.stock_posted_at IS NOT NULL THEN gri.qty_received ELSE 0 END), 0) AS qty_posted
                FROM offline_purchase_items opi
                LEFT JOIN goods_receipt_items gri ON gri.offline_purchase_item_id = opi.id
                LEFT JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
                WHERE opi.offline_purchase_id = :id
                GROUP BY opi.id, opi.qty";
        $rows = $this->db->fetchAll($sql, ['id' => $offlinePurchaseId]);

        $hasAnyReceipt = (bool) $this->db->fetchOne(
            "SELECT 1 FROM goods_receipts WHERE offline_purchase_id = :id AND deleted_at IS NULL LIMIT 1",
            ['id' => $offlinePurchaseId]
        );

        $totalItems = count($rows);
        $completedItems = 0;
        $anyPosted = false;

        foreach ($rows as $row) {
            if ((float) $row['qty_posted'] > 0) {
                $anyPosted = true;
            }
            if ((float) $row['qty_posted'] >= (float) $row['qty']) {
                $completedItems++;
            }
        }

        if ($totalItems > 0 && $completedItems === $totalItems) {
            $newStatus = 'selesai';
        } elseif ($anyPosted) {
            $newStatus = 'diterima_sebagian';
        } elseif ($hasAnyReceipt) {
            $newStatus = 'menunggu_validasi';
        } else {
            $newStatus = 'belum_diterima';
        }

        if ($newStatus !== $current['status']) {
            $this->updateById($offlinePurchaseId, ['status' => $newStatus]);
        }
    }
}
