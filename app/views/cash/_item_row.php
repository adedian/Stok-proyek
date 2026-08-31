<?php
/**
 * Partial: satu baris rincian item Kas.
 * Var opsional: $item (uraian, qty, satuan, jumlah, item_id, category_id,
 *   category_name, unit), $index (int).
 * Var dari pemanggil (create/edit/ajaxItemRow): $itemCatalog (Barang aktif),
 *   $itemCategories (untuk map id->nama).
 *
 * Kolom ber-class .stock-col hanya tampil saat tabel ber-class .stock-mode
 * (mode "Pembelian Barang (masuk stok)"). Kelas .qty-input/.price-input/
 * .subtotal-cell/.btn-remove-row/.item-row dipakai JS penghitung total.
 */
$item = $item ?? ['uraian' => '', 'qty' => '', 'satuan' => '', 'jumlah' => '', 'item_id' => null, 'category_id' => null, 'category_name' => '', 'unit' => ''];
$itemCatalog    = $itemCatalog ?? [];
$itemCategories = $itemCategories ?? [];
$catMap = [];
foreach ($itemCategories as $c) {
    $catMap[(int) $c['id']] = $c['category_name'];
}
$satuanVal = ($item['satuan'] ?? '') !== '' ? number_format((float) $item['satuan'], 2, '.', ',') : '';
$curItemId = !empty($item['item_id']) ? (int) $item['item_id'] : 0;
$curCatName = $item['category_name'] ?? ($catMap[(int) ($item['category_id'] ?? 0)] ?? '');
?>
<tr class="item-row">
    <td class="stock-col" data-label="Barang" style="min-width: 180px;">
        <div class="input-group input-group-sm">
            <select class="form-select form-select-sm barang-select">
                <option value="">-- Pilih Barang --</option>
                <?php foreach ($itemCatalog as $it): ?>
                    <option value="<?= (int) $it['id'] ?>"
                            data-name="<?= e($it['item_name']) ?>"
                            data-unit="<?= e($it['unit_name'] ?? '') ?>"
                            data-cat-id="<?= (int) ($it['category_id'] ?? 0) ?>"
                            data-cat-name="<?= e($catMap[(int) ($it['category_id'] ?? 0)] ?? '') ?>"
                        <?= $curItemId === (int) $it['id'] ? 'selected' : '' ?>>
                        <?= e($it['item_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="item_id[]" class="item-id-input" value="<?= $curItemId ?: '' ?>">
    </td>
    <td data-label="Uraian">
        <input type="text" name="item_uraian[]" class="form-control form-control-sm uraian-input"
               value="<?= e($item['uraian'] ?? '') ?>" placeholder="mis. Pembelian kabel" required>
    </td>
    <td class="stock-col" data-label="Kategori" style="width: 160px;">
        <input type="text" class="form-control form-control-sm category-display bg-light" value="<?= e($curCatName) ?>" readonly placeholder="(otomatis)">
        <input type="hidden" name="item_category_id[]" class="category-id-input" value="<?= (int) ($item['category_id'] ?? 0) ?: '' ?>">
    </td>
    <td data-label="Qty" style="width: 100px;">
        <input type="number" name="item_qty[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty'] ?? '') ?>" min="0.01" step="0.01" placeholder="0" required>
    </td>
    <td class="stock-col" data-label="Satuan" style="width: 110px;">
        <input type="text" class="form-control form-control-sm unit-display bg-light" value="<?= e($item['unit'] ?? '') ?>" readonly placeholder="(otomatis)">
        <input type="hidden" name="item_unit[]" class="unit-input" value="<?= e($item['unit'] ?? '') ?>">
    </td>
    <td data-label="Harga Satuan (Rp)" style="width: 160px;">
        <input type="text" name="item_satuan[]" class="form-control form-control-sm price-input currency-input"
               inputmode="numeric" value="<?= e($satuanVal) ?>" placeholder="0" required>
    </td>
    <td data-label="Jumlah" style="width: 160px;" class="text-end subtotal-cell">Rp 0.00</td>
    <td class="text-center cell-remove" style="width: 44px;">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus baris">
            <i class="bi bi-trash"></i><span class="d-md-none ms-1">Hapus baris</span>
        </button>
    </td>
</tr>
