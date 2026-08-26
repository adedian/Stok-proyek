<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

class StockOut extends Model
{
    protected string $table = 'stock_out';
    protected bool $softDelete = true;

    /**
     * "001/STO.HME/X/2026" -- lihat catatan lengkap di SalesInvoice::generateInvoiceNumber(),
     * pola & jaminan yang sama (atomic, reset per tahun).
     */
    public function generateStockOutNumber(?string $outDate = null): string
    {
        return (new DocumentNumber())->next('stock_out', 'prefix_sto', $outDate);
    }

    public function listWithRelations(array $filters = []): array
    {
        // LEFT JOIN projects (bukan INNER) -- so.project_id NULL untuk pengeluaran
        // tujuan Client (destination_type='client'), lihat sales_invoice_id/clients.
        $sql = "SELECT so.*, inv.item_name, inv.unit, p.project_name, dn.delivery_number,
                       si.invoice_number, c.client_name
                FROM stock_out so
                JOIN inventory inv ON inv.id = so.inventory_id
                LEFT JOIN projects p ON p.id = so.project_id
                LEFT JOIN sales_invoices si ON si.id = so.sales_invoice_id
                LEFT JOIN clients c ON c.id = si.client_id
                LEFT JOIN delivery_notes dn ON dn.id = so.delivery_note_id AND dn.deleted_at IS NULL
                WHERE so.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND so.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (inv.item_name LIKE :kw1 OR so.destination LIKE :kw2 OR so.pic_name LIKE :kw3)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND so.out_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND so.out_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY so.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT so.*, inv.item_name, inv.unit, inv.qty_available, p.project_name,
                       si.invoice_number, c.client_name
                FROM stock_out so
                JOIN inventory inv ON inv.id = so.inventory_id
                LEFT JOIN projects p ON p.id = so.project_id
                LEFT JOIN sales_invoices si ON si.id = so.sales_invoice_id
                LEFT JOIN clients c ON c.id = si.client_id
                WHERE so.id = :id AND so.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Lepaskan semua baris stock_out yang terpasang ke satu Surat Jalan --
     * dipakai saat Surat Jalan dihapus (DeliveryNoteController::delete()).
     * Baris pengeluaran barangnya sendiri TIDAK ikut dihapus/diubah, cuma
     * dilepas supaya bisa dikelompokkan ulang ke Surat Jalan lain.
     */
    public function unlinkDeliveryNote(int $deliveryNoteId): void
    {
        $this->db->query(
            "UPDATE stock_out SET delivery_note_id = NULL WHERE delivery_note_id = :id",
            ['id' => $deliveryNoteId]
        );
    }
}
