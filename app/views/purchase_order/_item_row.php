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
$item = $item ?? ['item_id' => null, 'item_name' => '', 'unit' => '', 'qty_order' => '', 'price' => ''];
$itemCatalog = $itemCatalog ?? [];
$hasItemId = !empty($item['item_id']);
$isLegacyRow = !$hasItemId && $item['item_name'] !== '';

// Prefill Kode Barang + Kategori untuk baris yang sudah punya item_id (mode edit) --
// baris baru diisi otomatis lewat JS saat user memilih barang (lihat listener
// 'change' di form.php). Kategori READ-ONLY, ikut kategori master Barang; kalau
// kategori master kosong/terhapus, pakai snapshot yang tersimpan di baris PO.
$selectedItemCode = '';
$selectedCategory = $item['category'] ?? '';
if ($hasItemId) {
    foreach ($itemCatalog as $it) {
        if ((int) $it['id'] === (int) $item['item_id']) {
            $selectedItemCode = $it['item_code'];
            $selectedCategory = ($it['category_name'] ?? '') !== '' ? $it['category_name'] : ($item['category'] ?? '');
            break;
        }
    }
}
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
                    <option value="<?= (int) $it['id'] ?>" data-unit="<?= e($it['unit_name']) ?>" data-itemcode="<?= e($it['item_code']) ?>" data-category="<?= e($it['category_name'] ?? '') ?>"
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
    <td style="width: 140px;">
        <input type="text" class="form-control form-control-sm category-display" value="<?= e($selectedCategory) ?>" readonly placeholder="-" title="Kategori mengikuti master Barang (tampil di cetak PO)">
        <input type="hidden" name="category[]" class="category-input" value="<?= e($selectedCategory) ?>">
    </td>
    <td style="width: 110px;">
        <input type="text" class="form-control form-control-sm unit-display" value="<?= e($item['unit']) ?>" readonly>
        <input type="hidden" name="unit[]" class="unit-input" value="<?= e($item['unit']) ?>">
    </td>
    <td style="width: 120px;">
        <input type="number" name="qty_order[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty_order']) ?>" min="0.01" step="0.01" placeholder="0" required>
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
