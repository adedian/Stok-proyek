<?php
$canCreate = can('request_po', 'create');
$postUrl = fn(string $action) => BASE_URL . '/index.php?module=request_po&action=' . $action;

// Satu form POST kecil untuk aksi baris (CSRF + konfirmasi). Server tetap memvalidasi ulang.
$rowPost = function (int $id, string $action, string $label, string $icon, ?string $confirm, bool $danger = false) use ($postUrl) {
    echo '<form method="POST" action="' . e($postUrl($action)) . '"' . ($confirm ? ' class="rpo-confirm" data-message="' . e($confirm) . '"' : '') . '>'
        . csrfField() . '<input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="dropdown-item' . ($danger ? ' text-danger' : '') . '"><i class="bi ' . e($icon) . '"></i> ' . e($label) . '</button></form>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Request PO</h4>
        <small class="text-muted">Permintaan pembuatan PO &mdash; modul terpisah, tidak membuat PO/Request Budget/Invoice/Kas otomatis</small>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/request_po/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Request PO
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/request_po" class="row g-2 align-items-end">
            <div class="col-12 col-md-2">
                <label class="form-label small text-muted mb-1">Nomor Request PO</label>
                <input type="text" name="number" class="form-control form-control-sm" value="<?= e($filters['number']) ?>" placeholder="mis. RPO-0001">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Requester</label>
                <input type="text" name="requester" class="form-control form-control-sm" value="<?= e($filters['requester']) ?>" placeholder="nama requester">
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
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
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
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Filter"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/request_po" class="btn btn-sm btn-outline-secondary w-100" title="Reset"><i class="bi bi-x-circle"></i></a>
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
                        <th style="width:50px">No</th>
                        <th>Request PO</th>
                        <th>Tanggal</th>
                        <th>Requester</th>
                        <th>Project</th>
                        <th class="text-end">Jumlah Barang</th>
                        <th>Status</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-file-earmark-text empty-icon"></i>
                                <?php if (array_filter($filters)): ?>
                                    <div class="empty-title">Tidak ada Request PO yang sesuai dengan pencarian.</div>
                                <?php else: ?>
                                    <div class="empty-title">Belum ada Request PO.</div>
                                <?php endif; ?>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/request_po/create" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Tambah Request PO</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $n => $r):
                        $acts = $rpController->availableActions($r);
                        $id = (int) $r['id'];
                    ?>
                        <tr>
                            <td data-label="No" class="text-muted"><?= (int) ($pagination['offset'] ?? 0) + $n + 1 ?></td>
                            <td data-label="Request PO">
                                <a href="<?= BASE_URL ?>/request_po/detail/<?= $id ?>" class="fw-semibold text-decoration-none"><?= e($r['request_po_number']) ?></a>
                                <div class="small text-muted text-truncate" style="max-width:260px"><?= e($r['purpose']) ?></div>
                            </td>
                            <td data-label="Tanggal"><?= e(formatTanggal($r['request_date'])) ?></td>
                            <td data-label="Requester"><?= e($r['requester_name']) ?></td>
                            <td data-label="Project"><?= e($r['project_name']) ?></td>
                            <td data-label="Jumlah Barang" class="text-end"><?= (int) $r['item_count'] ?> item</td>
                            <td data-label="Status"><span class="badge bg-<?= e(RequestPo::statusBadge($r['status'])) ?>"><?= e(RequestPo::statusLabel($r['status'])) ?></span></td>
                            <td class="text-center no-print" data-label="Aksi">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/request_po/detail/<?= $id ?>"><i class="bi bi-eye"></i> Detail</a></li>
                                        <?php if (!empty($acts['edit'])): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/request_po/edit/<?= $id ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['submit'])): ?>
                                            <li><?php $rowPost($id, 'submit', 'Submit', 'bi-send', 'Submit Request PO ' . $r['request_po_number'] . ' untuk approval?'); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['approve'])): ?>
                                            <li><?php $rowPost($id, 'approve', 'Setujui', 'bi-check-circle', 'Setujui Request PO ' . $r['request_po_number'] . '?'); ?></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['reject'])): ?>
                                            <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/request_po/detail/<?= $id ?>#aksi"><i class="bi bi-x-circle"></i> Tolak</a></li>
                                        <?php endif; ?>
                                        <?php if (!empty($acts['delete'])): ?>
                                            <li><?php $rowPost($id, 'delete', 'Hapus', 'bi-trash', 'Hapus Request PO ' . $r['request_po_number'] . '?', true); ?></li>
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
    document.querySelectorAll('.rpo-confirm').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, lanjutkan').then(function (ok) { if (ok) { form.submit(); } });
        });
    });
});
</script>
