<?php
/**
 * Partial: satu baris rincian item Kas.
 * Var opsional: $item (array: uraian, qty, satuan, jumlah), $index (int).
 * Kelas .item-row/.qty-input/.price-input/.subtotal-cell/.btn-remove-row
 * dipakai oleh JS penghitung total di cash/form.php (pola PO / Pembelian Offline).
 * data-label pada tiap <td> dipakai layout kartu di mobile (lihat <style> di form.php).
 */
$item = $item ?? ['uraian' => '', 'qty' => '', 'satuan' => '', 'jumlah' => ''];
$satuanVal = ($item['satuan'] ?? '') !== '' ? number_format((float) $item['satuan'], 2, '.', ',') : '';
?>
<tr class="item-row">
    <td data-label="Uraian">
        <input type="text" name="item_uraian[]" class="form-control form-control-sm"
               value="<?= e($item['uraian'] ?? '') ?>" placeholder="mis. Pembelian kabel" required>
    </td>
    <td data-label="Qty" style="width: 110px;">
        <input type="number" name="item_qty[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty'] ?? '') ?>" min="0.01" step="0.01" placeholder="0" required>
    </td>
    <td data-label="Satuan (Rp)" style="width: 170px;">
        <input type="text" name="item_satuan[]" class="form-control form-control-sm price-input currency-input"
               inputmode="numeric" value="<?= e($satuanVal) ?>" placeholder="0" required>
    </td>
    <td data-label="Jumlah" style="width: 170px;" class="text-end subtotal-cell">Rp 0.00</td>
    <td class="text-center cell-remove" style="width: 44px;">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus baris">
            <i class="bi bi-trash"></i><span class="d-md-none ms-1">Hapus baris</span>
        </button>
    </td>
</tr>
