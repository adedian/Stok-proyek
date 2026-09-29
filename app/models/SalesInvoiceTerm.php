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
 * Murni breakdown tagihan -- TIDAK ada tracking pembayaran di sini (user
 * sengaja memilih tidak perlu modul pembayaran terpisah).
 */
class SalesInvoiceTerm extends Model
{
    protected string $table = 'sales_invoice_terms';

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
}
