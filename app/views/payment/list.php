<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Pembayaran</h4>
        <small class="text-muted">Daftar seluruh pembayaran ke supplier</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/payment/summary" class="btn btn-outline-secondary">
            <i class="bi bi-pie-chart"></i> Rekap PO
        </a>
        <a href="<?= BASE_URL ?>/payment/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Pembayaran
        </a>
    </div>
</div>

<!-- Kartu ringkasan status pembayaran PO -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 p-3 me-3">
                    <i class="bi bi-hourglass fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">PO Belum Dibayar</div>
                    <div class="fs-4 fw-bold"><?= (int) $summary['pending'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3">
                    <i class="bi bi-pie-chart-fill fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">PO Dibayar Sebagian</div>
                    <div class="fs-4 fw-bold"><?= (int) $summary['partial'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3">
                    <i class="bi bi-check-circle-fill fs-3"></i>
                </div>
                <div>
                    <div class="text-muted small">PO Lunas</div>
                    <div class="fs-4 fw-bold"><?= (int) $summary['paid'] ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/payment" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Cari (No. Pembayaran / No. PO / Supplier)</label>
                <input type="text" name="keyword" class="form-control form-control-sm"
                       value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/payment" class="btn btn-sm btn-outline-secondary">
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
                        <th>No. Pembayaran</th>
                        <th>No. PO</th>
                        <th>Pembuat PO</th>
                        <th>Supplier</th>
                        <th class="text-center">Termin</th>
                        <th>Sumber Dana</th>
                        <th>Metode</th>
                        <th class="text-end">Nominal</th>
                        <th>Tanggal</th>
                        <th class="text-center">Bukti</th>
                        <th class="text-center">Status</th>
                        <th style="min-width: 140px;">Progress PO</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="12" class="p-0">
                                <div class="empty-state">
                                    <i class="bi bi-credit-card empty-icon"></i>
                                    <div class="empty-title">Belum ada pembayaran</div>
                                    <div class="empty-desc">Catat pembayaran termin ke supplier untuk PO yang berjalan.</div>
                                    <a href="<?= BASE_URL ?>/payment/create" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle"></i> Tambah Pembayaran
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($pay['payment_number']) ?></td>
                            <td><?= e($pay['po_number']) ?></td>
                            <td><?= e($pay['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($pay['supplier_name']) ?></td>
                            <td class="text-center">Ke-<?= (int) $pay['termin'] ?></td>
                            <td>
                                <span class="badge text-bg-light border">
                                    <?= e($fundingSourceLabels[$pay['funding_source']] ?? $pay['funding_source']) ?>
                                </span>
                            </td>
                            <td><?= e($pay['method_name'] ?? '-') ?></td>
                            <td class="text-end"><?= formatRupiah($pay['amount']) ?></td>
                            <td><?= formatTanggal($pay['payment_date']) ?></td>
                            <td class="text-center">
                                <?php if (!empty($pay['proof_file'])): ?>
                                    <a href="<?= e(fileUrl($pay['proof_file'])) ?>" target="_blank" title="Lihat bukti">
                                        <i class="bi bi-file-earmark-check text-success fs-5"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$pay['status']] ?? 'secondary') ?>">
                                    <?= e($statusLabels[$pay['status']] ?? $pay['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-between small mb-1">
                                    <span class="text-muted"><?= formatRupiah($pay['po_total_paid']) ?> / <?= formatRupiah($pay['total_amount']) ?></span>
                                    <span class="fw-semibold"><?= number_format($pay['po_payment_percentage'], 1, ',', '.') ?>%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-<?= $pay['po_payment_percentage'] >= 100 ? 'success' : 'primary' ?>"
                                         role="progressbar" style="width: <?= (float) $pay['po_payment_percentage'] ?>%"
                                         aria-valuenow="<?= (float) $pay['po_payment_percentage'] ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if (isPeriodClosed('payment', $pay['payment_date'])): ?>
                                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-lock-fill"></i> Periode ditutup</span></li>
                                        <?php else: ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/payment/edit/<?= (int) $pay['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=payment&action=delete"
                                                  onsubmit="return confirm('Yakin ingin menghapus pembayaran <?= e($pay['payment_number']) ?>?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $pay['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </li>
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
