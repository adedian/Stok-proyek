<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Barang</h4>
        <?php if ($isEdit): ?>
            <small class="text-muted">Kode: <strong><?= e($item['item_code']) ?></strong></small>
        <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/item" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=item&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
    <?php endif; ?>

    <?php if (!$isEdit): $__prefixesByType = $codePrefixes; $__masterByType = $codeMasterCodes; ?>
        <?php foreach ($stockTypeLabels as $st => $stLabel): ?>
            <div class="card border-0 shadow-sm mb-3 js-code-preview" data-stock-type="<?= e($st) ?>" style="display: none;">
                <div class="card-body">
                    <?php
                    $codePrefixes   = $__prefixesByType[$st] ?? [];
                    $codeMasterCode = $__masterByType[$st] ?? '';
                    $codeEntityType = 'item_' . $st;
                    $codeEntityLabel = 'Barang - ' . $stLabel;
                    $codePrefixFieldId = 'codePrefix_' . $st;
                    require ROOT_PATH . '/app/views/partials/code_preview.php';
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                    <input type="text" name="item_name" class="form-control"
                           value="<?= e($item['item_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($item['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= ($item['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jenis Stok <span class="text-danger">*</span></label>
                    <select name="stock_type" id="stockTypeSelect" class="form-select" required <?= $isEdit ? 'disabled' : '' ?>>
                        <?php foreach ($stockTypeLabels as $st => $stLabel): ?>
                            <option value="<?= e($st) ?>" <?= ($item['stock_type'] ?? 'stok_proyek') === $st ? 'selected' : '' ?>><?= e($stLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="stock_type" value="<?= e($item['stock_type'] ?? 'stok_proyek') ?>">
                        <div class="form-text">Jenis Stok menentukan kelompok kode (Master Kode &raquo; Barang) saat dibuat, tidak bisa diganti setelah kode terbit.</div>
                    <?php else: ?>
                        <div class="form-text">Menentukan kelompok kode (prefix) dari Master Kode &raquo; Barang.</div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Kategori</label>
                    <select name="category_id" class="form-select">
                        <option value="">-- Tanpa kategori --</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= $isEdit && (int) ($item['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= e($c['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Satuan <span class="text-danger">*</span></label>
                    <select name="unit_id" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"
                                <?= $isEdit && (int) ($item['unit_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['unit_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Stok Minimum</label>
                    <input type="number" name="min_stock" class="form-control" min="0" step="0.01"
                           value="<?= e($item['min_stock'] ?? '0') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Spesifikasi</label>
                    <textarea name="specification" class="form-control" rows="2"><?= e($item['specification'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" id="itemSubmitBtn"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/item" class="btn btn-light border">Batal</a>
    </div>
</form>

<?php if (!$isEdit): ?>
<script>
(function () {
    // Preview kode & tombol Simpan mengikuti Jenis Stok yang dipilih -- tiap
    // kelompok (Stok Proyek/Stok Lampu/Inventory Kantor) punya konfigurasi
    // prefix SENDIRI (lihat Master Kode > Barang), jadi status "belum
    // dikonfigurasi" juga berbeda-beda per kelompok.
    var configured = <?= json_encode(array_map(fn($c) => $c !== null, $codeConfigs)) ?>;
    var select = document.getElementById('stockTypeSelect');
    var submitBtn = document.getElementById('itemSubmitBtn');

    function applyStockTypeState() {
        var current = select.value;
        document.querySelectorAll('.js-code-preview').forEach(function (el) {
            var show = el.dataset.stockType === current;
            el.style.display = show ? '' : 'none';
            // Hanya prefix kelompok yang aktif yang boleh ikut submit.
            var pf = el.querySelector('select[name="code_prefix"]');
            if (pf) pf.disabled = !show;
        });
        submitBtn.disabled = !configured[current];
    }

    select.addEventListener('change', applyStockTypeState);
    applyStockTypeState();
})();
</script>
<?php endif; ?>
