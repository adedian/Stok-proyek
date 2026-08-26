<?php
require_once ROOT_PATH . '/core/Model.php';

class PurchaseOrderHistory extends Model
{
    protected string $table = 'purchase_order_history';

    public function log(int $poId, string $action, string $description, ?int $userId): void
    {
        $this->create([
            'purchase_order_id' => $poId,
            'action'      => $action,
            'description' => $description,
            'created_by'  => $userId,
        ]);
    }

    public function timelineByPo(int $poId): array
    {
        $sql = "SELECT h.*, u.full_name
                FROM purchase_order_history h
                LEFT JOIN users u ON u.id = h.created_by
                WHERE h.purchase_order_id = :po_id
                ORDER BY h.created_at DESC";
        return $this->db->fetchAll($sql, ['po_id' => $poId]);
    }
}
