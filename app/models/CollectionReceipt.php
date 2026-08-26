<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * CollectionReceipt (Tanda Terima -- tanda terima penagihan)
 * Sesuai template Draft Tanda Terima.pdf: daftar No. Invoice/Faktur Pajak/
 * No. Surat Jalan/Total per baris (dari sales_invoices + delivery_notes),
 * BUKAN daftar barang dari Pengeluaran Barang.
 */
class CollectionReceipt extends Model
{
    protected string $table = 'collection_receipts';
    protected bool $softDelete = true;

    /**
     * "001/TT.HME/VIII/2026" -- format sama dengan Invoice Keluar & Surat Jalan
     * (lihat SalesInvoice::generateInvoiceNumber()), disamakan supaya konsisten
     * dengan template Tanda Terima terbaru yang mencantumkan contoh nomor ini.
     */
    public function generateReceiptNumber(?string $receiptDate = null): string
    {
        return (new DocumentNumber())->next('collection_receipt', 'prefix_tt', $receiptDate);
    }

    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT cr.*, c.client_name,
                       (SELECT COALESCE(SUM(total_amount), 0) FROM collection_receipt_items cri WHERE cri.collection_receipt_id = cr.id) AS grand_total
                FROM collection_receipts cr
                JOIN clients c ON c.id = cr.client_id
                WHERE cr.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            $sql .= " AND (cr.receipt_number LIKE :kw1 OR c.client_name LIKE :kw2)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND cr.receipt_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND cr.receipt_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY cr.receipt_date DESC, cr.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT cr.*, c.client_name, c.address AS client_address,
                       s.name AS signature_name, s.position AS signature_position, s.signature_image
                FROM collection_receipts cr
                JOIN clients c ON c.id = cr.client_id
                LEFT JOIN signatures s ON s.id = cr.signature_id
                WHERE cr.id = :id AND cr.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
