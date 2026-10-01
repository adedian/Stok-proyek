<?php
$apprLabel = ['PENDING' => ['warning text-dark', 'Menunggu'], 'APPROVED' => ['success', 'Approved'], 'REJECTED' => ['danger', 'Ditolak']];
$badge = function (?string $st) use ($apprLabel) {
    $b = $apprLabel[$st ?? ''] ?? ['secondary', '-'];
    return '<span class="badge bg-' . e($b[0]) . '">' . e($b[1]) . '</span>';
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Laporan Request Budget</h4>
        <small class="text-muted">Seluruh Request Budget yang sudah diajukan &mdash; approval, data Purchase, dan pengajuan ke Purwati/Nissa</small>
    </div>
    <div class="d-flex gap-2">
        <?php // Format cetak/export mengikuti template Excel Accounting -- menunggu file template. ?>
        <button type="button" class="btn btn-outline-secondary" disabled
                title="Format cetak/export mengikuti template Excel dari Accounting -- menunggu file template.">
            <i class="bi bi-printer"></i> Cetak / Export
        </button>
        <a href="<?= BASE_URL ?>/report" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Laporan</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/request_budget_report" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Cari</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="no. request, pengaju, project, keperluan">
            </div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted mb-1">Dari Tanggal</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted mb-1">Sampai Tanggal</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>"></div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Project</label>
                <select name="project_id" class="form-select form-select-sm"><option value="">Semua Project</option>
                    <?php foreach ($projects as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $filters['project_id'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm"><option value="">Semua Status</option>
                    <?php foreach ($statuses as $slug => $label): ?><option value="<?= e($slug) ?>" <?= $filters['status'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Approval</label>
                <select name="approval" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="all_approved" <?= $filters['approval'] === 'all_approved' ? 'selected' : '' ?>>Kedua approval selesai</option>
                    <option value="pending" <?= $filters['approval'] === 'pending' ? 'selected' : '' ?>>Masih menunggu</option>
                    <option value="rejected" <?= $filters['approval'] === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Purchase (melengkapi/mengajukan)</label>
                <select name="purchase_user_id" class="form-select form-select-sm"><option value="">Semua</option>
                    <?php foreach ($purchaseUsers as $u): ?><option value="<?= (int) $u['id'] ?>" <?= (int) $filters['purchase_user_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Tujuan Pengajuan</label>
                <select name="forward_to" class="form-select form-select-sm"><option value="">Semua</option>
                    <?php foreach (RequestBudget::FORWARD_DESTINATIONS as $d): ?><option value="<?= e($d) ?>" <?= $filters['forward_to'] === $d ? 'selected' : '' ?>><?= e($d) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted mb-1">Vendor</label><input type="text" name="vendor" class="form-control form-control-sm" value="<?= e($filters['vendor']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted mb-1">No. PO</label><input type="text" name="po_number" class="form-control form-control-sm" value="<?= e($filters['po_number']) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label small text-muted mb-1">No. Invoice</label><input type="text" name="invoice_number" class="form-control form-control-sm" value="<?= e($filters['invoice_number']) ?>"></div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Filter"><i class="bi bi-search"></i> Filter</button>
                <a href="<?= BASE_URL ?>/request_budget_report" class="btn btn-sm btn-outline-secondary w-100" title="Reset"><i class="bi bi-x-circle"></i></a>
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
                        <th>No. Request</th><th>Tanggal</th><th>Project</th><th>Pengaju</th><th class="text-end">Budget</th>
                        <th>Approval PM</th><th>Approval Purchase</th><th>PO</th><th>Invoice</th><th>Vendor</th>
                        <th>Diajukan Ke</th><th>Status</th><th class="no-print"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="13" class="p-0"><div class="empty-state"><i class="bi bi-wallet2 empty-icon"></i><div class="empty-title">Tidak ada Request Budget yang sesuai.</div></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td data-label="No. Request"><a href="<?= BASE_URL ?>/request_budget_report/detail/<?= (int) $r['id'] ?>" class="fw-semibold text-decoration-none"><?= e($r['request_number']) ?></a></td>
                            <td data-label="Tanggal"><?= e(formatTanggal($r['request_date'])) ?></td>
                            <td data-label="Project"><?= e($r['project_name']) ?></td>
                            <td data-label="Pengaju"><?= e($r['requester_name']) ?></td>
                            <td data-label="Budget" class="text-end"><?= formatRupiah($r['total_amount']) ?></td>
                            <td data-label="Approval PM"><?= $badge($r['approval_pm']) ?></td>
                            <td data-label="Approval Purchase"><?= $badge($r['approval_purchase']) ?></td>
                            <td data-label="PO"><?= e($r['po_numbers'] ?? '-') ?></td>
                            <td data-label="Invoice"><?= e($r['invoice_numbers'] ?? '-') ?></td>
                            <td data-label="Vendor"><?= e($r['vendors'] ?? '-') ?></td>
                            <td data-label="Diajukan Ke"><?= e($r['forwarded_to'] ?? '-') ?><?= !empty($r['forwarded_by_name']) ? '<div class="small text-muted">oleh ' . e($r['forwarded_by_name']) . '</div>' : '' ?></td>
                            <td data-label="Status"><span class="badge bg-<?= e(RequestBudget::statusBadge($r['status'])) ?>"><?= e(RequestBudget::statusLabel($r['status'])) ?></span></td>
                            <td class="no-print text-end"><a href="<?= BASE_URL ?>/request_budget_report/detail/<?= (int) $r['id'] ?>" class="btn btn-sm btn-outline-primary py-0">Detail</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($rows)): ?>
                    <tfoot><tr class="table-light"><td colspan="4" class="text-end fw-bold">Total Budget (seluruh hasil filter, <?= (int) $pagination['totalRows'] ?> request)</td><td class="text-end fw-bold"><?= formatRupiah($grandTotal) ?></td><td colspan="8"></td></tr></tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/views/partials/pagination.php'; ?>
