<?php
/** Daftar Reimbursement. Variabel: $rows, $filters, $pagination, $baseQuery, $statuses, $viewAll */
$canCreate = can('reimbursement', 'create');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Reimbursement</h4>
        <small class="text-muted"><?= $viewAll ? 'Seluruh pengajuan reimbursement (akses pemeriksa).' : 'Pengajuan reimbursement milik Anda.' ?> Setelah disetujui Nissa / Purwati, kurang bayar diteruskan otomatis ke Pengajuan Budget.</small>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/reimbursement/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Buat Reimbursement</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/reimbursement" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Nomor / Nama</label>
                <input type="text" name="number" class="form-control form-control-sm" value="<?= e($filters['number']) ?>" placeholder="mis. 001/RMB.HME atau nama">
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
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Dari</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Sampai</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-6 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100" title="Filter"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/reimbursement" class="btn btn-sm btn-outline-secondary w-100" title="Reset"><i class="bi bi-x-circle"></i></a>
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
                        <th>No. Reimbursement</th>
                        <th>Tanggal</th>
                        <th>Nama</th>
                        <th>Tugas</th>
                        <th class="text-end">Total Pengeluaran</th>
                        <th class="text-end">Kasbon (B S)</th>
                        <th class="text-end">Diteruskan</th>
                        <th>Status</th>
                        <th>Pengajuan Budget</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="11" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-receipt empty-icon"></i>
                                <div class="empty-title"><?= array_filter($filters) ? 'Tidak ada reimbursement yang sesuai dengan pencarian.' : 'Belum ada Reimbursement.' ?></div>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/reimbursement/create" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Buat Reimbursement</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $n => $r): $id = (int) $r['id']; ?>
                        <tr>
                            <td data-label="No" class="text-muted"><?= (int) ($pagination['offset'] ?? 0) + $n + 1 ?></td>
                            <td data-label="No. Reimbursement"><a href="<?= BASE_URL ?>/reimbursement/detail/<?= $id ?>" class="fw-semibold text-decoration-none"><?= e($r['reimb_number']) ?></a></td>
                            <td data-label="Tanggal"><?= e(formatTanggal($r['reimb_date'])) ?></td>
                            <td data-label="Nama"><?= e($r['employee_name']) ?><?php if ($viewAll && $r['created_by_name'] && $r['created_by_name'] !== $r['employee_name']): ?><div class="small text-muted">diajukan: <?= e($r['created_by_name']) ?></div><?php endif; ?></td>
                            <td data-label="Tugas"><?= e($r['task'] ?? '') ?></td>
                            <td data-label="Total Pengeluaran" class="text-end"><?= e(formatRupiah($r['total_expense'])) ?></td>
                            <td data-label="Kasbon (B S)" class="text-end"><?= (float) $r['kasbon_amount'] > 0 ? e(formatRupiah($r['kasbon_amount'])) : '<span class="text-muted">-</span>' ?></td>
                            <td data-label="Diteruskan" class="text-end"><?= (float) $r['net_amount'] > 0 ? e(formatRupiah($r['net_amount'])) : '<span class="text-muted">-</span>' ?></td>
                            <td data-label="Status"><span class="badge bg-<?= e(Reimbursement::statusBadge($r['status'])) ?>"><?= e(Reimbursement::statusLabel($r['status'])) ?></span></td>
                            <td data-label="Pengajuan Budget"><?php
                                if (!empty($r['pengajuan_number'])) {
                                    echo can('pengajuan_budget', 'view')
                                        ? '<a href="' . BASE_URL . '/pengajuan_budget/detail/' . (int) $r['pengajuan_budget_id'] . '" class="text-decoration-none">' . e($r['pengajuan_number']) . '</a>'
                                        : e($r['pengajuan_number']);
                                } else { echo '<span class="text-muted">-</span>'; }
                            ?></td>
                            <td class="text-center no-print" data-label="Aksi">
                                <a href="<?= BASE_URL ?>/reimbursement/detail/<?= $id ?>" class="btn btn-sm btn-outline-secondary" title="Detail"><i class="bi bi-eye"></i></a>
                                <?php if ($r['status'] === Reimbursement::DRAFT && can('reimbursement', 'edit') && ((int) $r['created_by'] === (int) currentUserId() || currentUserRole() === ROLE_SUPER_ADMIN)): ?>
                                    <a href="<?= BASE_URL ?>/reimbursement/edit/<?= $id ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require ROOT_PATH . '/app/views/partials/pagination.php'; ?>
