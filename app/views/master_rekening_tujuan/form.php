<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$val = static fn(string $k, $d = '') => e($row[$k] ?? $d);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Rekening Tujuan</h4>
    <a href="<?= BASE_URL ?>/master_rekening_tujuan" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=master_rekening_tujuan&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $row['id'] ?>"><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Penerima <span class="text-danger">*</span></label>
                    <input type="text" name="nama_penerima" class="form-control" maxlength="150" value="<?= $val('nama_penerima') ?>" required autofocus placeholder="mis. Ahmad Soleh">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bank</label>
                    <input type="text" name="bank_tujuan" class="form-control" maxlength="100" value="<?= $val('bank_tujuan') ?>" placeholder="mis. BCA">
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Rekening</label>
                    <input type="text" name="no_rekening" class="form-control" maxlength="60" value="<?= $val('no_rekening') ?>" placeholder="mis. 4240911065">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Keterangan</label>
                    <input type="text" name="keterangan" class="form-control" maxlength="255" value="<?= $val('keterangan') ?>" placeholder="opsional">
                </div>
                <div class="col-md-4">
                    <label class="form-label d-block">Status</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" <?= (($row['is_active'] ?? 1)) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="isActive">Aktif</label>
                    </div>
                </div>
            </div>
            <div class="form-text mt-2">Di Pengajuan Budget tampil sebagai: <em>Nama - Bank No. Rekening</em>.</div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/master_rekening_tujuan" class="btn btn-light border">Batal</a>
    </div>
</form>
