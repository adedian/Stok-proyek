<?php
/**
 * Partial: satu baris item Pembelian Offline untuk form penerimaan barang.
 * Variabel wajib: $offlineItem (array dari offlineItemsForReceipt), $index (int, opsional)
 * Meniru persis goods_receipt/_item_row.php (versi PO).
 */
$currentQty = $offlineItem['qty_received_current'] ?? '';
?>
<tr>
    <td>
        <?= e($offlineItem['item_name']) ?>
        <input type="hidden" name="offline_item_id[]" value="<?= (int) $offlineItem['offline_item_id'] ?>">
    </td>
    <td><?= e($offlineItem['unit']) ?></td>
    <td class="text-end"><?= number_format($offlineItem['qty_order'], 2, ',', '.') ?></td>
    <td class="text-end text-muted"><?= number_format($offlineItem['qty_received_before'], 2, ',', '.') ?></td>
    <td class="text-end fw-semibold <?= $offlineItem['qty_remaining'] > 0 ? 'text-warning' : 'text-success' ?>">
        <?= number_format($offlineItem['qty_remaining'], 2, ',', '.') ?>
    </td>
    <td style="width: 140px;">
        <input type="number" name="qty_received[]" class="form-control form-control-sm"
               value="<?= e($currentQty) ?>" min="0" step="0.01" placeholder="0">
    </td>
</tr>
