<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * SalesInvoiceTerm (Revisi 10 Fase 5)
 * Baris termin/tahap penagihan 1 Invoice Keluar -- menggantikan peran "1 baris
 * DP tunggal" yang dulu melekat langsung di sales_invoices. Label+percentage
 * DIPERCAYA dari input user (sama seperti description/unit_price baris item
 * invoice) -- dp_percentage_id cuma jejak audit "preset mana yang dipakai",
 * BUKAN sumber kebenaran yang di-resolve ulang server-side, supaya invoice
 * lama tidak diam-diam berubah kalau master dp_percentages diedit belakangan.
 */
class SalesInvoiceTerm extends Model
{
    protected string $table = 'sales_invoice_terms';

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

    public function termsByInvoice(int $invoiceId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM sales_invoice_terms WHERE sales_invoice_id = :id ORDER BY term_no ASC, id ASC",
            ['id' => $invoiceId]
        );
    }

    public function deleteByInvoice(int $invoiceId): void
    {
        $this->db->query("DELETE FROM sales_invoice_terms WHERE sales_invoice_id = :id", ['id' => $invoiceId]);
    }

    /**
     * Termin + info pembayaran (total dibayar/status/sisa) dalam 1 query --
     * dipakai halaman Detail Invoice & dropdown "Pilih Termin" di form Pembayaran.
     */
    public function termsWithPaymentInfo(int $invoiceId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT t.*,
                    COALESCE((SELECT SUM(p.amount) FROM sales_invoice_payments p
                              WHERE p.sales_invoice_term_id = t.id AND p.deleted_at IS NULL), 0) AS total_paid
             FROM sales_invoice_terms t
             WHERE t.sales_invoice_id = :id
             ORDER BY t.term_no ASC, t.id ASC",
            ['id' => $invoiceId]
        );

        foreach ($rows as &$row) {
            $totalAmount = (float) $row['total_amount'];
            $totalPaid = (float) $row['total_paid'];
            $row['total_paid'] = $totalPaid;
            $row['remaining'] = max(0, $totalAmount - $totalPaid);
            $row['status'] = $this->resolveStatus($totalAmount, $totalPaid);
            $row['percentage_paid'] = $totalAmount > 0 ? min(100, round($totalPaid / $totalAmount * 100, 1)) : 0.0;
        }

        return $rows;
    }

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
     * Termin milik invoice yang belum dihapus, dgn label gabungan "No. Invoice --
     * Termin" + sisa tagihan -- dipakai dropdown "Pilih Termin" di form Pembayaran
     * Invoice (lintas semua invoice, bukan per-invoice).
     */
    public function payableTermsList(): array
    {
        $sql = "SELECT t.*, si.invoice_number, si.invoice_date, c.client_name,
                       COALESCE((SELECT SUM(p.amount) FROM sales_invoice_payments p
                                 WHERE p.sales_invoice_term_id = t.id AND p.deleted_at IS NULL), 0) AS total_paid
                FROM sales_invoice_terms t
                JOIN sales_invoices si ON si.id = t.sales_invoice_id AND si.deleted_at IS NULL
                JOIN clients c ON c.id = si.client_id
                ORDER BY si.invoice_date DESC, si.id DESC, t.term_no ASC";
        $rows = $this->db->fetchAll($sql);

        foreach ($rows as &$row) {
            $totalAmount = (float) $row['total_amount'];
            $totalPaid = (float) $row['total_paid'];
            $row['total_paid'] = $totalPaid;
            $row['remaining'] = max(0, $totalAmount - $totalPaid);
            $row['status'] = $this->resolveStatus($totalAmount, $totalPaid);
        }

        return $rows;
    }

    public function findWithInvoice(int $id)
    {
        $sql = "SELECT t.*, si.invoice_number, si.invoice_date, si.client_id, c.client_name
                FROM sales_invoice_terms t
                JOIN sales_invoices si ON si.id = t.sales_invoice_id AND si.deleted_at IS NULL
                JOIN clients c ON c.id = si.client_id
                WHERE t.id = :id";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
