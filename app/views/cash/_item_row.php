<?php
/**
 * Partial: satu baris rincian Kas.
 *
 * $item (opsional): uraian, qty, satuan (= harga satuan Rp), jumlah,
 *   cash_category_id, unit.
 * $cashCategories: Master Kategori Kas aktif (id, category_name, affects_stock,
 *   stock_scope). Kategori ber-affects_stock=1 -> baris MASUK stok, kolom
 *   Satuan aktif & wajib. "Biaya Operasional" (affects_stock=0) -> tidak.
 * $units: Master Satuan aktif (unit_name).
 *
 * Kelas dipakai JS form: .uraian-input .category-select .unit-select .unit-na
 *   .qty-input .price-input .subtotal-cell .btn-remove-row .item-row
 */
$item = $item ?? ['uraian' => '', 'qty' => '', 'satuan' => '', 'jumlah' => '', 'cash_category_id' => null, 'unit' => ''];
$cashCategories = $cashCategories ?? [];
$units          = $units ?? [];

$curCat   = (int) ($item['cash_category_id'] ?? 0);
$curUnit  = (string) ($item['unit'] ?? '');
$satuanVal = ($item['satuan'] ?? '') !== '' ? number_format((float) $item['satuan'], 2, '.', ',') : '';

$curAffects = false;
foreach ($cashCategories as $c) {
    if ((int) $c['id'] === $curCat) {
        $curAffects = (int) $c['affects_stock'] === 1;
        break;
    }
}
?>
<tr class="item-row<?= $curAffects ? ' stock-row' : '' ?>">
    <td data-label="Uraian" style="min-width: 180px;">
        <input type="text" name="item_uraian[]" class="form-control form-control-sm uraian-input"
               value="<?= e($item['uraian'] ?? '') ?>" placeholder="mis. Pembelian kabel" required>
    </td>
    <td data-label="Kategori" style="min-width: 170px;">
        <select name="item_cash_category_id[]" class="form-select form-select-sm category-select" required>
            <option value="">-- Pilih Kategori --</option>
            <?php foreach ($cashCategories as $c): ?>
                <option value="<?= (int) $c['id'] ?>"
                        data-affects-stock="<?= (int) $c['affects_stock'] ?>"
                        data-scope="<?= e($c['stock_scope'] ?? '') ?>"
                    <?= $curCat === (int) $c['id'] ? 'selected' : '' ?>>
                    <?= e($c['category_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="form-text small cat-hint<?= $curAffects ? '' : ' d-none' ?>">
            <i class="bi bi-box-seam"></i> Masuk stok
        </div>
    </td>
    <td data-label="Satuan" style="width: 130px;">
        <select name="item_unit[]" class="form-select form-select-sm unit-select<?= $curAffects ? '' : ' d-none' ?>"
                <?= $curAffects ? '' : 'disabled' ?>>
            <option value="">-- Satuan --</option>
            <?php foreach ($units as $u): ?>
                <option value="<?= e($u['unit_name']) ?>" <?= $curUnit === $u['unit_name'] ? 'selected' : '' ?>><?= e($u['unit_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="unit-na text-muted<?= $curAffects ? ' d-none' : '' ?>">&mdash;</span>
    </td>
    <td data-label="Qty" style="width: 90px;">
        <input type="number" name="item_qty[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty'] ?? '') ?>" min="0.01" step="0.01" placeholder="0" required>
    </td>
    <td data-label="Harga Satuan (Rp)" style="width: 150px;">
        <input type="text" name="item_satuan[]" class="form-control form-control-sm price-input currency-input"
               inputmode="numeric" value="<?= e($satuanVal) ?>" placeholder="0" required>
    </td>
    <td data-label="Jumlah" style="width: 150px;" class="text-end subtotal-cell">Rp 0.00</td>
    <td class="text-center cell-remove" style="width: 44px;">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus baris">
            <i class="bi bi-trash"></i><span class="d-md-none ms-1">Hapus baris</span>
        </button>
    </td>
</tr>
