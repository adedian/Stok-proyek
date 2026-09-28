<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Biaya tambahan PO (ongkir, biaya bongkar, dll) -- baris terpisah dari
 * daftar barang, ikut menambah Grand Total (lihat PurchaseOrder::recalculateTotal()).
 */
class PurchaseOrderExtraCost extends Model
{
    protected string $table = 'purchase_order_extra_costs';

    public function itemsByPo(int $poId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM purchase_order_extra_costs WHERE purchase_order_id = :po_id ORDER BY id ASC",
            ['po_id' => $poId]
        );
    }

    public function deleteByPo(int $poId): int
    {
        return $this->db->query(
            "DELETE FROM purchase_order_extra_costs WHERE purchase_order_id = :po_id",
            ['po_id' => $poId]
        )->rowCount();
    }
}
