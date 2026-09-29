<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * SalesInvoicePayment (Revisi 10 Fase 5)
 * Uang masuk dari client per termin Invoice Keluar -- versi AR dari model
 * `Payment` (yang sudah ada utk PO/AP). Status lunas/sebagian/belum TIDAK
 * disimpan di baris ini -- selalu dihitung dari SUM(amount) vs
 * sales_invoice_terms.total_amount (lihat SalesInvoiceTerm::resolveStatus()),
 * sama seperti pola Payment::resolveStatus().
 */
class SalesInvoicePayment extends Model
{
    protected string $table = 'sales_invoice_payments';
    protected bool $softDelete = true;

    public function generatePaymentNumber(?string $date = null): string
    {
        return (new DocumentNumber())->next('sales_invoice_payment', 'prefix_inv_pay', $date, 'KW.HME');
    }

    public function totalPaidByTerm(int $termId): float
    {
        $result = $this->db->fetchOne(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM sales_invoice_payments
             WHERE sales_invoice_term_id = :term_id AND deleted_at IS NULL",
            ['term_id' => $termId]
        );
        return (float) $result['total'];
    }

    public function listWithRelations(array $filters = []): array
    {
        $sql = "SELECT sip.*, t.label AS term_label, t.percentage, t.total_amount AS term_total,
                       si.invoice_number, si.id AS sales_invoice_id, c.client_name
                FROM sales_invoice_payments sip
                JOIN sales_invoice_terms t ON t.id = sip.sales_invoice_term_id
                JOIN sales_invoices si ON si.id = t.sales_invoice_id
                JOIN clients c ON c.id = si.client_id
                WHERE sip.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['c.client_name'],
                ['sip.payment_number', 'si.invoice_number'],
                'sipkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['sales_invoice_id'])) {
            $sql .= " AND si.id = :sales_invoice_id";
            $params['sales_invoice_id'] = $filters['sales_invoice_id'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND sip.payment_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND sip.payment_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY sip.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT sip.*, t.label AS term_label, t.percentage, t.total_amount AS term_total,
                       t.sales_invoice_id, si.invoice_number, c.client_name
                FROM sales_invoice_payments sip
                JOIN sales_invoice_terms t ON t.id = sip.sales_invoice_term_id
                JOIN sales_invoices si ON si.id = t.sales_invoice_id
                JOIN clients c ON c.id = si.client_id
                WHERE sip.id = :id AND sip.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
