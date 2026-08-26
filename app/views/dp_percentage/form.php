<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Persentase DP</h4>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=dp_percentage" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=dp_percentage&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="<?= e($row['name'] ?? '') ?>" placeholder="mis. DP 50%" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Persentase (%) <span class="text-danger">*</span></label>
                    <input type="number" name="percentage" class="form-control" value="<?= e($row['percentage'] ?? '') ?>" min="0.01" max="100" step="0.01" required>
                </div>
            </div>
            <?php if (!$isEdit): ?>
                <div class="form-text mt-2">Persentase baru otomatis berstatus Aktif dan langsung muncul di dropdown Tagihan DP pada Invoice Keluar.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/index.php?module=dp_percentage" class="btn btn-light border">Batal</a>
    </div>
</form>
