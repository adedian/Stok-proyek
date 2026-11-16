<?php
/** Daftar pengajuan -- halaman utama menu "Form Pengajuan". Variabel: $rows, $filters, $pagination, $baseQuery, $statuses */
$canCreate = can('pengajuan_budget', 'create');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Form Pengajuan Budget</h4>
        <small class="text-muted">Permintaan Otorisasi &mdash; rencana pembayaran lintas rekening sumber. Tidak mengubah saldo Bank &amp; tidak membuat transaksi Bank Keluar.</small>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('pengajuan_budget_report', 'view')): ?>
            <a href="<?= BASE_URL ?>/pengajuan_budget_report" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-bar-graph"></i> Laporan</a>
        <?php endif; ?>
        <?php if ($canCreate): ?>
            <a href="<?= BASE_URL ?>/pengajuan_budget/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Buat Pengajuan</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/pengajuan_budget" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Nomor Pengajuan</label>
                <input type="text" name="number" class="form-control form-control-sm" value="<?= e($filters['number']) ?>" placeholder="mis. 001/PB.HME">
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
                <a href="<?= BASE_URL ?>/pengajuan_budget" class="btn btn-sm btn-outline-secondary w-100" title="Reset"><i class="bi bi-x-circle"></i></a>
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
                        <th>No. Pengajuan</th>
                        <th>Tanggal</th>
                        <th>Pengaju</th>
                        <th class="text-end">Baris</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-file-earmark-text empty-icon"></i>
                                <div class="empty-title"><?= array_filter($filters) ? 'Tidak ada pengajuan yang sesuai dengan pencarian.' : 'Belum ada Pengajuan Budget.' ?></div>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/pengajuan_budget/create" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Buat Pengajuan</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $n => $r): $id = (int) $r['id']; ?>
                        <tr>
                            <td data-label="No" class="text-muted"><?= (int) ($pagination['offset'] ?? 0) + $n + 1 ?></td>
                            <td data-label="No. Pengajuan"><a href="<?= BASE_URL ?>/pengajuan_budget/detail/<?= $id ?>" class="fw-semibold text-decoration-none"><?= e($r['pengajuan_number']) ?></a></td>
                            <td data-label="Tanggal"><?= e(formatTanggal($r['pengajuan_date'])) ?></td>
                            <td data-label="Pengaju"><?= e($r['created_by_name']) ?></td>
                            <td data-label="Baris" class="text-end"><?= (int) $r['item_count'] ?></td>
                            <td data-label="Total" class="text-end"><?= e(formatRupiah($r['total_amount'])) ?></td>
                            <td data-label="Status"><span class="badge bg-<?= e(PengajuanBudget::statusBadge($r['status'])) ?>"><?= e(PengajuanBudget::statusLabel($r['status'])) ?></span></td>
                            <td class="text-center no-print" data-label="Aksi">
                                <a href="<?= BASE_URL ?>/pengajuan_budget/detail/<?= $id ?>" class="btn btn-sm btn-outline-secondary" title="Detail"><i class="bi bi-eye"></i></a>
                                <?php if ($r['status'] === PengajuanBudget::DRAFT && can('pengajuan_budget', 'edit')): ?>
                                    <a href="<?= BASE_URL ?>/pengajuan_budget/edit/<?= $id ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
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
