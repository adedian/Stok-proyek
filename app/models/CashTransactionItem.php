<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Rincian item satu transaksi Kas.
 * {cash_category_id, project_id, supplier_name, uraian, unit, qty,
 *  satuan(=harga satuan Rp), jumlah=qty*satuan}.
 * project_id diisi hanya untuk baris ber-kategori stok scope 'proyek';
 * supplier_name opsional untuk baris yang masuk stok.
 *
 * Kategori diambil per baris dari Master Kategori Kas (cash_categories). Kategori
 * ber-`affects_stock`=1 membuat baris ini otomatis menambah stok saat transaksi
 * disimpan (scope 'kantor' / 'proyek' dari kategori) -- idempotent via
 * `stock_posted_at`. Kategori "Biaya Operasional" (affects_stock=0) tidak
 * menyentuh stok.
 *
 * Bukan soft-delete -- ikut hidup/mati bersama header (FK ON DELETE CASCADE
 * untuk hard delete; saat header di-soft-delete, item dibiarkan menempel).
 */
class CashTransactionItem extends Model
{
    protected string $table = 'cash_transaction_items';

    public function byTransaction(int $trxId): array
    {
        return $this->db->fetchAll(
            "SELECT cti.*, cc.category_name, cc.affects_stock, cc.stock_scope, p.project_name
               FROM cash_transaction_items cti
               LEFT JOIN cash_categories cc ON cc.id = cti.cash_category_id
               LEFT JOIN projects p ON p.id = cti.project_id
              WHERE cti.cash_transaction_id = :id
           ORDER BY cti.id ASC",
            ['id' => $trxId]
        );
    }

    /**
     * Baris item Kas yang MEMPENGARUHI stok (kategori ber-affects_stock=1) --
     * dipakai untuk kredit / reverse stok. `stock_scope` ikut dibawa supaya
     * pemanggil tahu bucket kantor / proyek tanpa query ulang.
     */
    public function stockRowsByTransaction(int $trxId): array
    {
        return $this->db->fetchAll(
            "SELECT cti.*, cc.stock_scope
               FROM cash_transaction_items cti
               JOIN cash_categories cc ON cc.id = cti.cash_category_id
              WHERE cti.cash_transaction_id = :id AND cc.affects_stock = 1",
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
