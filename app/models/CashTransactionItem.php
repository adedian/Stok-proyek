<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Rincian item satu transaksi Kas (Revisi 9 lanjutan).
 * {uraian, qty, satuan(=harga satuan Rp), jumlah=qty*satuan}.
 * Bukan soft-delete -- ikut hidup/mati bersama header (FK ON DELETE CASCADE
 * untuk hard delete; saat header di-soft-delete, item dibiarkan menempel).
 */
class CashTransactionItem extends Model
{
    protected string $table = 'cash_transaction_items';

    public function byTransaction(int $trxId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM cash_transaction_items WHERE cash_transaction_id = :id ORDER BY id ASC",
            ['id' => $trxId]
        );
    }

    public function deleteByTransaction(int $trxId): void
    {
        $this->db->query(
            "DELETE FROM cash_transaction_items WHERE cash_transaction_id = :id",
            ['id' => $trxId]
        );
    }
}
