<?php /** @var array $rows @var array $filters @var array $categories @var array $picOptions @var bool $scoped @var array $summary */ ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Kas</h4>
        <small class="text-muted">
            Catatan kas masuk &amp; kas keluar
            <?php if ($scoped): ?><span class="badge bg-light text-dark border ms-1">PIC terkait Anda</span><?php endif; ?>
        </small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=cash&action=report" class="btn btn-outline-secondary">
            <i class="bi bi-journal-text"></i> Laporan Kas
        </a>
        <?php if (can('cash', 'create')): ?>
            <a href="<?= BASE_URL ?>/index.php?module=cash&action=create" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Tambah Kas
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2">
                <div class="text-muted small">Total Kas Masuk</div>
                <div class="fs-5 fw-bold text-success"><?= formatRupiah($summary['masuk']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2">
                <div class="text-muted small">Total Kas Keluar</div>
                <div class="fs-5 fw-bold text-danger"><?= formatRupiah($summary['keluar']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body py-2">
                <div class="text-muted small">Selisih (Masuk &minus; Keluar)</div>
                <div class="fs-5 fw-bold"><?= formatRupiah($summary['masuk'] - $summary['keluar']) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="cash">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">PIC</label>
                <?php if (!empty($picOptions)): ?>
                    <select name="pic" class="form-select form-select-sm">
                        <option value="">Semua PIC</option>
                        <?php foreach ($picOptions as $p): ?>
                            <option value="<?= e($p) ?>" <?= $filters['pic'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="pic" class="form-control form-control-sm" value="<?= e($filters['pic']) ?>" placeholder="Nama PIC">
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Kategori</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['category_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Mutasi</label>
                <select name="mutasi" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="masuk"  <?= $filters['mutasi'] === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                    <option value="keluar" <?= $filters['mutasi'] === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/index.php?module=cash" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
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
                        <th style="width:44px;">No</th>
                        <th>Tanggal</th>
                        <th>PIC</th>
                        <th>No Bukti</th>
                        <th>Kategori</th>
                        <th>Mutasi</th>
                        <th class="text-end">Nominal (Rp)</th>
                        <th>Dibuat Oleh</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-cash-coin empty-icon"></i>
                                <div class="empty-title">Belum ada transaksi Kas</div>
                                <div class="empty-desc">Catat kas masuk atau kas keluar untuk mulai.</div>
                                <?php if (can('cash', 'create')): ?>
                                    <a href="<?= BASE_URL ?>/index.php?module=cash&action=create" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tambah Kas
                                    </a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= formatTanggal($r['trx_date']) ?></td>
                            <td><?= e($r['pic']) ?></td>
                            <td><?= e($r['no_bukti']) ?></td>
                            <td><?= e($r['category_name']) ?></td>
                            <td>
                                <?php if ($r['mutasi'] === 'masuk'): ?>
                                    <span class="badge text-bg-success">Masuk</span>
                                <?php else: ?>
                                    <span class="badge text-bg-danger">Keluar</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end fw-semibold"><?= formatRupiah($r['total_amount']) ?></td>
                            <td><?= e($r['created_by_name'] ?? '-') ?></td>
                            <td class="text-center no-print">
                                <?php if (can('cash', 'edit') || can('cash', 'delete')): ?>
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (can('cash', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=cash&action=edit&id=<?= (int) $r['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (can('cash', 'delete')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=cash&action=delete"
                                                  class="js-confirm-delete" data-message="Hapus transaksi Kas <?= e($r['no_bukti']) ?> ke Tempat Sampah?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash"></i> Hapus</button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
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
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });
});
</script>
