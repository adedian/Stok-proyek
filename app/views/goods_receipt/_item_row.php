<?php
/**
 * Partial: satu baris item PO untuk form penerimaan barang.
 * Variabel wajib: $poItem (array dari poItemsForReceipt), $index (int, opsional)
 */
$currentQty = $poItem['qty_received_current'] ?? '';
?>
<tr>
    <td>
        <?= e($poItem['item_name']) ?>
        <input type="hidden" name="po_item_id[]" value="<?= (int) $poItem['po_item_id'] ?>">
    </td>
    <td><?= e($poItem['unit']) ?></td>
    <td class="text-end"><?= number_format($poItem['qty_order'], 2, ',', '.') ?></td>
    <td class="text-end text-muted"><?= number_format($poItem['qty_received_before'], 2, ',', '.') ?></td>
    <td class="text-end fw-semibold <?= $poItem['qty_remaining'] > 0 ? 'text-warning' : 'text-success' ?>">
        <?= number_format($poItem['qty_remaining'], 2, ',', '.') ?>
    </td>
    <td style="width: 140px;">
        <input type="number" name="qty_received[]" class="form-control form-control-sm"
               value="<?= e($currentQty) ?>" min="0" step="0.01" placeholder="0">
    </td>
</tr>
