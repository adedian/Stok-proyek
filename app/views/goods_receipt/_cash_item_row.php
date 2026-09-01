<?php
/**
 * Partial: satu baris item Pembelian Kas untuk form penerimaan barang.
 * Variabel wajib: $cashItem (array dari cashItemsForReceipt), $index (int, opsional)
 * Meniru persis goods_receipt/_offline_item_row.php (versi Pembelian Offline).
 */
$currentQty = $cashItem['qty_received_current'] ?? '';
?>
<tr>
    <td>
        <?= e($cashItem['item_name']) ?>
        <input type="hidden" name="cash_item_id[]" value="<?= (int) $cashItem['cash_item_id'] ?>">
    </td>
    <td><?= e($cashItem['unit']) ?></td>
    <td class="text-end"><?= number_format($cashItem['qty_order'], 2, ',', '.') ?></td>
    <td class="text-end text-muted"><?= number_format($cashItem['qty_received_before'], 2, ',', '.') ?></td>
    <td class="text-end fw-semibold <?= $cashItem['qty_remaining'] > 0 ? 'text-warning' : 'text-success' ?>">
        <?= number_format($cashItem['qty_remaining'], 2, ',', '.') ?>
    </td>
    <td style="width: 140px;">
        <input type="number" name="qty_received[]" class="form-control form-control-sm"
               value="<?= e($currentQty) ?>" min="0" step="0.01" placeholder="0">
    </td>
</tr>
