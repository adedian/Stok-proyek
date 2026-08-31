<?php
/**
 * Master Kode > {Kelompok}: Master Code + daftar prefix (multi) + daftar kode.
 * Format kode: PREFIX.NOMOR.MASTERCODE (mis. ME.0001.ITM).
 * $configs   : array baris code_configs untuk kelompok ini
 * $masterCode: string
 * $rows      : data entity (Item/Supplier/dst) untuk daftar kode
 */
function mkPreview(array $c, string $mc): string
{
    $num = str_pad((string) $c['next_number'], (int) $c['digit_length'], '0', STR_PAD_LEFT);
    $mc = trim((string) ($c['master_code'] ?? $mc));
    return $c['prefix'] . '.' . $num . ($mc !== '' ? '.' . $mc : '');
}
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Master Kode &raquo; <?= e($entityMeta['label']) ?></h4>
        <small class="text-muted">
            Data <?= e($entityMeta['label']) ?> dikelola di
            <a href="<?= BASE_URL ?>/<?= e($entityMeta['module']) ?>">Master <?= e($entityMeta['label']) ?></a> &mdash;
            di sini hanya pola kode: <code>PREFIX.NOMOR.MASTERCODE</code>.
        </small>
    </div>
    <a href="<?= BASE_URL ?>/master_kode" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Master Kode
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Master Code (kode akhir)</h6>
                <form method="POST" action="<?= BASE_URL ?>/master_kode/saveMasterCode" class="row g-2 align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
                    <div class="col-8">
                        <label class="form-label small mb-1">Kode</label>
                        <input type="text" name="master_code" class="form-control text-uppercase"
                               value="<?= e($masterCode) ?>" maxlength="10" placeholder="mis. ITM" required>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i></button>
                    </div>
                    <div class="col-12"><div class="form-text">Berlaku untuk semua prefix kelompok ini.</div></div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Tambah Prefix</h6>
                <form method="POST" action="<?= BASE_URL ?>/master_kode/addPrefix" class="row g-2 align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Prefix</label>
                        <input type="text" name="prefix" class="form-control text-uppercase" maxlength="20" required placeholder="mis. ME">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label small mb-1">Jumlah Digit</label>
                        <input type="number" name="digit_length" class="form-control" value="4" min="1" max="10" required>
                    </div>
                    <div class="col-sm-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg"></i> Tambah</button>
                    </div>
                </form>
                <div class="form-text mt-2">
                    Prefix yang sama tidak boleh dobel di kelompok ini. Prefix sama boleh dipakai di kelompok lain.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="mb-3">Daftar Prefix</h6>
        <?php if (empty($configs)): ?>
            <div class="text-muted small">Belum ada prefix. Tambahkan di atas &mdash; form Tambah <?= e($entityMeta['label']) ?> akan ditolak sampai ada minimal 1 prefix.</div>
        <?php endif; ?>
        <?php foreach ($configs as $c): ?>
            <div class="d-flex flex-wrap align-items-end gap-2 py-2 border-bottom">
                <form method="POST" action="<?= BASE_URL ?>/master_kode/updatePrefix" class="d-flex flex-wrap align-items-end gap-2 flex-grow-1">
                    <?= csrfField() ?>
                    <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                    <div>
                        <label class="form-label small mb-0">Prefix</label>
                        <input type="text" name="prefix" class="form-control form-control-sm text-uppercase" style="width: 120px;"
                               value="<?= e($c['prefix']) ?>" maxlength="20" required>
                    </div>
                    <div>
                        <label class="form-label small mb-0">Digit</label>
                        <input type="number" name="digit_length" class="form-control form-control-sm" style="width: 90px;"
                               value="<?= (int) $c['digit_length'] ?>" min="1" max="10" required>
                    </div>
                    <div class="text-muted small">
                        Nomor berikutnya: <strong><?= (int) $c['next_number'] ?></strong><br>
                        Contoh: <code><?= e(mkPreview($c, $masterCode)) ?></code>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-save"></i> Simpan</button>
                </form>
                <?php if ((int) $c['next_number'] <= 1): ?>
                    <form method="POST" action="<?= BASE_URL ?>/master_kode/deletePrefix"
                          onsubmit="return confirm('Hapus prefix <?= e($c['prefix']) ?>?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus prefix"><i class="bi bi-trash"></i></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/master_kode/group" class="row g-2 align-items-end">
            <input type="hidden" name="type" value="<?= e($entityType) ?>">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Cari (Kode / Nama)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="mis. ME.0001, sup 01">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i> Cari</button>
                <a href="<?= BASE_URL ?>/master_kode/group?type=<?= e($entityType) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Kode</th><th>Nama</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="2" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-upc-scan empty-icon"></i>
                                <div class="empty-title">Belum ada data</div>
                                <div class="empty-desc mb-0">Belum ada <?= e($entityMeta['label']) ?> yang cocok.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><code><?= e($r[$entityMeta['code_col']]) ?></code></td>
                            <td><?= e($r[$entityMeta['name_col']]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/views/partials/pagination.php'; ?>
