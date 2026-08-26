<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Tanda Tangan</h4>
    <a href="<?= BASE_URL ?>/index.php?module=signature" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=signature&action=<?= $actionUrl ?>" enctype="multipart/form-data">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $signature['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Pemilik Tanda Tangan <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= e($signature['name'] ?? '') ?>" placeholder="mis. Budi Santoso" required autofocus>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Jabatan <span class="text-danger">*</span></label>
                    <input type="text" name="position" class="form-control"
                           value="<?= e($signature['position'] ?? '') ?>" placeholder="mis. Project Manager" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gambar Tanda Tangan <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?></label>
                    <input type="file" name="signature_image" class="form-control" accept=".jpg,.jpeg,.png" <?= $isEdit ? '' : 'required' ?>>
                    <div class="form-text">Format JPG/PNG, maksimal 2MB. Gunakan gambar latar transparan/putih untuk hasil cetak yang rapi.</div>
                    <?php if ($isEdit && !empty($signature['signature_image'])): ?>
                        <div class="mt-2">
                            <div class="text-muted small mb-1">Gambar saat ini:</div>
                            <img src="<?= BASE_URL ?>/<?= e($signature['signature_image']) ?>" alt="Tanda tangan saat ini"
                                 style="max-width: 160px; max-height: 80px; object-fit: contain;" class="border rounded p-1">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($signature['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Aktif</option>
                        <option value="inactive" <?= ($signature['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                    <div class="form-text">Hanya tanda tangan Aktif yang muncul di dokumen cetak.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/index.php?module=signature" class="btn btn-light border">Batal</a>
    </div>
</form>
