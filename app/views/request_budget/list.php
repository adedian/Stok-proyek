<?php
$canCreate = can('request_budget', 'create');
$postUrl = fn(string $action) => BASE_URL . '/index.php?module=request_budget&action=' . $action;

// Satu form POST kecil untuk aksi baris (CSRF + konfirmasi). Server tetap memvalidasi ulang.
$rowPost = function (int $id, string $action, string $label, string $icon, ?string $confirm, bool $danger = false) use ($postUrl) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '"' . ($confirm ? ' class="rb-confirm" data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="dropdown-item' . ($danger ? ' text-danger' : '') . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Request Budget</h4>
        <small class="text-muted">Pengajuan budget project &mdash; modul terpisah, tidak membuat transaksi PO/Kas/Stok</small>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/request_budget/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Request Budget
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/request_budget" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>"
                       placeholder="no. request, pengaju, project, keperluan, barang...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">Semua Project</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (int) $filters['project_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Pengaju</label>
                <select name="requester_id" class="form-select form-select-sm">
                    <option value="">Semua Pengaju</option>
                    <?php foreach ($requesters as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $filters['requester_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <?php foreach ($statuses as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $filters['status'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small text-muted mb-1">Dari</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-1">
                <label class="form-label small text-muted mb-1">Sampai</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-6 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Filter"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/request_budget" class="btn btn-sm btn-outline-secondary w-100" title="Reset"><i class="bi bi-x-circle"></i></a>
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
                        <th>No. Request</th>
                        <th>Tanggal</th>
                        <th>Project</th>
                        <th>Pengaju</th>
                        <th class="text-end">Total Budget</th>
                        <th>Status</th>
                        <th>Disetujui Oleh</th>
                        <th>Diteruskan Ke</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="9" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-wallet2 empty-icon"></i>
                                <?php if (array_filter($filters)): ?>
                                    <div class="empty-title">Tidak ada Request Budget yang sesuai dengan pencarian.</div>
                                <?php else: ?>
                                    <div class="empty-title">Belum ada Request Budget.</div>
                                <?php endif; ?>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/request_budget/create" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Tambah Request Budget</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r):
                        $acts = $rbController->availableActions($r);
                        $id = (int) $r['id'];
                    ?>
                        <tr>
                            <td data-label="No. Request">
                                <a href="<?= BASE_URL ?>/request_budget/detail/<?= $id ?>" class="fw-semibold text-decoration-none"><?= e($r['request_number']) ?></a>
                            </td>
                            <td data-label="Tanggal"><?= e(formatTanggal($r['request_date'])) ?></td>
                            <td data-label="Project"><?= e($r['project_name']) ?></td>
                            <td data-label="Pengaju"><?= e($r['requester_name']) ?></td>
                            <td data-label="Total Budget" class="text-end"><?= formatRupiah($r['total_amount']) ?></td>
                            <td data-label="Status"><span class="badge bg-<?= e(RequestBudget::statusBadge($r['status'])) ?>"><?= e(RequestBudget::statusLabel($r['status'])) ?></span></td>
                            <td data-label="Disetujui Oleh"><?= e($r['approved_by_name'] ?? '-') ?><?= !empty($r['approved_by_role']) ? '<div class="small text-muted">' . e($r['approved_by_role']) . '</div>' : '' ?></td>
                            <td data-label="Diteruskan Ke"><?= e($r['forwarded_to'] ?? '-') ?><?= !empty($r['forwarded_by_name']) ? '<div class="small text-muted">oleh ' . e($r['forwarded_by_name']) . '</div>' : '' ?></td>
                            <td class="text-center no-print" data-label="Aksi">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/request_budget/detail/<?= $id ?>"><i class="bi bi-eye"></i> Detail</a></li>
                                        <?php if (!empty($acts['edit'])): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/request_budget/edit/<?= $id ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['revise'])): ?>
                                            <li><?php $rowPost($id, 'revise', 'Revisi', 'bi-arrow-counterclockwise', null); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['print'])): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/request_budget/print?id=<?= $id ?>&autoprint=1" target="_blank"><i class="bi bi-printer"></i> Cetak</a></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['submit'])): ?>
                                            <li><?php $rowPost($id, 'submit', 'Submit', 'bi-send', 'Ajukan Request Budget ' . $r['request_number'] . ' untuk approval?'); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['approve'])): ?>
                                            <li><?php $rowPost($id, 'approve', 'Setujui', 'bi-check-circle', 'Setujui Request Budget ' . $r['request_number'] . '?'); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['reject'])): ?>
                                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/request_budget/detail/<?= $id ?>#aksi-tolak"><i class="bi bi-x-circle"></i> Tolak</a></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['forward'])): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/request_budget/detail/<?= $id ?>#aksi-teruskan"><i class="bi bi-box-arrow-in-right"></i> Teruskan ke Purwati/Nissa</a></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['complete'])): ?>
                                            <li><?php $rowPost($id, 'complete', 'Selesaikan', 'bi-check2-all', 'Selesaikan Request Budget ' . $r['request_number'] . '?'); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['delete'])): ?>
                                            <li><?php $rowPost($id, 'delete', 'Hapus', 'bi-trash', 'Hapus Request Budget ' . $r['request_number'] . '?', true); ?></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/views/partials/pagination.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rb-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) { if (ok) { form.submit(); } });
        });
    });
});
</script>
