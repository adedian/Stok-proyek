<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Gudang</h4>
        <?php if ($isEdit): ?>
            <small class="text-muted">Kode: <strong><?= e($warehouse['warehouse_code']) ?></strong></small>
        <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=warehouse" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php if (!$isEdit): ?>
    <div class="mb-3">
        <?php $codeEntityType = 'warehouse'; $codeEntityLabel = 'Gudang'; require ROOT_PATH . '/app/views/partials/code_preview.php'; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=warehouse&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $warehouse['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Nama Gudang <span class="text-danger">*</span></label>
                    <input type="text" name="warehouse_name" class="form-control"
                           value="<?= e($warehouse['warehouse_name'] ?? '') ?>" required>
                </div>
                <?php if ($isEdit): ?>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($warehouse['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= ($warehouse['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-6">
                    <label class="form-label">PIC</label>
                    <input type="text" name="pic_name" class="form-control" value="<?= e($warehouse['pic_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($warehouse['phone'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <textarea name="address" class="form-control" rows="2"><?= e($warehouse['address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" <?= (!$isEdit && $codeConfig === null) ? 'disabled' : '' ?>><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/index.php?module=warehouse" class="btn btn-light border">Batal</a>
    </div>
</form>
