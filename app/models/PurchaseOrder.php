<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

class PurchaseOrder extends Model
{
    protected string $table = 'purchase_orders';
    protected bool $softDelete = true;

    public array $statusLabels = [
        'draft'            => 'Draft',
        'waiting_approval' => 'Menunggu Approval',
        'approved'         => 'Disetujui',
        'partial_received' => 'Sebagian Diterima',
        'completed'        => 'Selesai',
        'cancelled'        => 'Dibatalkan',
    ];

    public array $statusBadgeClass = [
        'draft'            => 'secondary',
        'waiting_approval' => 'warning',
        'approved'         => 'primary',
        'partial_received' => 'info',
        'completed'        => 'success',
        'cancelled'        => 'danger',
    ];

    /**
     * Ambil PO TERMASUK yang sudah soft-delete -- beda dari find() bawaan Model
     * yang otomatis menyembunyikan PO terhapus. Dipakai untuk kebutuhan historis
     * (mis. hapus Penerimaan Barang perlu tahu project_id PO aslinya supaya stok
     * bisa dikoreksi balik dengan benar, walau PO induknya sendiri sudah dihapus).
     */
    public function findAny(int $id)
    {
        return $this->db->fetchOne(
            "SELECT * FROM purchase_orders WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * List PO + join supplier & project, dengan filter opsional
     */
    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT po.*, s.supplier_name, s.supplier_code, p.project_name
                FROM purchase_orders po
                JOIN suppliers s ON s.id = po.supplier_id
                JOIN projects p ON p.id = po.project_id
                WHERE po.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND po.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND po.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (po.po_number LIKE :kw1 OR s.supplier_name LIKE :kw2)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND po.po_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND po.po_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY po.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Versi listWithRelations() khusus Laporan PO -- ditambah kolom Barang (ringkasan
     * nama item), Nota (No. Invoice terakhir kalau ada), dan Dibayar (total pembayaran
     * aktual + persentase, dihitung real dari tabel payments, bukan data manual).
     * Method listWithRelations() asli TIDAK diubah supaya halaman list PO existing
     * tidak terpengaruh.
     */
    public function listForReport(array $filters = []): array
    {
        $rows = $this->listWithRelations($filters);
        if (empty($rows)) {
            return [];
        }

        $poIds = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($poIds), '?'));

        $itemRows = $this->db->fetchAll(
            "SELECT purchase_order_id, GROUP_CONCAT(item_name SEPARATOR ', ') AS barang
             FROM purchase_order_items WHERE purchase_order_id IN ({$placeholders})
             GROUP BY purchase_order_id",
            $poIds
        );
        $barangByPo = array_column($itemRows, 'barang', 'purchase_order_id');

        // Kode Barang per PO: join lewat item_id (FK yang sudah ada) ke master items.
        // PO lama tanpa item_id (item legacy, sebelum katalog Barang ada) tidak ikut
        // ter-GROUP_CONCAT di sini -> tampil '-' di laporan (batasan yang diketahui).
        $codeRows = $this->db->fetchAll(
            "SELECT poi.purchase_order_id, GROUP_CONCAT(DISTINCT it.item_code SEPARATOR ', ') AS kode_barang
             FROM purchase_order_items poi
             INNER JOIN items it ON it.id = poi.item_id
             WHERE poi.purchase_order_id IN ({$placeholders})
             GROUP BY poi.purchase_order_id",
            $poIds
        );
        $kodeBarangByPo = array_column($codeRows, 'kode_barang', 'purchase_order_id');

        $invoiceRows = $this->db->fetchAll(
            "SELECT i1.purchase_order_id, i1.invoice_number
             FROM invoices i1
             INNER JOIN (
                 SELECT purchase_order_id, MAX(id) AS max_id FROM invoices
                 WHERE deleted_at IS NULL AND purchase_order_id IN ({$placeholders})
                 GROUP BY purchase_order_id
             ) latest ON latest.purchase_order_id = i1.purchase_order_id AND latest.max_id = i1.id",
            $poIds
        );
        $notaByPo = array_column($invoiceRows, 'invoice_number', 'purchase_order_id');

        $paymentRows = $this->db->fetchAll(
            "SELECT purchase_order_id, COALESCE(SUM(amount), 0) AS total_paid
             FROM payments WHERE deleted_at IS NULL AND purchase_order_id IN ({$placeholders})
             GROUP BY purchase_order_id",
            $poIds
        );
        $paidByPo = array_column($paymentRows, 'total_paid', 'purchase_order_id');

        foreach ($rows as &$row) {
            $totalPaid = (float) ($paidByPo[$row['id']] ?? 0);

            $row['barang'] = $barangByPo[$row['id']] ?? '-';
            $row['kode_barang'] = $kodeBarangByPo[$row['id']] ?? '-';
            $row['nota'] = $notaByPo[$row['id']] ?? '-';
            $row['dibayar'] = $totalPaid;
        }

        return $rows;
    }

    /**
     * List PER ITEM (bukan per PO seperti listForReport()) untuk export
     * "Laporan Rekap PO - Detail Barang": satu baris = satu baris item PO,
     * dengan Kode/Nama Barang, Qty, Harga Satuan, Total masing-masing baris
     * (bukan digabung GROUP_CONCAT seperti listForReport()).
     */
    public function listItemsForReport(array $filters = []): array
    {
        $sql = "SELECT po.po_date, s.supplier_name, COALESCE(it.item_code, '-') AS kode_barang,
                       poi.item_name, poi.unit, poi.qty_order, poi.price, poi.subtotal, u.full_name AS pembuat_po
                FROM purchase_order_items poi
                JOIN purchase_orders po ON po.id = poi.purchase_order_id
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN items it ON it.id = poi.item_id
                LEFT JOIN users u ON u.id = po.created_by
                WHERE po.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND po.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND po.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (po.po_number LIKE :kw1 OR s.supplier_name LIKE :kw2)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND po.po_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND po.po_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY po.po_date ASC, po.id ASC, poi.id ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * List PER PO (header) untuk export "Laporan Rekap PO - Rekap Pembayaran":
     * Nilai PO (total_amount), Total dibayar (SUM payments, sama seperti
     * listForReport()), Sisa belum dibayar, dan % belum dibayar.
     */
    public function listRecapForReport(array $filters = []): array
    {
        $rows = $this->listWithRelations($filters);
        if (empty($rows)) {
            return [];
        }

        $poIds = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($poIds), '?'));

        $paymentRows = $this->db->fetchAll(
            "SELECT purchase_order_id, COALESCE(SUM(amount), 0) AS total_paid
             FROM payments WHERE deleted_at IS NULL AND purchase_order_id IN ({$placeholders})
             GROUP BY purchase_order_id",
            $poIds
        );
        $paidByPo = array_column($paymentRows, 'total_paid', 'purchase_order_id');

        foreach ($rows as &$row) {
            $nilai = (float) $row['total_amount'];
            $dibayar = (float) ($paidByPo[$row['id']] ?? 0);
            $sisa = max($nilai - $dibayar, 0);

            $row['nilai_po'] = $nilai;
            $row['total_dibayar'] = $dibayar;
            $row['sisa_belum_dibayar'] = $sisa;
            $row['pct_belum_dibayar'] = $nilai > 0 ? round($sisa / $nilai * 100, 2) : 0;
        }

        return $rows;
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT po.*, s.supplier_name, s.supplier_code, s.address AS supplier_address,
                       s.contact_person AS supplier_contact_person, s.phone AS supplier_phone,
                       p.project_name, p.location AS project_location, u.full_name AS created_by_name,
                       w.warehouse_name AS delivery_location_name, w.address AS delivery_location_address,
                       sig.name AS signature_name, sig.position AS signature_position,
                       sig.signature_image AS signature_image
                FROM purchase_orders po
                JOIN suppliers s ON s.id = po.supplier_id
                JOIN projects p ON p.id = po.project_id
                LEFT JOIN users u ON u.id = po.created_by
                LEFT JOIN warehouses w ON w.id = po.delivery_location_id
                LEFT JOIN signatures sig ON sig.id = po.signature_id AND sig.deleted_at IS NULL
                WHERE po.id = :id AND po.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * "001/PO.HME/X/2026" -- lihat catatan lengkap di SalesInvoice::generateInvoiceNumber(),
     * pola & jaminan yang sama (atomic, reset per tahun).
     */
    public function generatePoNumber(?string $poDate = null): string
    {
        return (new DocumentNumber())->next('purchase_order', 'prefix_po', $poDate);
    }

    public function recalculateTotal(int $poId): void
    {
        $sql = "SELECT COALESCE(SUM(subtotal), 0) AS total
                FROM purchase_order_items WHERE purchase_order_id = :id";
        $total = $this->db->fetchOne($sql, ['id' => $poId])['total'];

        $this->updateById($poId, ['total_amount' => $total]);
    }

    /**
     * PO yang boleh dibuatkan pembayaran: bukan draft/cancelled
     * (dipakai oleh modul Pembayaran untuk mengisi dropdown)
     */
    public function payablePoList(): array
    {
        $sql = "SELECT po.id, po.po_number, po.total_amount, s.supplier_name
                FROM purchase_orders po
                JOIN suppliers s ON s.id = po.supplier_id
                WHERE po.deleted_at IS NULL
                  AND po.status NOT IN ('draft', 'cancelled')
                ORDER BY po.created_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * PO yang boleh dibuatkan penerimaan barang: sudah disetujui, belum selesai/batal
     * (dipakai oleh modul Penerimaan Barang untuk mengisi dropdown)
     */
    public function receivablePoList(): array
    {
        $sql = "SELECT po.id, po.po_number, s.supplier_name
                FROM purchase_orders po
                JOIN suppliers s ON s.id = po.supplier_id
                WHERE po.deleted_at IS NULL
                  AND po.status IN ('approved', 'partial_received')
                ORDER BY po.created_at DESC";
        return $this->db->fetchAll($sql);
    }

    /**
     * Evaluasi ulang status PO berdasarkan total qty diterima vs qty dipesan di seluruh item-nya.
     * Dipanggil setiap kali ada penerimaan barang baru/diubah/dihapus/divalidasi.
     * - Semua item qty diterima >= qty order  -> completed
     * - Sebagian item sudah ada penerimaan     -> partial_received
     * - Belum ada penerimaan sama sekali       -> tetap 'approved' (tidak diturunkan paksa)
     *
     * PENTING (bug fix): hanya qty yang SUDAH divalidasi & benar-benar masuk stok
     * (stock_posted_at terisi) yang dihitung "diterima". Item yang masih menunggu
     * validasi atau divalidasi "Barang Lain" TIDAK dihitung -- kalau tidak, PO bisa
     * saja berstatus "Selesai" padahal sebagian barangnya salah/belum tervalidasi.
     */
    public function refreshReceiptStatus(int $poId): void
    {
        $current = $this->find($poId);
        if (!$current || in_array($current['status'], ['draft', 'waiting_approval', 'cancelled'], true)) {
            return; // status di luar alur penerimaan barang, jangan diutak-atik
        }

        $sql = "SELECT poi.qty_order, COALESCE(SUM(CASE WHEN gri.stock_posted_at IS NOT NULL THEN gri.qty_received ELSE 0 END), 0) AS qty_received
                FROM purchase_order_items poi
                LEFT JOIN goods_receipt_items gri ON gri.purchase_order_item_id = poi.id
                LEFT JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
                WHERE poi.purchase_order_id = :po_id
                GROUP BY poi.id, poi.qty_order";
        $rows = $this->db->fetchAll($sql, ['po_id' => $poId]);

        $totalItems = count($rows);
        $completedItems = 0;
        $anyReceived = false;

        foreach ($rows as $row) {
            if ((float) $row['qty_received'] > 0) {
                $anyReceived = true;
            }
            if ((float) $row['qty_received'] >= (float) $row['qty_order']) {
                $completedItems++;
            }
        }

        if ($totalItems > 0 && $completedItems === $totalItems) {
            $newStatus = 'completed';
        } elseif ($anyReceived) {
            $newStatus = 'partial_received';
        } else {
            $newStatus = 'approved';
        }

        if ($newStatus !== $current['status']) {
            $this->updateById($poId, ['status' => $newStatus]);
        }
    }
}
