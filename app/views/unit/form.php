<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Satuan</h4>
    <a href="<?= BASE_URL ?>/index.php?module=unit" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=unit&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $unit['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label">Nama Satuan <span class="text-danger">*</span></label>
            <input type="text" name="unit_name" class="form-control"
                   value="<?= e($unit['unit_name'] ?? '') ?>" placeholder="mis. Kg, Zak, M3" required autofocus>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/index.php?module=unit" class="btn btn-light border">Batal</a>
    </div>
</form>
