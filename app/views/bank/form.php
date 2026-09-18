<?php
/** @var string $mode @var array|null $row @var array $banks @var array $projects */
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$val = static fn(string $k, $d = '') => e($row[$k] ?? $d);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> Transaksi Bank</h4>
    <a href="<?= BASE_URL ?>/cash" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=bank&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                    <input type="date" name="trx_date" class="form-control" value="<?= $val('trx_date', date('Y-m-d')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Bank <span class="text-danger">*</span></label>
                    <select name="bank_id" class="form-select" required>
                        <option value="">-- Pilih Bank --</option>
                        <?php foreach ($banks as $b): ?>
                            <option value="<?= (int) $b['id'] ?>" <?= (string) ($row['bank_id'] ?? '') === (string) $b['id'] ? 'selected' : '' ?>>
                                <?= e($b['bank_name']) ?> (<?= e(strtoupper($b['jenis'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mutasi <span class="text-danger">*</span></label>
                    <select name="mutasi" class="form-select" required>
                        <option value="">-- Pilih --</option>
                        <option value="masuk" <?= ($row['mutasi'] ?? '') === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                        <option value="keluar" <?= ($row['mutasi'] ?? '') === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nominal (Rp) <span class="text-danger">*</span></label>
                    <input type="text" name="amount" class="form-control" value="<?= $val('amount') ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="">-- Tanpa Project --</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" <?= (string) ($row['project_id'] ?? '') === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">PIC</label>
                    <input type="text" name="pic" class="form-control" value="<?= $val('pic') ?>" placeholder="opsional">
                </div>
                <div class="col-12">
                    <label class="form-label">Uraian <span class="text-danger">*</span></label>
                    <input type="text" name="uraian" class="form-control" value="<?= $val('uraian') ?>" required>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
        <a href="<?= BASE_URL ?>/cash" class="btn btn-light border">Batal</a>
    </div>
</form>
