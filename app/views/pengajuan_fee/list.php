<?php
/** Daftar Pengajuan Fee. Variabel: $rows, $filters, $pagination, $baseQuery, $statuses */
$canCreate = can('pengajuan_fee', 'create');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Pengajuan Fee</h4>
        <small class="text-muted">Fee atas invoice (10% dari DPP, dipotong PPh). Alur: Dibuat &rarr; Diperiksa &rarr; Disetujui. Modul terpisah, tidak terhubung ke Pengajuan Budget.</small>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/pengajuan_fee/create" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Buat Pengajuan Fee</a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/pengajuan_fee" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Nomor / Invoice / Customer / Penerima</label>
                <input type="text" name="number" class="form-control form-control-sm" value="<?= e($filters['number']) ?>" placeholder="mis. FEE.HME atau JL0000">
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
                <a href="<?= BASE_URL ?>/pengajuan_fee" class="btn btn-sm btn-outline-secondary w-100" title="Reset"><i class="bi bi-x-circle"></i></a>
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
                        <th>Invoice</th>
                        <th>Customer</th>
                        <th>Penerima Fee</th>
                        <th class="text-end">Total Fee</th>
                        <th class="text-end">Fee Diterima</th>
                        <th>Status</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-percent empty-icon"></i>
                                <div class="empty-title"><?= array_filter($filters) ? 'Tidak ada pengajuan yang sesuai dengan pencarian.' : 'Belum ada Pengajuan Fee.' ?></div>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/pengajuan_fee/create" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Buat Pengajuan Fee</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $n => $r): $id = (int) $r['id']; ?>
                        <tr>
                            <td data-label="No" class="text-muted"><?= (int) ($pagination['offset'] ?? 0) + $n + 1 ?></td>
                            <td data-label="No. Pengajuan"><a href="<?= BASE_URL ?>/pengajuan_fee/detail/<?= $id ?>" class="fw-semibold text-decoration-none"><?= e($r['fee_number']) ?></a></td>
                            <td data-label="Tanggal"><?= e(formatTanggal($r['fee_date'])) ?></td>
                            <td data-label="Invoice"><?= e($r['invoice_number'] ?? '') ?></td>
                            <td data-label="Customer"><?= e($r['customer_name'] ?? '') ?></td>
                            <td data-label="Penerima Fee"><?= e($r['recipient_name'] ?? '') ?></td>
                            <td data-label="Total Fee" class="text-end"><?= e(formatRupiah($r['total_fee'])) ?></td>
                            <td data-label="Fee Diterima" class="text-end"><?= e(formatRupiah($r['net_fee'])) ?></td>
                            <td data-label="Status"><span class="badge bg-<?= e(PengajuanFee::statusBadge($r['status'])) ?>"><?= e(PengajuanFee::statusLabel($r['status'])) ?></span></td>
                            <td class="text-center no-print" data-label="Aksi">
                                <a href="<?= BASE_URL ?>/pengajuan_fee/detail/<?= $id ?>" class="btn btn-sm btn-outline-secondary" title="Detail"><i class="bi bi-eye"></i></a>
                                <?php if ($r['status'] === PengajuanFee::DRAFT && can('pengajuan_fee', 'edit')): ?>
                                    <a href="<?= BASE_URL ?>/pengajuan_fee/edit/<?= $id ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
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
