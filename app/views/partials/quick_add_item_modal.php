<?php
/**
 * Modal quick-add Barang. Dipakai dari baris item PO (bisa banyak baris),
 * jadi target <select>-nya di-resolve dinamis lewat window.__quickAddItemTarget
 * (di-set oleh handler tombol "+ Barang" di tiap baris -- lihat _item_row.php).
 * Butuh variabel $itemCategories, $units, dan permission 'item'.'quick_add'.
 */
$itemCategories = $itemCategories ?? [];
$units = $units ?? [];
?>
<div class="modal fade" id="modalQuickAddItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formQuickAddItem">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-seam"></i> Tambah Barang Cepat</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none quick-add-error"></div>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" class="form-control" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Kategori</label>
                            <select name="category_id" class="form-select">
                                <option value="">-- Tanpa kategori --</option>
                                <?php foreach ($itemCategories as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"><?= e($c['category_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Satuan <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <select name="unit_id" id="quickAddItemUnitSelect" class="form-select unit-select" required>
                                    <option value="">-- Pilih Satuan --</option>
                                    <?php foreach ($units as $u): ?>
                                        <option value="<?= (int) $u['id'] ?>"><?= e($u['unit_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (canQuickAdd('unit')): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btnQuickAddUnitFromItem" title="Tambah Satuan Cepat">
                                        <i class="bi bi-plus-lg"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stok Minimum</label>
                            <input type="number" name="min_stock" class="form-control" min="0" step="0.01" placeholder="0">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    initQuickAdd({
        modalId: 'modalQuickAddItem',
        formId: 'formQuickAddItem',
        selectEl: function () { return window.__quickAddItemTarget || null; },
        broadcastSelector: '.item-select',
        endpoint: '<?= BASE_URL ?>/index.php?module=item&action=quickStore',
        extraFill: function (opt, data) {
            opt.dataset.unit = data.unit_name || '';
            opt.dataset.unitId = data.unit_id || '';
            opt.dataset.itemcode = data.item_code || '';
        },
    });

    var btnUnit = document.getElementById('btnQuickAddUnitFromItem');
    if (btnUnit) {
        btnUnit.addEventListener('click', function () {
            window.__quickAddUnitTarget = document.getElementById('quickAddItemUnitSelect');
            var unitModalEl = document.getElementById('modalQuickAddUnit');
            if (unitModalEl) {
                bootstrap.Modal.getOrCreateInstance(unitModalEl).show();
            }
        });
    }
});
</script>

<?php if (canQuickAdd('unit')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_unit_modal.php'; ?>
<?php endif; ?>
