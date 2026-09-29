<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Pembayaran Invoice</h4>
        <small class="text-muted">Uang masuk dari client per termin Invoice Keluar</small>
    </div>
    <?php if (can('sales_invoice_payment', 'create')): ?>
        <a href="<?= BASE_URL ?>/sales_invoice_payment/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Pembayaran
        </a>
    <?php endif; ?>
</div>

<?php if (hasRole([ROLE_SUPER_ADMIN])): ?>
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rangeDeleteModal">
            <i class="bi bi-calendar-x"></i> Hapus per Rentang Tanggal
        </button>
    </div>
    <?php $rangeDeleteAction = 'sales_invoice_payment/rangeDelete'; $rangeDeleteLabel = 'Pembayaran Invoice';
          require ROOT_PATH . '/app/views/partials/range_delete_modal.php'; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/sales_invoice_payment" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Cari (No. Kwitansi / No. Invoice / Client)</label>
                <input type="text" name="keyword" class="form-control form-control-sm"
                       value="<?= e($filters['keyword']) ?>" placeholder="potongan no kwitansi / no invoice / client">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Dari</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Sampai</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/sales_invoice_payment" class="btn btn-sm btn-outline-secondary">
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
                        <th>No. Kwitansi</th>
                        <th>No. Invoice</th>
                        <th>Client</th>
                        <th>Termin</th>
                        <th class="text-end">Nominal</th>
                        <th>Tanggal</th>
                        <th class="text-center">Bukti</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="p-0">
                                <div class="empty-state">
                                    <i class="bi bi-receipt empty-icon"></i>
                                    <div class="empty-title">Belum ada pembayaran</div>
                                    <div class="empty-desc">Catat pembayaran yang diterima dari client untuk termin invoice yang berjalan.</div>
                                    <?php if (can('sales_invoice_payment', 'create')): ?>
                                        <a href="<?= BASE_URL ?>/sales_invoice_payment/create" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle"></i> Tambah Pembayaran
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($pay['payment_number']) ?></td>
                            <td><?= e($pay['invoice_number']) ?></td>
                            <td><?= e($pay['client_name']) ?></td>
                            <td><?= e($pay['term_label']) ?> (<?= formatPercent($pay['percentage']) ?>%)</td>
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
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/sales_invoice/detail/<?= (int) $pay['sales_invoice_id'] ?>">
                                                <i class="bi bi-eye"></i> Lihat Invoice
                                            </a>
                                        </li>
                                        <?php if (isPeriodClosed('sales_invoice_payment', $pay['payment_date'])): ?>
                                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-lock-fill"></i> Periode ditutup</span></li>
                                        <?php else: ?>
                                        <?php if (can('sales_invoice_payment', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/sales_invoice_payment/edit/<?= (int) $pay['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (can('sales_invoice_payment', 'delete')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=sales_invoice_payment&action=delete"
                                                  onsubmit="return confirm('Yakin ingin menghapus pembayaran <?= e($pay['payment_number']) ?>?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $pay['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
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
