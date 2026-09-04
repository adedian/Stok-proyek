<?php
/**
 * Partial: satu baris rincian Kas.
 *
 * $item (opsional): uraian, qty, satuan (= harga satuan Rp), jumlah,
 *   cash_category_id, item_id, project_id, supplier_name, unit,
 *   master_item_name, master_item_code (dari byTransaction saat edit).
 * $cashCategories: Master Kategori Kas aktif (id, category_name, affects_stock,
 *   stock_scope). Kategori ber-affects_stock=1 -> baris MASUK stok: kolom
 *   Barang (WAJIB), Satuan & Supplier aktif; kolom Project aktif+wajib hanya
 *   bila stock_scope='proyek'. "Biaya Operasional" (affects_stock=0) -> semua
 *   kolom stok nonaktif.
 * $units: Master Satuan aktif (unit_name).
 * $projects: Project aktif (id, project_name).
 * $itemCatalog: Barang aktif (id, item_code, item_name, unit_name).
 *
 * Kelas dipakai JS form: .uraian-input .category-select .barang-select
 *   .barang-id-input .barang-wrap .barang-na .project-wrap .project-select .supplier-input
 *   .unit-select .proj-na .sup-na .unit-na .qty-input .price-input
 *   .subtotal-cell .btn-remove-row .item-row
 */
$item = $item ?? ['uraian' => '', 'qty' => '', 'satuan' => '', 'jumlah' => '', 'cash_category_id' => null, 'item_id' => null, 'project_id' => null, 'supplier_name' => '', 'unit' => ''];
$cashCategories = $cashCategories ?? [];
$units          = $units ?? [];
$projects       = $projects ?? [];
$itemCatalog    = $itemCatalog ?? [];

$curCat   = (int) ($item['cash_category_id'] ?? 0);
$curItem  = (int) ($item['item_id'] ?? 0);
$curProj  = (int) ($item['project_id'] ?? 0);
$curSup   = (string) ($item['supplier_name'] ?? '');
$curUnit  = (string) ($item['unit'] ?? '');
$satuanVal = ($item['satuan'] ?? '') !== '' ? number_format((float) $item['satuan'], 2, '.', ',') : '';

$curAffects = false;
$curScope   = '';
foreach ($cashCategories as $c) {
    if ((int) $c['id'] === $curCat) {
        $curAffects = (int) $c['affects_stock'] === 1;
        $curScope   = (string) ($c['stock_scope'] ?? '');
        break;
    }
}
$curProyek = $curAffects && $curScope === 'proyek';
// Kolom Project juga tampil (opsional) untuk kategori NON-stok seperti "Biaya
// Operasional" -- supaya biaya bisa dibebankan ke sebuah project. Wajib hanya
// untuk kategori stok ber-scope 'proyek'.
$curNonStockCat  = $curCat > 0 && !$curAffects;
$curShowProject  = $curProyek || $curNonStockCat;

// Barang terpilih tapi tidak ada di katalog aktif (mis. sudah discontinue) ->
// tetap tampilkan sebagai opsi supaya data lama tidak hilang saat edit.
$curItemInCatalog = false;
foreach ($itemCatalog as $it) {
    if ((int) $it['id'] === $curItem) { $curItemInCatalog = true; break; }
}
$canQuickAddItem    = function_exists('canQuickAdd') && canQuickAdd('item');
$canQuickAddProject = function_exists('canQuickAdd') && canQuickAdd('project');
?>
<tr class="item-row<?= $curAffects ? ' stock-row' : '' ?>">
    <td data-label="Uraian" style="min-width: 160px;">
        <input type="text" name="item_uraian[]" class="form-control form-control-sm uraian-input"
               value="<?= e($item['uraian'] ?? '') ?>" required>
    </td>
    <td data-label="Kategori" style="min-width: 160px;">
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
    <td data-label="Barang" style="min-width: 190px;" class="<?= $curAffects ? '' : 'cell-na' ?>">
        <div class="barang-wrap<?= $curAffects ? '' : ' d-none' ?>">
            <div class="input-group input-group-sm">
                <select class="form-select form-select-sm barang-select item-select" <?= $curAffects ? '' : 'disabled' ?> <?= $curAffects ? 'required' : '' ?>>
                    <option value="">-- Pilih Barang --</option>
                    <?php if ($curItem && !$curItemInCatalog): ?>
                        <option value="<?= $curItem ?>" data-unit="<?= e($curUnit) ?>" selected>
                            <?= e(($item['master_item_name'] ?? 'Barang') . ' (' . ($item['master_item_code'] ?? '-') . ')') ?>
                        </option>
                    <?php endif; ?>
                    <?php foreach ($itemCatalog as $it): ?>
                        <option value="<?= (int) $it['id'] ?>"
                                data-unit="<?= e($it['unit_name'] ?? '') ?>"
                                data-code="<?= e($it['item_code'] ?? '') ?>"
                            <?= $curItem === (int) $it['id'] ? 'selected' : '' ?>>
                            <?= e($it['item_name']) ?><?= !empty($it['item_code']) ? ' (' . e($it['item_code']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($canQuickAddItem): ?>
                    <button type="button" class="btn btn-outline-secondary btn-quick-add-item" title="Tambah Barang Cepat">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <input type="hidden" name="item_barang_id[]" class="barang-id-input" value="<?= $curItem ?: '' ?>">
        <span class="barang-na text-muted<?= $curAffects ? ' d-none' : '' ?>">&mdash;</span>
    </td>
    <td data-label="Project" style="min-width: 150px;" class="<?= $curShowProject ? '' : 'cell-na' ?>">
        <div class="project-wrap<?= $curShowProject ? '' : ' d-none' ?>">
            <div class="input-group input-group-sm">
                <select name="item_project_id[]" class="form-select form-select-sm project-select"
                        <?= $curShowProject ? '' : 'disabled' ?> <?= $curProyek ? 'required' : '' ?>>
                    <option value="">-- Pilih Project --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= $curProj === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($canQuickAddProject): ?>
                    <button type="button" class="btn btn-outline-secondary btn-quick-add-project" title="Tambah Project Cepat">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <span class="proj-na text-muted<?= $curShowProject ? ' d-none' : '' ?>">&mdash;</span>
    </td>
    <td data-label="Supplier" style="min-width: 140px;" class="<?= $curAffects ? '' : 'cell-na' ?>">
        <input type="text" name="item_supplier_name[]" class="form-control form-control-sm supplier-input<?= $curAffects ? '' : ' d-none' ?>"
               value="<?= e($curSup) ?>" <?= $curAffects ? '' : 'disabled' ?>>
        <span class="sup-na text-muted<?= $curAffects ? ' d-none' : '' ?>">&mdash;</span>
    </td>
    <td data-label="Satuan" style="width: 120px;" class="<?= $curAffects ? '' : 'cell-na' ?>">
        <select name="item_unit[]" class="form-select form-select-sm unit-select<?= $curAffects ? '' : ' d-none' ?>"
                <?= $curAffects ? '' : 'disabled' ?>>
            <option value="">-- Satuan --</option>
            <?php foreach ($units as $u): ?>
                <option value="<?= e($u['unit_name']) ?>" <?= $curUnit === $u['unit_name'] ? 'selected' : '' ?>><?= e($u['unit_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="unit-na text-muted<?= $curAffects ? ' d-none' : '' ?>">&mdash;</span>
    </td>
    <td data-label="Qty" style="width: 85px;">
        <input type="number" name="item_qty[]" class="form-control form-control-sm qty-input"
               value="<?= e($item['qty'] ?? '') ?>" min="0.01" step="0.01" placeholder="0" required>
    </td>
    <td data-label="Harga Satuan (Rp)" style="width: 140px;">
        <input type="text" name="item_satuan[]" class="form-control form-control-sm price-input currency-input"
               inputmode="numeric" value="<?= e($satuanVal) ?>" placeholder="0" required>
    </td>
    <td data-label="Jumlah" style="width: 140px;" class="text-end subtotal-cell">Rp 0.00</td>
    <td class="text-center cell-remove" style="width: 44px;">
        <button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus baris">
            <i class="bi bi-trash"></i><span class="d-md-none ms-1">Hapus baris</span>
        </button>
    </td>
</tr>
