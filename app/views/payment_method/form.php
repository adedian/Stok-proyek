<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Metode Pembayaran</h4>
    <a href="<?= BASE_URL ?>/payment_method" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=payment_method&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $method['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <label class="form-label">Nama Metode Pembayaran <span class="text-danger">*</span></label>
            <input type="text" name="method_name" class="form-control"
                   value="<?= e($method['method_name'] ?? '') ?>" required autofocus>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/payment_method" class="btn btn-light border">Batal</a>
    </div>
</form>
