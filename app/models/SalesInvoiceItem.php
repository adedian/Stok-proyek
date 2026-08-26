<?php
require_once ROOT_PATH . '/core/Model.php';

class SalesInvoiceItem extends Model
{
    protected string $table = 'sales_invoice_items';

    public function itemsByInvoice(int $invoiceId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM sales_invoice_items WHERE sales_invoice_id = :id ORDER BY id ASC",
            ['id' => $invoiceId]
        );
    }

    /**
     * Nama barang di 1 invoice (katalog Barang lewat item_id kalau ada, atau
     * teks bebas description kalau tidak -- mayoritas baris invoice memang
     * teks bebas/jasa, item_id opsional) -- dipakai StockOutController untuk
     * menandai/mendahulukan barang Stok Kantor yang cocok dengan invoice ini
     * di dropdown "Barang" pengeluaran tujuan Client.
     */
    public function itemNamesByInvoice(int $invoiceId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT COALESCE(it.item_name, sii.description) AS name
             FROM sales_invoice_items sii
             LEFT JOIN items it ON it.id = sii.item_id
             WHERE sii.sales_invoice_id = :id",
            ['id' => $invoiceId]
        );
        return array_column($rows, 'name');
    }

    public function deleteByInvoice(int $invoiceId): int
    {
        return $this->db->query(
            "DELETE FROM sales_invoice_items WHERE sales_invoice_id = :id",
            ['id' => $invoiceId]
        )->rowCount();
    }
}
