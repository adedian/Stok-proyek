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
            "SELECT cti.*, ic.category_name
               FROM cash_transaction_items cti
               LEFT JOIN item_categories ic ON ic.id = cti.category_id
              WHERE cti.cash_transaction_id = :id
           ORDER BY cti.id ASC",
            ['id' => $trxId]
        );
    }

    /** Baris item Kas yang terkait Barang (untuk kredit/reverse stok). */
    public function stockRowsByTransaction(int $trxId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM cash_transaction_items
              WHERE cash_transaction_id = :id AND item_id IS NOT NULL",
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
