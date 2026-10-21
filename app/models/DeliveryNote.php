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
     * Nomor Surat Jalan kini DIKETIK MANUAL di form (bukan lagi otomatis).
     * generateDeliveryNumber() ("001/SJ.HME/VIII/2026") dipertahankan hanya kalau
     * nanti penomoran otomatis diaktifkan kembali; saat ini tidak dipanggil.
     */
    public function generateDeliveryNumber(?string $deliveryDate = null): string
    {
        return (new DocumentNumber())->next('delivery_note', 'prefix_sj', $deliveryDate);
    }

    /** Apakah nomor ini sudah dipakai Surat Jalan lain (termasuk di Tempat Sampah -- kolom delivery_number UNIK). */
    public function numberTaken(string $number, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM delivery_notes WHERE delivery_number = :n";
        $params = ['n' => $number];
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
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
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['dn.destination_name', 'c.client_name'],
                ['dn.delivery_number'],
                'dnkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
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
                       s.name AS signature_name, s.position AS signature_position, s.signature_image,
                       sord.order_number AS sales_order_number, sord.po_number AS sales_order_po_number,
                       sord.po_date AS sales_order_po_date
                FROM delivery_notes dn
                LEFT JOIN clients c ON c.id = dn.client_id
                LEFT JOIN projects p ON p.id = dn.project_id
                LEFT JOIN signatures s ON s.id = dn.signature_id
                LEFT JOIN sales_orders sord ON sord.id = dn.sales_order_id
                WHERE dn.id = :id AND dn.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Surat Jalan yang bisa diterbitkan Invoice Keluar (jenis Lampu): tujuan Client (supaya customer jelas)
     * dan belum punya invoice aktif. Terbaru dulu, dibatasi $limit.
     */
    public function eligibleForInvoice(int $limit = 300): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->fetchAll(
            "SELECT dn.id, dn.delivery_number, dn.delivery_date, dn.client_id, dn.sales_order_id,
                    c.client_name, sord.po_number
               FROM delivery_notes dn
               JOIN clients c ON c.id = dn.client_id
               LEFT JOIN sales_orders sord ON sord.id = dn.sales_order_id
              WHERE dn.deleted_at IS NULL AND dn.destination_type = 'client' AND dn.client_id IS NOT NULL
                AND EXISTS (SELECT 1 FROM stock_out so WHERE so.delivery_note_id = dn.id AND so.deleted_at IS NULL)
                AND NOT EXISTS (SELECT 1 FROM sales_invoices si WHERE si.delivery_note_id = dn.id AND si.deleted_at IS NULL)
              ORDER BY dn.delivery_date DESC, dn.id DESC
              LIMIT {$limit}"
        );
    }

    /** Surat Jalan + data order sumbernya (tanpa filter tujuan) -- untuk pratinjau/validasi invoice. */
    public function findForInvoice(int $id)
    {
        return $this->db->fetchOne(
            "SELECT dn.*, c.client_name, sord.order_number AS sales_order_number, sord.po_number, sord.po_date, sord.ppn_percent AS order_ppn
               FROM delivery_notes dn
               LEFT JOIN clients c ON c.id = dn.client_id
               LEFT JOIN sales_orders sord ON sord.id = dn.sales_order_id
              WHERE dn.id = :id AND dn.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    /** Invoice aktif (belum di Tempat Sampah) yang sudah memakai Surat Jalan ini, atau null. */
    public function activeInvoice(int $id, ?int $excludeInvoiceId = null)
    {
        $sql = "SELECT id, invoice_number FROM sales_invoices WHERE delivery_note_id = :d AND deleted_at IS NULL";
        $params = ['d' => $id];
        if ($excludeInvoiceId) {
            $sql .= " AND id <> :x";
            $params['x'] = $excludeInvoiceId;
        }
        return $this->db->fetchOne($sql . " LIMIT 1", $params) ?: null;
    }

    /** Kunci baris Surat Jalan (FOR UPDATE) -- menserialkan dua invoice yang memperebutkan Surat Jalan yang sama. */
    public function lockForUpdate(int $id)
    {
        return $this->db->fetchOne("SELECT * FROM delivery_notes WHERE id = :id AND deleted_at IS NULL FOR UPDATE", ['id' => $id]);
    }

    /**
     * Barang pada Surat Jalan, digabung per barang, siap jadi baris invoice.
     * Dari Order Penjualan: per baris order (membawa sales_order_item_id & harga order).
     * Tanpa order: per nama barang gudang (harga belum ada -> 0, diisi user).
     */
    public function invoiceLines(int $id, bool $fromOrder): array
    {
        if ($fromOrder) {
            return $this->db->fetchAll(
                "SELECT soi.id AS sales_order_item_id, soi.item_id, soi.item_name, soi.specification, soi.unit,
                        soi.net_price AS unit_price, SUM(so.qty) AS qty
                   FROM stock_out so
                   JOIN sales_order_items soi ON soi.id = so.sales_order_item_id
                  WHERE so.delivery_note_id = :id AND so.deleted_at IS NULL
                  GROUP BY soi.id, soi.item_id, soi.item_name, soi.specification, soi.unit, soi.net_price
                  ORDER BY soi.id",
                ['id' => $id]
            );
        }
        return $this->db->fetchAll(
            "SELECT NULL AS sales_order_item_id, it.id AS item_id, inv.item_name, NULL AS specification, inv.unit,
                    0 AS unit_price, SUM(so.qty) AS qty
               FROM stock_out so
               JOIN inventory inv ON inv.id = so.inventory_id
               LEFT JOIN items it ON it.item_name = inv.item_name AND it.deleted_at IS NULL
              WHERE so.delivery_note_id = :id AND so.deleted_at IS NULL
              GROUP BY inv.item_name, inv.unit, it.id
              ORDER BY MIN(so.id)",
            ['id' => $id]
        );
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
