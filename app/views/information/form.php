<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Informasi</h4>
    <a href="<?= BASE_URL ?>/information" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=information&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $info['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Judul Informasi <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" maxlength="200"
                           value="<?= e($info['title'] ?? '') ?>" placeholder="mis. Perubahan Jadwal Penerimaan Barang" required autofocus>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Kategori <span class="text-danger">*</span></label>
                    <select name="category" class="form-select" required>
                        <?php foreach ($categories as $slug => $label): ?>
                            <option value="<?= e($slug) ?>" <?= ($info['category'] ?? 'umum') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        <?php foreach ($statuses as $slug => $label): ?>
                            <option value="<?= e($slug) ?>" <?= ($info['status'] ?? 'aktif') === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Hanya informasi berstatus Aktif yang tampil ke pengguna biasa.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tanggal Publikasi <span class="text-danger">*</span></label>
                    <input type="date" name="publish_date" class="form-control"
                           value="<?= e($info['publish_date'] ?? date('Y-m-d')) ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Isi Informasi <span class="text-danger">*</span></label>
                    <textarea name="content" class="form-control" rows="8" placeholder="Tulis keterangan lengkap di sini..." required><?= e($info['content'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/information" class="btn btn-light border">Batal</a>
    </div>
</form>
