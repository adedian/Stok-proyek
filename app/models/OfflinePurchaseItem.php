<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Item/barang dalam satu Pembelian Offline (header+detail, meniru persis
 * pola PurchaseOrderItem -- lihat OfflinePurchaseController).
 */
class OfflinePurchaseItem extends Model
{
    protected string $table = 'offline_purchase_items';

    public function itemsByPurchase(int $offlinePurchaseId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM offline_purchase_items WHERE offline_purchase_id = :id ORDER BY id ASC",
            ['id' => $offlinePurchaseId]
        );
    }

    public function deleteByPurchase(int $offlinePurchaseId): int
    {
        return $this->db->query(
            "DELETE FROM offline_purchase_items WHERE offline_purchase_id = :id",
            ['id' => $offlinePurchaseId]
        )->rowCount();
    }

    /**
     * True kalau minimal satu item Pembelian Offline ini sudah punya penerimaan
     * barang (goods_receipt_items). Item seperti ini TIDAK BOLEH dihapus/diganti --
     * FK fk_gri_opi akan menolaknya -- dipakai untuk mengunci edit item saat
     * Pembelian Offline ini sudah pernah diterima barangnya.
     */
    public function hasReceipts(int $offlinePurchaseId): bool
    {
        $result = $this->db->fetchOne(
            "SELECT 1 FROM offline_purchase_items opi
             JOIN goods_receipt_items gri ON gri.offline_purchase_item_id = opi.id
             WHERE opi.offline_purchase_id = :id
             LIMIT 1",
            ['id' => $offlinePurchaseId]
        );
        return (bool) $result;
    }

    /**
     * Baris per-item untuk Laporan Pembelian Offline -- header offline_purchases sudah
     * tidak menyimpan barang/qty/harga langsung sejak dikonversi ke header+detail,
     * jadi laporan sekarang sumbernya dari sini (1 baris = 1 item pembelian).
     */
    public function reportRows(array $filters = []): array
    {
        $sql = "SELECT opi.*, op.purchase_number, op.supplier_name, op.purchase_date, op.status,
                       p.project_name, op.project_id
                FROM offline_purchase_items opi
                JOIN offline_purchases op ON op.id = opi.offline_purchase_id AND op.deleted_at IS NULL
                JOIN projects p ON p.id = op.project_id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND op.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (opi.item_name LIKE :kw1 OR op.supplier_name LIKE :kw2 OR op.purchase_number LIKE :kw3)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND op.purchase_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND op.purchase_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY op.purchase_date DESC, opi.id ASC";

        return $this->db->fetchAll($sql, $params);
    }
}
