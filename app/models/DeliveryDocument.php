<?php
require_once ROOT_PATH . '/core/Model.php';

class DeliveryDocument extends Model
{
    protected string $table = 'delivery_documents';

    public function byReceipt(int $receiptId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM delivery_documents WHERE goods_receipt_id = :receipt_id ORDER BY id ASC",
            ['receipt_id' => $receiptId]
        );
    }

    public function deleteByReceipt(int $receiptId): int
    {
        return $this->db->query(
            "DELETE FROM delivery_documents WHERE goods_receipt_id = :receipt_id",
            ['receipt_id' => $receiptId]
        )->rowCount();
    }
}
