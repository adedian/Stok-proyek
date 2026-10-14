<?php
/**
 * Partial: satu baris jasa pada PO berkategori JASA.
 * Variabel opsional: $item (array baris PO), $index (int), $units (daftar Satuan master, hanya untuk saran).
 *
 * Beda dari _item_row.php (Barang): TANPA dropdown master Barang, TANPA Kode/Kategori.
 * "Deskripsi Jasa" teks bebas (disimpan di purchase_order_items.item_name, item_id selalu NULL);
 * Satuan teks bebas + saran dari master Satuan (mis. Paket, Hari, Ls). Nama field input sama
 * dengan baris Barang sehingga collectPoInput() dan perhitungan Qty/Harga/Diskon/PPN dipakai bersama.
 */
$item = $item ?? ['item_name' => '', 'unit' => '', 'qty_order' => '', 'price' => '', 'discount_percent' => 0, 'ppn_enabled' => false, 'ppn_percent' => null];
?>
<tr class="item-row service-row">
    <td>
        <input type="text" name="item_name[]" class="form-control form-control-sm"
               value="<?= e($item['item_name']) ?>" maxlength="200" placeholder="Uraian jasa / pekerjaan" required>
        <input type="hidden" name="item_id[]" value="">
    </td>
    <td style="width: 130px;">
        <input type="text" name="unit[]" class="form-control form-control-sm" list="poServiceUnitOptions"
               value="<?= e($item['unit']) ?>" maxlength="30" placeholder="mis. Paket" autocomplete="off">
    </td>
    <td style="width: 110px;">
        <input type="text" inputmode="decimal" name="qty_order[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty_order']) ?>" placeholder="0" required>
    </td>
    <td style="width: 215px;">
        <div class="input-group input-group-sm">
            <span class="input-group-text po-currency-label"><?= e(currencyPrefix($poCurrency ?? 'IDR')) ?></span>
            <input type="text" name="price[]" class="form-control form-control-sm price-input currency-input"
                   inputmode="decimal" value="<?= e($item['price'] !== '' ? number_format((float) $item['price'], 2, '.', ',') : '') ?>" placeholder="0" required>
        </div>
    </td>
    <td style="width: 90px;">
        <input type="text" inputmode="decimal" name="discount_percent[]" class="form-control form-control-sm discount-input"
               value="<?= e((string) ($item['discount_percent'] ?: '')) ?>" placeholder="0" title="Diskon (%)">
    </td>
    <td style="width: 110px;">
        <div class="input-group input-group-sm">
            <span class="input-group-text p-1">
                <input type="checkbox" class="form-check-input ppn-toggle-visible mt-0"
                       <?= !empty($item['ppn_enabled']) ? 'checked' : '' ?> title="Pakai PPN">
            </span>
            <input type="hidden" name="ppn_enabled[]" class="ppn-enabled-input" value="<?= !empty($item['ppn_enabled']) ? '1' : '' ?>">
            <?php $ppnOnRow = !empty($item['ppn_enabled']); $ppnValRow = ($ppnOnRow && $item['ppn_percent'] !== null) ? (string) $item['ppn_percent'] : ''; ?>
            <input type="hidden" name="ppn_percent[]" class="ppn-percent-hidden" value="<?= e($ppnValRow) ?>">
            <input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm ppn-percent-input"
                   value="<?= e($ppnValRow) ?>" placeholder="%" <?= $ppnOnRow ? '' : 'disabled' ?>>
        </div>
    </td>
    <td style="width: 170px;" class="text-end subtotal-cell"><?= e(formatMoney(0, $poCurrency ?? 'IDR')) ?></td>
    <td style="width: 50px;" class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
            <i class="bi bi-trash"></i>
        </button>
    </td>
</tr>
