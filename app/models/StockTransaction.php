<?php
require_once ROOT_PATH . '/core/Model.php';

class StockTransaction extends Model
{
    protected string $table = 'stock_transactions';

    public function log(
        int $inventoryId,
        string $type,
        string $referenceType,
        int $referenceId,
        float $qty,
        float $qtyBefore,
        float $qtyAfter,
        string $transactionDate,
        ?int $userId,
        string $notes = ''
    ): void {
        $this->create([
            'inventory_id'      => $inventoryId,
            'transaction_type'  => $type, // in | out | adjustment
            'reference_type'    => $referenceType,
            'reference_id'      => $referenceId,
            'qty'               => $qty,
            'qty_before'        => $qtyBefore,
            'qty_after'         => $qtyAfter,
            'transaction_date'  => $transactionDate,
            'notes'             => $notes,
            'created_by'        => $userId,
        ]);
    }

    public function historyByInventory(int $inventoryId): array
    {
        // Ikutkan nomor dokumen sumber tiap mutasi supaya kolom "Referensi" di
        // Kartu Stok tampil manusiawi (mis. "Penerimaan Barang 026/LPB.HME/IX/2026")
        // -- bukan slug internal "goods_receipt_validation #13".
        // goods_receipt & goods_receipt_validation dua-duanya menunjuk goods_receipts.id.
        return $this->db->fetchAll(
            "SELECT st.*,
                    COALESCE(gr.receipt_number, so.stock_out_number, op.opname_number, ct.no_bukti) AS doc_number
               FROM stock_transactions st
               LEFT JOIN goods_receipts gr
                      ON gr.id = st.reference_id
                     AND st.reference_type IN ('goods_receipt', 'goods_receipt_validation')
               LEFT JOIN stock_out so
                      ON so.id = st.reference_id AND st.reference_type = 'stock_out'
               LEFT JOIN stock_opname op
                      ON op.id = st.reference_id AND st.reference_type = 'stock_opname'
               LEFT JOIN cash_transactions ct
                      ON ct.id = st.reference_id AND st.reference_type = 'kas'
              WHERE st.inventory_id = :id
              ORDER BY st.transaction_date DESC, st.id DESC",
            ['id' => $inventoryId]
        );
    }
}
