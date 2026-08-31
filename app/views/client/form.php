<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Client</h4>
        <?php if ($isEdit): ?>
            <small class="text-muted">Kode: <strong><?= e($client['client_code']) ?></strong></small>
        <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/client" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php if (!$isEdit): ?>
    <div class="mb-3">
        <?php $codeEntityType = 'client'; $codeEntityLabel = 'Client'; require ROOT_PATH . '/app/views/partials/code_preview.php'; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=client&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $client['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Client <span class="text-danger">*</span></label>
                    <input type="text" name="client_name" class="form-control"
                           value="<?= e($client['client_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">PIC</label>
                    <input type="text" name="contact_person" class="form-control"
                           value="<?= e($client['contact_person'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($client['phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($client['email'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <textarea name="address" class="form-control" rows="2"><?= e($client['address'] ?? '') ?></textarea>
                </div>
                <?php if ($isEdit): ?>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($client['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                            <option value="inactive" <?= ($client['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" <?= (!$isEdit && $codeConfig === null) ? 'disabled' : '' ?>>
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/client" class="btn btn-light border">Batal</a>
    </div>
</form>
