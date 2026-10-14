<?php
/**
 * Partial: satu baris rincian Bank -- HANYA {uraian, amount} (Revisi lanjutan:
 * pola Rincian Kas tapi disederhanakan, Bank tidak punya kategori/barang/
 * project/qty/satuan per baris).
 * Kelas dipakai JS form: .uraian-input .amount-input .btn-remove-row .item-row
 */
$item = $item ?? ['uraian' => '', 'amount' => ''];
$amountVal = ($item['amount'] ?? '') !== '' ? number_format((float) $item['amount'], 2, '.', ',') : '';
?>
<tr class="item-row">
    <td data-label="Uraian">
        <input type="text" name="item_uraian[]" class="form-control form-control-sm uraian-input"
               value="<?= e($item['uraian'] ?? '') ?>" required>
    </td>
    <td data-label="Nominal (Rp)" style="width: 220px;">
        <input type="text" name="item_amount[]" class="form-control form-control-sm amount-input currency-input"
               inputmode="numeric" value="<?= e($amountVal) ?>" placeholder="0" required>
    </td>
    <td class="text-center cell-remove" style="width: 44px;">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus baris">
            <i class="bi bi-trash"></i><span class="d-md-none ms-1">Hapus baris</span>
        </button>
    </td>
</tr>
