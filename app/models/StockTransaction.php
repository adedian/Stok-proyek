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
        return $this->db->fetchAll(
            "SELECT * FROM stock_transactions WHERE inventory_id = :id ORDER BY transaction_date DESC, id DESC",
            ['id' => $inventoryId]
        );
    }
}
