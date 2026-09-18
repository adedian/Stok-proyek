<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$val = static fn(string $k, $d = '') => e($row[$k] ?? $d);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Bank</h4>
    <a href="<?= BASE_URL ?>/master_bank" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=master_bank&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Kode Bank <span class="text-danger">*</span></label>
                    <input type="text" name="bank_code" class="form-control" value="<?= $val('bank_code') ?>" required autofocus>
                </div>
                <div class="col-md-8">
                    <label class="form-label">Nama Bank <span class="text-danger">*</span></label>
                    <input type="text" name="bank_name" class="form-control" value="<?= $val('bank_name') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Jenis <span class="text-danger">*</span></label>
                    <select name="jenis" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <?php foreach ($jenisLabels as $k => $l): ?>
                            <option value="<?= e($k) ?>" <?= ($row['jenis'] ?? '') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                               <?= (($row['is_active'] ?? 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Aktif</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Keterangan</label>
                    <textarea name="keterangan" class="form-control" rows="2"><?= $val('keterangan') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/master_bank" class="btn btn-light border">Batal</a>
    </div>
</form>
