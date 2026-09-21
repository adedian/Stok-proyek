<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Rincian baris satu transaksi Bank -- {uraian, amount}. SENGAJA jauh lebih
 * sederhana dari cash_transaction_items (tidak ada kategori/barang/project/
 * qty/satuan -- Bank tidak menyentuh stok). Header bank_transactions.uraian
 * (concat "; ") & .amount (SUM) tetap dijaga BankController supaya list/
 * laporan/print Bank yang sudah ada tidak perlu tahu soal tabel ini.
 *
 * Bukan soft-delete -- ikut hidup/mati bersama header.
 */
class BankTransactionItem extends Model
{
    protected string $table = 'bank_transaction_items';

    public function byTransaction(int $bankTransactionId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM bank_transaction_items WHERE bank_transaction_id = :id ORDER BY id ASC",
            ['id' => $bankTransactionId]
        );
    }

    public function deleteByTransaction(int $bankTransactionId): void
    {
        $this->db->query(
            "DELETE FROM bank_transaction_items WHERE bank_transaction_id = :id",
            ['id' => $bankTransactionId]
        );
    }
}
