<?php
require_once ROOT_PATH . '/core/Model.php';

class CollectionReceiptItem extends Model
{
    protected string $table = 'collection_receipt_items';

    public function itemsByReceipt(int $receiptId): array
    {
        return $this->db->fetchAll(
            "SELECT cri.*, si.invoice_number, si.tax_invoice_number, dn.delivery_number
             FROM collection_receipt_items cri
             JOIN sales_invoices si ON si.id = cri.sales_invoice_id
             LEFT JOIN delivery_notes dn ON dn.id = cri.delivery_note_id
             WHERE cri.collection_receipt_id = :id
             ORDER BY cri.id ASC",
            ['id' => $receiptId]
        );
    }

    public function deleteByReceipt(int $receiptId): int
    {
        return $this->db->query(
            "DELETE FROM collection_receipt_items WHERE collection_receipt_id = :id",
            ['id' => $receiptId]
        )->rowCount();
    }
}
