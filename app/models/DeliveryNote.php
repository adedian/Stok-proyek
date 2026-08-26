<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * DeliveryNote (Surat Jalan)
 * Header yang MENGELOMPOKKAN beberapa baris stock_out (Pengeluaran Barang) yang
 * sudah ada jadi satu dokumen cetak -- lihat stock_out.delivery_note_id. Tidak
 * mengubah alur input Pengeluaran Barang; ini murni lapisan pengelompokan +
 * field khusus pengiriman (kendaraan/driver/penerima) yang belum ada di stock_out.
 */
class DeliveryNote extends Model
{
    protected string $table = 'delivery_notes';
    protected bool $softDelete = true;

    /**
     * "001/SJ.HME/VIII/2026" -- lihat catatan lengkap di SalesInvoice::generateInvoiceNumber(),
     * pola & jaminan yang sama (atomic, mengikuti tanggal dokumen, reset per tahun,
     * dibuat sekali saat dokumen dibuat).
     */
    public function generateDeliveryNumber(?string $deliveryDate = null): string
    {
        return (new DocumentNumber())->next('delivery_note', 'prefix_sj', $deliveryDate);
    }

    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT dn.*, c.client_name, p.project_name,
                       (SELECT COUNT(*) FROM stock_out so WHERE so.delivery_note_id = dn.id AND so.deleted_at IS NULL) AS item_count
                FROM delivery_notes dn
                LEFT JOIN clients c ON c.id = dn.client_id
                LEFT JOIN projects p ON p.id = dn.project_id
                WHERE dn.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            $sql .= " AND (dn.delivery_number LIKE :kw1 OR dn.destination_name LIKE :kw2 OR c.client_name LIKE :kw3)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND dn.delivery_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND dn.delivery_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY dn.delivery_date DESC, dn.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT dn.*, c.client_name, c.address AS client_address, p.project_name,
                       s.name AS signature_name, s.position AS signature_position, s.signature_image
                FROM delivery_notes dn
                LEFT JOIN clients c ON c.id = dn.client_id
                LEFT JOIN projects p ON p.id = dn.project_id
                LEFT JOIN signatures s ON s.id = dn.signature_id
                WHERE dn.id = :id AND dn.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    public function itemsByDeliveryNote(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT so.*, inv.item_name, inv.unit
             FROM stock_out so
             JOIN inventory inv ON inv.id = so.inventory_id
             WHERE so.delivery_note_id = :id AND so.deleted_at IS NULL
             ORDER BY so.id ASC",
            ['id' => $id]
        );
    }
}
