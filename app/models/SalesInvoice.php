<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * SalesInvoice (Invoice Keluar / AR)
 * SENGAJA terpisah dari model `Invoice` (AP -- invoice masuk dari supplier,
 * dipakai InvoiceValidation untuk auto-match PO/pembayaran/goods-receipt).
 * Modul ini punya arah & bentuk data berbeda: HME menagih ke client, dengan
 * baris item + PPN, bukan 1 nominal. Lihat migration 2026_08_24_* untuk
 * konteks lengkap kenapa dipisah.
 */
class SalesInvoice extends Model
{
    protected string $table = 'sales_invoices';
    protected bool $softDelete = true;
    /** Termin milik invoice (FK RESTRICT) ikut terhapus saat invoice dihapus permanen dari Tempat Sampah. */
    protected array $ownedChildren = ['sales_invoice_terms' => 'sales_invoice_id'];

    /**
     * Nomor invoice keluar dibuat OTOMATIS saat Tambah: "001/INV.HME/X/2026" (Project)
     * atau "001/FKT.HME/X/2026" (Lampu) -- urut/kode/bulan romawi/tahun mengikuti tanggal
     * invoice, atomic (lihat DocumentNumber::next()). Project & Lampu memakai doc_type
     * counter TERPISAH. Saat Edit nomor boleh dikoreksi manual.
     */
    public function generateInvoiceNumber(string $invoiceType, ?string $invoiceDate = null): string
    {
        $docType = $invoiceType === 'lampu' ? 'sales_invoice_lampu' : 'sales_invoice';
        $codeSettingKey = $invoiceType === 'lampu' ? 'prefix_fkt' : 'prefix_sls';
        return (new DocumentNumber())->next($docType, $codeSettingKey, $invoiceDate);
    }

    /**
     * Seperti generateInvoiceNumber(), tapi melewati nomor yang sudah terpakai (kolom UNIK) --
     * mis. nomor yang dulu diketik manual dan kebetulan sama dengan urutan counter.
     */
    public function generateUniqueInvoiceNumber(string $invoiceType, ?string $invoiceDate = null): string
    {
        for ($i = 0; $i < 50; $i++) {
            $number = $this->generateInvoiceNumber($invoiceType, $invoiceDate);
            if (!$this->numberTaken($number)) {
                return $number;
            }
        }
        throw new RuntimeException('Tidak dapat menemukan nomor invoice yang belum terpakai.');
    }

    /** Preview nomor untuk FORM tambah (tidak menaikkan counter) -- lihat DocumentNumber::preview(). */
    public function previewInvoiceNumber(string $invoiceType, ?string $invoiceDate = null): string
    {
        $docType = $invoiceType === 'lampu' ? 'sales_invoice_lampu' : 'sales_invoice';
        $codeSettingKey = $invoiceType === 'lampu' ? 'prefix_fkt' : 'prefix_sls';
        return (new DocumentNumber())->preview($docType, $codeSettingKey, $invoiceDate);
    }

    /**
     * Rumus Invoice Keluar (Termin, Revisi 10 Fase 5) -- satu-satunya tempat rumusnya
     * (dipakai SalesInvoiceController DAN penerbitan invoice dari Order Penjualan):
     *   Jumlah         = SUM(subtotal tiap item)
     *   Nominal termin = Jumlah x %termin
     *   PPN termin     = Nominal termin x PPN%
     *   Total termin   = Nominal termin + PPN termin
     *   Total invoice  = SUM(Total tiap termin)
     * Selalu dihitung ulang di backend; angka dari browser tidak pernah dipercaya.
     * Kolom agregat (dp_percentage/dp_amount/ppn_amount/total_amount) = SUM seluruh termin.
     */
    public static function calculateTotals(array $items, array $terms, float $ppnPercent): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item['subtotal'];
        }
        $subtotal = round($subtotal, 2);

        $computedTerms = [];
        $sumPercentage = 0.0;
        $sumAmount = 0.0;
        $sumPpn = 0.0;
        $sumTotal = 0.0;

        foreach ($terms as $i => $term) {
            $amount = round($subtotal * $term['percentage'] / 100, 2);
            $ppnAmount = round($amount * $ppnPercent / 100, 2);
            $total = round($amount + $ppnAmount, 2);

            $computedTerms[] = [
                'term_no'          => $i + 1,
                'dp_percentage_id' => $term['dp_percentage_id'],
                'label'            => $term['label'],
                'percentage'       => $term['percentage'],
                'amount'           => $amount,
                'ppn_amount'       => $ppnAmount,
                'total_amount'     => $total,
                'due_date'         => $term['due_date'],
            ];

            $sumPercentage += $term['percentage'];
            $sumAmount += $amount;
            $sumPpn += $ppnAmount;
            $sumTotal += $total;
        }

        return [
            'subtotal'         => $subtotal,
            'terms'            => $computedTerms,
            // dp_percentage_id agregat hanya bermakna kalau invoice ini PERSIS 1 termin.
            'dp_percentage_id' => count($computedTerms) === 1 ? $computedTerms[0]['dp_percentage_id'] : null,
            'dp_percentage'    => round($sumPercentage, 2),
            'dp_amount'        => round($sumAmount, 2),
            'ppn_amount'       => round($sumPpn, 2),
            'total'            => round($sumTotal, 2),
        ];
    }

    /** Apakah nomor ini sudah dipakai invoice lain (termasuk di Tempat Sampah -- kolom invoice_number UNIK). */
    public function numberTaken(string $number, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM sales_invoices WHERE invoice_number = :n";
        $params = ['n' => $number];
        if ($excludeId) {
            $sql .= " AND id != :id";
            $params['id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    /**
     * Subquery status tertagih: invoice dianggap SUDAH tertagih kalau sudah
     * muncul di minimal 1 Tanda Terima yang belum dibatalkan -- sama persis
     * dengan definisi DashboardStat::invoiceBelumTertagih(), dipakai bareng
     * di sini (kolom is_tertagih + filter billing_status) supaya kartu
     * dashboard & list Invoice Keluar selalu konsisten satu sama lain.
     */
    private const TERTAGIH_EXISTS = "EXISTS (
        SELECT 1 FROM collection_receipt_items cri
        JOIN collection_receipts cr ON cr.id = cri.collection_receipt_id AND cr.deleted_at IS NULL
        WHERE cri.sales_invoice_id = si.id
    )";

    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT si.*, c.client_name, c.client_code, p.project_name, s.name AS signature_name,
                       " . self::TERTAGIH_EXISTS . " AS is_tertagih
                FROM sales_invoices si
                JOIN clients c ON c.id = si.client_id
                LEFT JOIN projects p ON p.id = si.project_id
                LEFT JOIN signatures s ON s.id = si.signature_id
                WHERE si.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['c.client_name'],
                ['si.invoice_number'],
                'sikw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['client_id'])) {
            $sql .= " AND si.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }
        if (!empty($filters['invoice_type']) && in_array($filters['invoice_type'], ['project', 'lampu'], true)) {
            $sql .= " AND si.invoice_type = :invoice_type";
            $params['invoice_type'] = $filters['invoice_type'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND si.invoice_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND si.invoice_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['billing_status']) && in_array($filters['billing_status'], ['tertagih', 'belum_tertagih'], true)) {
            $sql .= $filters['billing_status'] === 'tertagih'
                ? " AND " . self::TERTAGIH_EXISTS
                : " AND NOT " . self::TERTAGIH_EXISTS;
        }
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $filters['ids']), fn($id) => $id > 0)));
            if (!empty($ids)) {
                $placeholders = [];
                foreach ($ids as $i => $id) {
                    $key = "id{$i}";
                    $placeholders[] = ":{$key}";
                    $params[$key] = $id;
                }
                $sql .= " AND si.id IN (" . implode(',', $placeholders) . ")";
            }
        }

        $sql .= " ORDER BY si.invoice_date DESC, si.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT si.*, c.client_name, c.client_code, c.address AS client_address,
                       c.contact_person AS client_contact_person, c.phone AS client_phone,
                       p.project_name, s.name AS signature_name, s.position AS signature_position,
                       s.signature_image, sord.order_number AS sales_order_number, sord.po_number AS sales_order_po_number,
                       dnl.delivery_number AS linked_delivery_number
                FROM sales_invoices si
                JOIN clients c ON c.id = si.client_id
                LEFT JOIN projects p ON p.id = si.project_id
                LEFT JOIN signatures s ON s.id = si.signature_id
                LEFT JOIN sales_orders sord ON sord.id = si.sales_order_id
                LEFT JOIN delivery_notes dnl ON dnl.id = si.delivery_note_id
                WHERE si.id = :id AND si.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    public function numberExists(string $number, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM sales_invoices WHERE invoice_number = :n AND deleted_at IS NULL";
        $params = ['n' => $number];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    /**
     * Total invoice yang SUDAH dipakai di suatu Tanda Terima (collection_receipt_items) --
     * dipakai untuk menyembunyikan/menandai invoice yang sudah ditagih supaya tidak
     * dobel-tagih tanpa sengaja saat memilih invoice untuk Tanda Terima baru.
     */
    /**
     * ROOT FIX: sebelumnya cek collection_receipt_items TANPA JOIN ke
     * collection_receipts, jadi Tanda Terima yang sudah dihapus (soft-delete)
     * masih membuat invoice ini kelihatan "sudah tertagih" selamanya --
     * baris collection_receipt_items memang tidak ikut terhapus saat Tanda
     * Terima-nya dihapus (histori tetap utuh by design). Sekarang pakai
     * TERTAGIH_EXISTS yang sama dengan listWithRelations()/is_tertagih.
     */
    public function isBilled(int $id): bool
    {
        $row = $this->db->fetchOne(
            "SELECT " . self::TERTAGIH_EXISTS . " AS billed FROM sales_invoices si WHERE si.id = :id",
            ['id' => $id]
        );
        return $row ? (bool) $row['billed'] : false;
    }

    /**
     * Invoice milik 1 client yang boleh dipilih untuk sebuah Tanda Terima:
     * belum pernah ditagih ($isBilled), ATAU sudah ditagih tapi di receipt yang
     * SEDANG diedit ($excludeReceiptId) -- dipakai form Edit Tanda Terima supaya
     * invoice yang sudah termasuk di receipt itu sendiri tetap muncul sebagai
     * pilihan (bukan disembunyikan karena dianggap "sudah dipakai").
     */
    public function availableForClient(int $clientId, ?int $excludeReceiptId = null): array
    {
        // Sama seperti isBilled() -- JOIN ke collection_receipts (bukan cuma
        // collection_receipt_items) supaya invoice yang Tanda Terima-nya sudah
        // dihapus kembali muncul sebagai "belum ditagih" & bisa dipilih lagi.
        $sql = "SELECT si.* FROM sales_invoices si
                WHERE si.deleted_at IS NULL AND si.client_id = :client_id
                  AND NOT EXISTS (
                      SELECT 1 FROM collection_receipt_items cri
                      JOIN collection_receipts cr ON cr.id = cri.collection_receipt_id AND cr.deleted_at IS NULL
                      WHERE cri.sales_invoice_id = si.id";
        $params = ['client_id' => $clientId];
        if ($excludeReceiptId) {
            $sql .= " AND cri.collection_receipt_id != :exclude_receipt_id";
            $params['exclude_receipt_id'] = $excludeReceiptId;
        }
        $sql .= "                  )
                ORDER BY si.invoice_date DESC, si.id DESC";

        return $this->db->fetchAll($sql, $params);
    }
}
