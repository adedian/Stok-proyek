<?php
/**
 * Partial: satu baris input item PO.
 * Variabel opsional: $item (array baris PO), $index (int).
 * Variabel wajib dari pemanggil: $itemCatalog (daftar Barang aktif, untuk dropdown).
 *
 * "Nama Barang" sekarang dropdown (pilih dari katalog Barang) + quick-add,
 * bukan lagi input teks bebas -- tapi item_name[]/unit[] yang dikirim ke server
 * TETAP string biasa (lihat hidden input di bawah), jadi collectPoInput()/saveItems()
 * di controller tidak perlu berubah sama sekali.
 */
$item = $item ?? ['item_id' => null, 'item_name' => '', 'unit' => '', 'qty_order' => '', 'price' => '', 'discount_percent' => 0, 'ppn_enabled' => false, 'ppn_percent' => null];
$itemCatalog = $itemCatalog ?? [];
$hasItemId = !empty($item['item_id']);
$isLegacyRow = !$hasItemId && $item['item_name'] !== '';

// Kode Barang: READ-ONLY, ikut master Barang (diisi JS saat pilih Barang).
// Kategori: teks BEBAS / catatan per baris PO -- TIDAK terhubung ke kategori di
// Master Data > Barang. Diisi/diubah manual, hanya tampil di cetak PO.
$selectedItemCode = '';
if ($hasItemId) {
    foreach ($itemCatalog as $it) {
        if ((int) $it['id'] === (int) $item['item_id']) {
            $selectedItemCode = $it['item_code'];
            break;
        }
    }
}
$selectedCategory = $item['category'] ?? '';
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
                    <option value="<?= (int) $it['id'] ?>" data-unit="<?= e($it['unit_name']) ?>" data-itemcode="<?= e($it['item_code']) ?>"
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
    <td style="width: 110px;">
        <input type="text" class="form-control form-control-sm code-display" value="<?= e($selectedItemCode) ?>" readonly placeholder="-">
    </td>
    <td style="width: 150px;">
        <input type="text" name="category[]" class="form-control form-control-sm category-input"
               list="poCategoryOptions" value="<?= e($selectedCategory) ?>" placeholder="Kategori (opsional)"
               title="Pilih dari daftar atau ketik sendiri -- catatan bebas, tidak terhubung ke Master Data Barang. Hanya tampil di cetak PO.">
    </td>
    <td style="width: 110px;">
        <input type="text" class="form-control form-control-sm unit-display" value="<?= e($item['unit']) ?>" readonly>
        <input type="hidden" name="unit[]" class="unit-input" value="<?= e($item['unit']) ?>">
    </td>
    <td style="width: 110px;">
        <input type="text" inputmode="decimal" name="qty_order[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty_order']) ?>" placeholder="0" required>
    </td>
    <td style="width: 150px;">
        <input type="text" name="price[]" class="form-control form-control-sm price-input currency-input"
               inputmode="numeric" value="<?= e($item['price'] !== '' ? number_format((float) $item['price'], 2, '.', ',') : '') ?>" placeholder="0" required>
    </td>
    <td style="width: 90px;">
        <input type="text" inputmode="decimal" name="discount_percent[]" class="form-control form-control-sm discount-input"
               value="<?= e((string) ($item['discount_percent'] ?: '')) ?>" placeholder="0" title="Diskon (%)">
    </td>
    <td style="width: 110px;">
        <!-- Checkbox visible TANPA name (tidak pernah ikut submit) -- state sebenarnya
             disinkronkan JS ke hidden input ppn_enabled[] supaya array ini SELALU
             punya 1 entri per baris (checkbox yang unchecked normalnya TIDAK ikut
             ter-submit sama sekali oleh browser -- itu akan menggeser index array
             antar baris kalau dipakai langsung sebagai name="ppn_enabled[]"). -->
        <div class="input-group input-group-sm">
            <span class="input-group-text p-1">
                <input type="checkbox" class="form-check-input ppn-toggle-visible mt-0"
                       <?= !empty($item['ppn_enabled']) ? 'checked' : '' ?> title="Pakai PPN">
            </span>
            <input type="hidden" name="ppn_enabled[]" class="ppn-enabled-input" value="<?= !empty($item['ppn_enabled']) ? '1' : '' ?>">
            <!-- Input persen TANPA name + disabled saat PPN tidak dicentang: input
                 disabled tidak ikut ter-submit dan itu menggeser index array antar
                 baris. Nilainya disalin JS ke hidden ppn_percent[] (selalu 1 entri
                 per baris; kosong kalau PPN mati). Server tetap validasi ulang. -->
            <?php $ppnOnRow = !empty($item['ppn_enabled']); $ppnValRow = ($ppnOnRow && $item['ppn_percent'] !== null) ? (string) $item['ppn_percent'] : ''; ?>
            <input type="hidden" name="ppn_percent[]" class="ppn-percent-hidden" value="<?= e($ppnValRow) ?>">
            <input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm ppn-percent-input"
                   value="<?= e($ppnValRow) ?>" placeholder="%" <?= $ppnOnRow ? '' : 'disabled' ?>>
        </div>
    </td>
    <td style="width: 150px;" class="text-end subtotal-cell">Rp 0.00</td>
    <td style="width: 50px;" class="text-center">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">
            <i class="bi bi-trash"></i>
        </button>
    </td>
</tr>
