<?php
/**
 * Partial: satu baris input item Pembelian Offline.
 * Variabel opsional: $item (array baris), $index (int).
 * Variabel wajib dari pemanggil: $itemCatalog (daftar Barang aktif, untuk dropdown).
 * Meniru persis pola purchase_order/_item_row.php.
 */
$item = $item ?? ['item_id' => null, 'item_name' => '', 'unit' => '', 'qty' => '', 'price' => ''];
$itemCatalog = $itemCatalog ?? [];
$hasItemId = !empty($item['item_id']);
$isLegacyRow = !$hasItemId && $item['item_name'] !== '';
?>
<tr class="item-row">
    <td>
        <div class="input-group input-group-sm">
            <select class="form-select item-select" required>
                <option value="">-- Pilih Barang --</option>
                <?php if ($isLegacyRow): ?>
                    <option value="legacy" data-legacy="1" data-name="<?= e($item['item_name']) ?>" data-unit="<?= e($item['unit']) ?>" selected>
                        (lama) <?= e($item['item_name']) ?>
                    </option>
                <?php endif; ?>
                <?php foreach ($itemCatalog as $it): ?>
                    <option value="<?= (int) $it['id'] ?>" data-unit="<?= e($it['unit_name']) ?>"
                        <?= $hasItemId && (int) $item['item_id'] === (int) $it['id'] ? 'selected' : '' ?>>
                        <?= e($it['item_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-outline-secondary btn-quick-add-item" title="Tambah Barang Cepat">
                <i class="bi bi-plus-lg"></i>
            </button>
        </div>
        <input type="hidden" name="item_id[]" class="item-id-input" value="<?= e((string) ($item['item_id'] ?? '')) ?>">
        <input type="hidden" name="item_name[]" class="item-name-input" value="<?= e($item['item_name']) ?>">
    </td>
    <td style="width: 120px;">
        <input type="text" class="form-control form-control-sm unit-display" value="<?= e($item['unit']) ?>" readonly>
        <input type="hidden" name="unit[]" class="unit-input" value="<?= e($item['unit']) ?>">
    </td>
    <td style="width: 120px;">
        <input type="number" name="qty[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty']) ?>" min="0.01" step="0.01" placeholder="0" required>
    </td>
    <td style="width: 160px;">
        <input type="text" name="price[]" class="form-control form-control-sm price-input currency-input"
               inputmode="numeric" value="<?= e($item['price'] !== '' ? number_format((float) $item['price'], 2, '.', ',') : '') ?>" placeholder="0" required>
    </td>
    <td style="width: 160px;" class="text-end subtotal-cell">Rp 0.00</td>
    <td style="width: 50px;" class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
            <i class="bi bi-trash"></i>
        </button>
    </td>
</tr>
