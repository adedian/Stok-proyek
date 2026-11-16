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
                <div class="col-md-6">
                    <label class="form-label">No. Rekening</label>
                    <input type="text" name="no_rekening" class="form-control" maxlength="60" value="<?= $val('no_rekening') ?>" placeholder="mis. 050 8676 789">
                    <div class="form-text">Tampil di Permintaan Otorisasi (Pengajuan Budget) sebagai Rekening Sumber.</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Warna Laporan</label>
                    <?php $warnaVal = $row['warna'] ?? ''; ?>
                    <div class="d-flex align-items-center gap-2">
                        <input type="color" name="warna" id="mbWarna" class="form-control form-control-color" value="<?= e($warnaVal ?: '#FFFF00') ?>" <?= $warnaVal ? '' : 'disabled' ?>>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" id="mbWarnaAuto" <?= $warnaVal ? '' : 'checked' ?>>
                            <label class="form-check-label small" for="mbWarnaAuto">Otomatis</label>
                        </div>
                    </div>
                    <div class="form-text">Warna blok rekening di Permintaan Otorisasi.</div>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Catatan di Blok Saldo</label>
                    <input type="text" name="catatan_saldo" class="form-control" maxlength="255" value="<?= $val('catatan_saldo') ?>" placeholder="mis. Note : pembayaran bunga di tanggal 25 setiap bulannya">
                    <div class="form-text">Catatan yang tampil di samping baris saldo rekening ini pada laporan (opsional).</div>
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
<script>
(function () {
    var auto = document.getElementById('mbWarnaAuto'), color = document.getElementById('mbWarna');
    if (!auto || !color) { return; }
    auto.addEventListener('change', function () { color.disabled = auto.checked; });
})();
</script>
