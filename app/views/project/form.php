<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Project</h4>
        <?php if ($isEdit): ?>
            <small class="text-muted">Kode: <strong><?= e($project['project_code']) ?></strong></small>
        <?php endif; ?>
    </div>
    <a href="<?= BASE_URL ?>/index.php?module=project" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<?php if (!$isEdit): ?>
    <div class="mb-3">
        <?php $codeEntityType = 'project'; $codeEntityLabel = 'Project'; require ROOT_PATH . '/app/views/partials/code_preview.php'; ?>
    </div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=project&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label">Nama Project <span class="text-danger">*</span></label>
                    <input type="text" name="project_name" class="form-control"
                           value="<?= e($project['project_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Lokasi</label>
                    <input type="text" name="location" class="form-control" value="<?= e($project['location'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">PIC Project</label>
                    <select name="pm_id" class="form-select">
                        <option value="">-- Belum ditentukan --</option>
                        <?php foreach ($picUsers as $u): ?>
                            <option value="<?= (int) $u['id'] ?>"
                                <?= $isEdit && (int) ($project['pm_id'] ?? 0) === (int) $u['id'] ? 'selected' : '' ?>>
                                <?= e($u['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="planning" <?= ($project['status'] ?? 'planning') === 'planning' ? 'selected' : '' ?>>Planning</option>
                        <option value="ongoing" <?= ($project['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        <option value="closed" <?= ($project['status'] ?? '') === 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" <?= (!$isEdit && $codeConfig === null) ? 'disabled' : '' ?>>
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/index.php?module=project" class="btn btn-light border">Batal</a>
    </div>
</form>
