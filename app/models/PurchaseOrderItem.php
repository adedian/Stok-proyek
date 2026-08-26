<?php
require_once ROOT_PATH . '/core/Model.php';

class PurchaseOrderItem extends Model
{
    protected string $table = 'purchase_order_items';

    public function itemsByPo(int $poId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM purchase_order_items WHERE purchase_order_id = :po_id ORDER BY id ASC",
            ['po_id' => $poId]
        );
    }

    public function deleteByPo(int $poId): int
    {
        return $this->db->query(
            "DELETE FROM purchase_order_items WHERE purchase_order_id = :po_id",
            ['po_id' => $poId]
        )->rowCount();
    }

    /**
     * True kalau minimal satu item PO ini sudah punya penerimaan barang
     * (goods_receipt_items). Item seperti ini TIDAK BOLEH dihapus/diganti --
     * FK fk_gri_poi akan menolaknya -- dipakai untuk mengunci edit item di PO
     * yang sudah pernah diterima barangnya (lihat PurchaseOrderController::update()).
     */
    public function hasReceipts(int $poId): bool
    {
        $result = $this->db->fetchOne(
            "SELECT 1 FROM purchase_order_items poi
             JOIN goods_receipt_items gri ON gri.purchase_order_item_id = poi.id
             WHERE poi.purchase_order_id = :po_id
             LIMIT 1",
            ['po_id' => $poId]
        );
        return (bool) $result;
    }
}
