<?php
/**
 * Modal quick-add Barang. Dipakai dari baris item PO (bisa banyak baris),
 * jadi target <select>-nya di-resolve dinamis lewat window.__quickAddItemTarget
 * (di-set oleh handler tombol "+ Barang" di tiap baris -- lihat _item_row.php).
 * Butuh variabel $units dan permission 'item'.'quick_add'.
 *
 * Kode Barang mengikuti "Jenis Stok" yang dipilih: Stok Proyek -> prefix ITM/LA/..,
 * Stok Lampu -> LMP, Inventory Kantor -> INVT (masing-masing punya sequence sendiri,
 * lihat Master Kode > Barang). Jenis Stok yang dipilih di sini juga yang tersimpan
 * di items.stock_type -- menentukan pengelompokan di Stok Barang / Opname / Laporan.
 */
$units = $units ?? [];

require_once ROOT_PATH . '/app/models/CodeConfig.php';
$__qaCode = new CodeConfig();
$__qaTypes = stockTypeLabels();
$__qaDefaultType = 'stok_proyek';
$qaGroups = [];
foreach ($__qaTypes as $__st => $__stLabel) {
    $__ent = 'item_' . $__st;
    $qaGroups[$__st] = [
        'label'      => $__stLabel,
        'entity'     => $__ent,
        'prefixes'   => $__qaCode->configsForEntity($__ent),
        'masterCode' => $__qaCode->masterCodeForEntity($__ent),
    ];
}
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
                    <div class="mb-3">
                        <label class="form-label">Jenis Stok <span class="text-danger">*</span></label>
                        <select name="stock_type" id="quickAddItemStockType" class="form-select" required>
                            <?php foreach ($qaGroups as $st => $g): ?>
                                <option value="<?= e($st) ?>" <?= $st === $__qaDefaultType ? 'selected' : '' ?>><?= e($g['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Menentukan prefix kode Barang &amp; pengelompokan stok (Stok Barang, Opname, Laporan).</div>
                    </div>
                    <div class="mb-3">
                        <?php foreach ($qaGroups as $st => $g): ?>
                            <div class="qa-code-group" data-stock-type="<?= e($st) ?>" style="<?= $st === $__qaDefaultType ? '' : 'display:none;' ?>">
                                <?php
                                $codePrefixes      = $g['prefixes'];
                                $codeMasterCode    = $g['masterCode'];
                                $codeEntityType    = $g['entity'];
                                $codeEntityLabel   = 'Barang - ' . $g['label'];
                                $codePrefixFieldId = 'quickAddItemPrefix_' . $st;
                                require ROOT_PATH . '/app/views/partials/code_preview.php';
                                ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="row g-3">
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
                    <button type="submit" class="btn btn-primary" id="quickAddItemSubmitBtn"><i class="bi bi-save"></i> Simpan</button>
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

    var itemModalEl = document.getElementById('modalQuickAddItem');
    var stockTypeSel = document.getElementById('quickAddItemStockType');
    var submitBtn = document.getElementById('quickAddItemSubmitBtn');

    // Tampilkan grup prefix/preview sesuai Jenis Stok; hanya prefix grup aktif yang
    // ikut submit (sisanya di-disable). Tombol Simpan mati kalau grup aktif belum
    // punya prefix (belum dikonfigurasi di Master Kode).
    function applyQaStockType() {
        var st = stockTypeSel ? stockTypeSel.value : '<?= e($__qaDefaultType) ?>';
        document.querySelectorAll('.qa-code-group').forEach(function (g) {
            var show = g.dataset.stockType === st;
            g.style.display = show ? '' : 'none';
            g.querySelectorAll('select[name="code_prefix"]').forEach(function (pf) { pf.disabled = !show; });
            if (show) {
                var active = g.querySelector('.js-cp-prefix');
                if (active) active.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        var activeGroup = document.querySelector('.qa-code-group[data-stock-type="' + st + '"]');
        var hasPrefix = !!(activeGroup && activeGroup.querySelector('select[name="code_prefix"]'));
        if (submitBtn) submitBtn.disabled = !hasPrefix;
    }

    if (stockTypeSel) stockTypeSel.addEventListener('change', applyQaStockType);

    if (itemModalEl) {
        // form.reset() setelah quick-add sebelumnya mengembalikan select ke opsi
        // pertama tanpa memicu 'change' -- sinkronkan ulang tiap modal dibuka.
        itemModalEl.addEventListener('shown.bs.modal', applyQaStockType);
    }
    applyQaStockType();
});
</script>

<?php if (canQuickAdd('unit')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_unit_modal.php'; ?>
<?php endif; ?>

<?php /* Tombol "+" prefix di dalam code_preview butuh modal ini ikut termuat.
         Hanya untuk yang boleh atur Master Kode (Super Admin). */ ?>
<?php if (function_exists('can') && can('master_kode', 'edit')): ?>
    <?php require ROOT_PATH . '/app/views/partials/quick_add_prefix_modal.php'; ?>
<?php endif; ?>
