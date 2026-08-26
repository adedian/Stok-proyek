<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

class Payment extends Model
{
    protected string $table = 'payments';
    protected bool $softDelete = true;

    public array $statusLabels = [
        'pending' => 'Belum Dibayar',
        'partial' => 'Dibayar Sebagian',
        'paid'    => 'Lunas',
    ];

    public array $statusBadgeClass = [
        'pending' => 'secondary',
        'partial' => 'warning',
        'paid'    => 'success',
    ];

    /**
     * Sumber dana pembayaran (Revisi 7 #16-21) -- label + kode BK/KK/KKP dan
     * key setting prefix nomor (system_settings, tab Pengaturan > Penomoran)
     * masing-masing, supaya sequence-nya TERPISAH per sumber dana (bukan
     * nomor global) sesuai permintaan.
     */
    public array $fundingSourceLabels = [
        'bank'         => 'Bank',
        'kas_kecil'    => 'Kas Kecil',
        'kas_project'  => 'Kas Project',
    ];

    public array $fundingSourceCodes = [
        'bank'        => 'BK',
        'kas_kecil'   => 'KK',
        'kas_project' => 'KKP',
    ];

    private const FUNDING_SOURCE_DOC_TYPE = [
        'bank'        => 'payment_bk',
        'kas_kecil'   => 'payment_kk',
        'kas_project' => 'payment_kkp',
    ];

    private const FUNDING_SOURCE_SETTING_KEY = [
        'bank'        => 'prefix_pay_bk',
        'kas_kecil'   => 'prefix_pay_kk',
        'kas_project' => 'prefix_pay_kkp',
    ];

    /**
     * Generate nomor pembayaran otomatis, format "001/BK.HME/VIII/2026" dkk
     * -- sequence ATOMIC & TERPISAH per sumber dana (lihat DocumentNumber::next()),
     * jadi Bank/Kas Kecil/Kas Project masing-masing mulai dari 001, bukan
     * berbagi satu nomor urut global.
     */
    public function generatePaymentNumber(string $fundingSource, ?string $date = null): string
    {
        $docType = self::FUNDING_SOURCE_DOC_TYPE[$fundingSource] ?? self::FUNDING_SOURCE_DOC_TYPE['bank'];
        $settingKey = self::FUNDING_SOURCE_SETTING_KEY[$fundingSource] ?? self::FUNDING_SOURCE_SETTING_KEY['bank'];
        $default = $this->fundingSourceCodes[$fundingSource] ?? 'BK';

        return (new DocumentNumber())->next($docType, $settingKey, $date, $default . '.HME');
    }

    /**
     * Total yang sudah dibayar untuk satu PO (tidak termasuk yang soft-deleted)
     */
    public function totalPaidByPo(int $poId): float
    {
        $result = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
             WHERE purchase_order_id = :po_id AND deleted_at IS NULL",
            ['po_id' => $poId]
        );
        return (float) $result['total'];
    }

    /**
     * Hitung status pembayaran suatu PO berdasarkan total_amount vs total dibayar
     */
    public function resolveStatus(float $totalAmount, float $totalPaid): string
    {
        if ($totalPaid <= 0) {
            return 'pending';
        }
        if ($totalPaid >= $totalAmount) {
            return 'paid';
        }
        return 'partial';
    }

    /**
     * List pembayaran + relasi PO/supplier, dengan filter opsional.
     * Setiap baris juga dilengkapi progress pembayaran KUMULATIF PO terkait
     * (po_total_paid/po_payment_percentage) -- beda dari `amount` yang cuma
     * nominal termin ini -- supaya list bisa menampilkan "sudah X% dari PO".
     */
    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT pay.*, po.po_number, po.total_amount, po.project_id, po.pembuat_po, s.supplier_name, p.project_name,
                       pm.method_name,
                       (SELECT COALESCE(SUM(p2.amount), 0) FROM payments p2
                        WHERE p2.purchase_order_id = po.id AND p2.deleted_at IS NULL) AS po_total_paid
                FROM payments pay
                JOIN purchase_orders po ON po.id = pay.purchase_order_id
                JOIN suppliers s ON s.id = po.supplier_id
                JOIN projects p ON p.id = po.project_id
                LEFT JOIN payment_methods pm ON pm.id = pay.payment_method_id
                WHERE pay.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND pay.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['po_id'])) {
            $sql .= " AND pay.purchase_order_id = :po_id";
            $params['po_id'] = $filters['po_id'];
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND po.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND (pay.payment_number LIKE :kw1 OR po.po_number LIKE :kw2 OR s.supplier_name LIKE :kw3)";
            $kw = '%' . $filters['keyword'] . '%';
            $params['kw1'] = $kw;
            $params['kw2'] = $kw;
            $params['kw3'] = $kw;
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND pay.payment_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND pay.payment_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY pay.created_at DESC";

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $totalAmount = (float) $row['total_amount'];
            $poTotalPaid = (float) $row['po_total_paid'];
            $row['po_total_paid'] = $poTotalPaid;
            $row['po_payment_percentage'] = $totalAmount > 0
                ? min(100, round($poTotalPaid / $totalAmount * 100, 1))
                : 0.0;
        }

        return $rows;
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT pay.*, po.po_number, po.total_amount, po.pembuat_po, s.supplier_name, pm.method_name
                FROM payments pay
                JOIN purchase_orders po ON po.id = pay.purchase_order_id
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN payment_methods pm ON pm.id = pay.payment_method_id
                WHERE pay.id = :id AND pay.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Ringkasan status pembayaran semua PO (untuk kartu di halaman pembayaran)
     * PO yang belum punya PO sama sekali (status draft misalnya) tetap dihitung "belum dibayar".
     */
    public function poPaymentSummary(): array
    {
        $sql = "SELECT po.id, po.po_number, po.total_amount, po.status AS po_status, po.pembuat_po,
                       s.supplier_name,
                       COALESCE(SUM(pay.amount), 0) AS total_paid
                FROM purchase_orders po
                JOIN suppliers s ON s.id = po.supplier_id
                LEFT JOIN payments pay ON pay.purchase_order_id = po.id AND pay.deleted_at IS NULL
                WHERE po.deleted_at IS NULL
                GROUP BY po.id, po.po_number, po.total_amount, po.status, po.pembuat_po, s.supplier_name
                ORDER BY po.created_at DESC";

        $rows = $this->db->fetchAll($sql);

        foreach ($rows as &$row) {
            $row['total_paid'] = (float) $row['total_paid'];
            $row['remaining'] = max(0, (float) $row['total_amount'] - $row['total_paid']);
            $row['payment_status'] = $this->resolveStatus((float) $row['total_amount'], $row['total_paid']);
            $row['percentage'] = (float) $row['total_amount'] > 0
                ? min(100, round($row['total_paid'] / (float) $row['total_amount'] * 100, 1))
                : 0.0;
        }

        return $rows;
    }

    /**
     * Info pembayaran satu PO: total PO, sudah dibayar, persentase (dibulatkan,
     * dibatasi max 100), sisa, dan status. Dipakai di kartu "Info Pembayaran"
     * pada Detail PO dan form Tambah/Edit Pembayaran. Persentase SELALU dihitung
     * dari data payments/purchase_orders yang sebenarnya -- tidak pernah disimpan manual.
     */
    public function poPaymentInfo(int $poId, float $totalAmount): array
    {
        $totalPaid = $this->totalPaidByPo($poId);
        $percentage = $totalAmount > 0 ? min(100, round($totalPaid / $totalAmount * 100, 1)) : 0.0;

        return [
            'total_amount' => $totalAmount,
            'total_paid'   => $totalPaid,
            'remaining'    => max(0, $totalAmount - $totalPaid),
            'percentage'   => $percentage,
            'status'       => $this->resolveStatus($totalAmount, $totalPaid),
        ];
    }

    /**
     * Progress pembayaran keseluruhan (semua PO yang bisa dibayar, bukan draft/cancelled) --
     * dipakai untuk kartu ringkasan di Dashboard.
     */
    public function overallProgress(): array
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(po.total_amount), 0) AS total_amount,
                    COALESCE(SUM(pay.amount), 0) AS total_paid
             FROM purchase_orders po
             LEFT JOIN payments pay ON pay.purchase_order_id = po.id AND pay.deleted_at IS NULL
             WHERE po.deleted_at IS NULL AND po.status NOT IN ('draft', 'cancelled')"
        );

        $totalAmount = (float) ($row['total_amount'] ?? 0);
        $totalPaid = (float) ($row['total_paid'] ?? 0);
        $percentage = $totalAmount > 0 ? min(100, round($totalPaid / $totalAmount * 100, 1)) : 0.0;

        return [
            'total_amount' => $totalAmount,
            'total_paid'   => $totalPaid,
            'remaining'    => max(0, $totalAmount - $totalPaid),
            'percentage'   => $percentage,
        ];
    }

    /**
     * Hitung jumlah PO per kategori status pembayaran (untuk kartu summary)
     */
    public function countStatusSummary(): array
    {
        $summary = $this->poPaymentSummary();
        $counts = ['pending' => 0, 'partial' => 0, 'paid' => 0];

        foreach ($summary as $row) {
            $counts[$row['payment_status']]++;
        }

        return $counts;
    }
}
