<?php
/** @var array $rows @var array $filters @var array $summary @var array $banks @var array $projects */
$rp = static fn($v) => number_format((float) $v, 0, ',', '.');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Bank</h4>
        <small class="text-muted">Transaksi Bank (Loan / HR) &mdash; khusus Accounting</small>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <a href="<?= BASE_URL ?>/cash" class="btn btn-outline-secondary btn-sm"><i class="bi bi-cash-coin"></i> Kas</a>
        <a href="<?= BASE_URL ?>/bank" class="btn btn-dark btn-sm"><i class="bi bi-bank"></i> Bank</a>
        <a href="<?= BASE_URL ?>/bank/report" class="btn btn-outline-dark"><i class="bi bi-file-text"></i> Laporan Bank</a>
        <?php if (can('bank', 'create')): ?>
            <a href="<?= BASE_URL ?>/bank/create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Transaksi
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Masuk</div>
                <div class="fs-5 fw-bold text-success">Rp <?= $rp($summary['masuk']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Keluar</div>
                <div class="fs-5 fw-bold text-danger">Rp <?= $rp($summary['keluar']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/bank" class="row g-2 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">Semua Project</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $filters['project_id'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Mutasi</label>
                <select name="mutasi" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="masuk" <?= $filters['mutasi'] === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                    <option value="keluar" <?= $filters['mutasi'] === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Bank</label>
                <select name="bank_ids[]" class="form-select form-select-sm" multiple size="1">
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= in_array((int) $b['id'], $filters['bank_ids'], true) ? 'selected' : '' ?>><?= e($b['bank_name']) ?> (<?= e(strtoupper($b['jenis'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Kosongkan = semua Bank. Ctrl/Cmd+klik untuk pilih beberapa.</div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> Filter</button>
                <a href="<?= BASE_URL ?>/bank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i> Reset</a>
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
                        <th>Tanggal</th>
                        <th>No Bukti</th>
                        <th>Bank</th>
                        <th>Project</th>
                        <th>PIC</th>
                        <th>Uraian</th>
                        <th class="text-end">Nominal</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-bank empty-icon"></i>
                                <div class="empty-title">Belum ada transaksi Bank</div>
                                <div class="empty-desc">Tambahkan transaksi Bank pertama.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= e(formatTanggal($r['trx_date'])) ?></td>
                            <td><?= e($r['no_bukti']) ?></td>
                            <td><?= e($r['bank_name']) ?> <span class="badge bg-info-subtle text-info-emphasis"><?= e(strtoupper($r['bank_jenis'])) ?></span></td>
                            <td><?= e($r['project_name'] ?? '-') ?></td>
                            <td><?= e($r['pic'] ?? '-') ?></td>
                            <td><?= e($r['uraian']) ?></td>
                            <td class="text-end <?= $r['mutasi'] === 'masuk' ? 'text-success' : 'text-danger' ?>">
                                <?= $r['mutasi'] === 'masuk' ? '+' : '-' ?> Rp <?= $rp($r['amount']) ?>
                            </td>
                            <td class="text-center no-print">
                                <?php if (can('bank', 'edit') || can('bank', 'delete')): ?>
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" title="Aksi"><i class="bi bi-three-dots-vertical"></i></button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (can('bank', 'edit')): ?>
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/bank/edit/<?= (int) $r['id'] ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                        <?php endif; ?>
                                        <?php if (can('bank', 'delete')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=bank&action=delete" class="js-confirm-delete" data-message="Hapus transaksi <?= e($r['no_bukti']) ?>?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Hapus</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php else: ?><span class="text-muted">&mdash;</span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) { if (ok) form.submit(); });
        });
    });
});
</script>
