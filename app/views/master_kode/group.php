<?php
/**
 * Master Kode > {Kelompok}: konfigurasi prefix + daftar kode kelompok ini SAJA.
 * $rows dibaca langsung dari model entity asli (Item/Supplier/dst) -- bukan
 * tabel/list gabungan, sesuai arsitektur di app/models/CodeConfig.php.
 */
$formatPreview = $config
    ? $config['prefix'] . '-' . str_pad((string) $config['next_number'], (int) $config['digit_length'], '0', STR_PAD_LEFT)
    : '-';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Master Kode &raquo; <?= e($entityMeta['label']) ?></h4>
        <small class="text-muted">
            Data <?= e($entityMeta['label']) ?> tetap dikelola di
            <a href="<?= BASE_URL ?>/<?= e($entityMeta['module']) ?>">Master <?= e($entityMeta['label']) ?></a> --
            di sini hanya mengatur pola kodenya.
        </small>
    </div>
    <a href="<?= BASE_URL ?>/master_kode" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Master Kode
    </a>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <h6 class="mb-3">Konfigurasi Prefix</h6>
        <?php if (!$config): ?>
            <div class="alert alert-warning py-2">
                <i class="bi bi-exclamation-triangle"></i>
                Kelompok ini belum dikonfigurasi -- form Tambah <?= e($entityMeta['label']) ?> akan ditolak sampai prefix diatur di sini.
            </div>
        <?php endif; ?>
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=master_kode&action=saveConfig" class="row g-3 align-items-end">
            <?= csrfField() ?>
            <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
            <div class="col-md-3">
                <label class="form-label">Prefix</label>
                <input type="text" name="prefix" class="form-control text-uppercase" value="<?= e($config['prefix'] ?? '') ?>" maxlength="20" required placeholder="mis. BRG">
            </div>
            <div class="col-md-3">
                <label class="form-label">Jumlah Digit Nomor</label>
                <input type="number" name="digit_length" class="form-control" value="<?= (int) ($config['digit_length'] ?? 4) ?>" min="1" max="10" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nomor Berikutnya</label>
                <input type="text" class="form-control" value="<?= $config ? (int) $config['next_number'] : '-' ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label">Format</label>
                <input type="text" class="form-control fw-bold" value="<?= e($formatPreview) ?>" disabled>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/master_kode" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="group">
            <input type="hidden" name="type" value="<?= e($entityType) ?>">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Cari (Kode / Nama)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="mis. SUP-0001, sup 01">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Cari
                </button>
                <a href="<?= BASE_URL ?>/master_kode/group?type=<?= e($entityType) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Kode</th>
                        <th>Nama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="2" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-upc-scan empty-icon"></i>
                                <div class="empty-title">Belum ada data</div>
                                <div class="empty-desc mb-0">Belum ada <?= e($entityMeta['label']) ?> yang cocok dengan pencarian ini.</div>
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
