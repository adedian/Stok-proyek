<?php
$canCreate = can('sales_order', 'create');
$canProcess = can('sales_order', 'process');
$canEdit = can('sales_order', 'edit');
$canPrint = can('sales_order', 'print');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Order Penjualan</h4>
        <small class="text-muted">Pesanan customer berdasarkan PO Customer &mdash; stok, Invoice Keluar, dan Surat Jalan terhubung ke order</small>
    </div>
    <?php if ($canCreate): ?>
        <a href="<?= BASE_URL ?>/sales_order/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Tambah Order Penjualan
        </a>
    <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/sales_order" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small text-muted mb-1">Cari (No. Order / No. PO / Customer / Barang)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="ketik potongan nomor / nama...">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Customer</label>
                <select name="client_id" class="form-select form-select-sm">
                    <option value="">Semua Customer</option>
                    <?php foreach ($clients as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['client_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['client_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Status Order</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach (SalesOrder::STATUS_LABELS as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $filters['status'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Status Stok</label>
                <select name="stock_status" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach (SalesOrder::STOCK_LABELS as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $filters['stock_status'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
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
                <a href="<?= BASE_URL ?>/sales_order" class="btn btn-sm btn-outline-secondary w-100" title="Reset"><i class="bi bi-x-circle"></i></a>
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
                        <th>No. Order</th>
                        <th>Tanggal</th>
                        <th>Customer</th>
                        <th>PO Customer</th>
                        <th class="text-end">Total Pesanan</th>
                        <th>Status Stok</th>
                        <th>Status Order</th>
                        <th>Pembuat</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="10" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-bag-check empty-icon"></i>
                                <?php if (array_filter($filters)): ?>
                                    <div class="empty-title">Tidak ada Order Penjualan yang sesuai dengan pencarian.</div>
                                <?php else: ?>
                                    <div class="empty-title">Belum ada Order Penjualan.</div>
                                    <div class="empty-desc">Catat pesanan customer berdasarkan PO yang diterima.</div>
                                <?php endif; ?>
                                <?php if ($canCreate): ?>
                                    <a href="<?= BASE_URL ?>/sales_order/create" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle"></i> Tambah Order Penjualan</a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $n => $r):
                        $id = (int) $r['id'];
                        $st = $r['display_status'];
                        $open = !in_array($st, ['draft', 'dibatalkan'], true);
                    ?>
                        <tr>
                            <td data-label="No" class="text-muted"><?= (int) ($pagination['offset'] ?? 0) + $n + 1 ?></td>
                            <td data-label="No. Order">
                                <a href="<?= BASE_URL ?>/sales_order/detail/<?= $id ?>" class="fw-semibold text-decoration-none"><?= e($r['order_number']) ?></a>
                                <div class="small text-muted"><?= (int) $r['line_count'] ?> barang &middot; <?= e(rtrim(rtrim(number_format((float) $r['ordered_qty'], 2, ',', '.'), '0'), ',')) ?> unit</div>
                            </td>
                            <td data-label="Tanggal"><?= e(formatTanggal($r['order_date'])) ?></td>
                            <td data-label="Customer"><?= e($r['client_name']) ?></td>
                            <td data-label="PO Customer">
                                <div class="fw-semibold"><?= e($r['po_number']) ?></div>
                                <div class="small text-muted"><?= e(formatTanggal($r['po_date'])) ?></div>
                            </td>
                            <td data-label="Total Pesanan" class="text-end"><?= e(formatRupiah($r['total_amount'])) ?></td>
                            <td data-label="Status Stok">
                                <?php if ($r['stock_status']): ?>
                                    <span class="badge bg-<?= e(SalesOrder::stockBadge($r['stock_status'])) ?>"><?= e(SalesOrder::stockLabel($r['stock_status'])) ?></span>
                                    <?php if ($open && (float) $r['remaining_qty'] > SalesOrder::EPS && (float) $r['fulfilled_qty'] > SalesOrder::EPS): ?>
                                        <div class="small text-muted">sisa <?= e(rtrim(rtrim(number_format((float) $r['remaining_qty'], 2, ',', '.'), '0'), ',')) ?> unit</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status Order">
                                <span class="badge bg-<?= e(SalesOrder::statusBadge($st)) ?>"><?= e(SalesOrder::statusLabel($st)) ?></span>
                            </td>
                            <td data-label="Pembuat"><?= e($r['created_by_name'] ?? '-') ?></td>
                            <td class="text-center no-print" data-label="Aksi">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="<?= BASE_URL ?>/sales_order/detail/<?= $id ?>"><i class="bi bi-eye"></i> Detail</a></li>
                                        <?php if ($canProcess && $open): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/sales_order/process/<?= $id ?>"><i class="bi bi-box-arrow-up"></i> Proses Pemenuhan</a></li>
                                        <?php endif; ?>
                                        <?php if ($canEdit && $st !== 'dibatalkan' && ($st === 'draft' || (float) $r['fulfilled_qty'] <= SalesOrder::EPS && (float) $r['invoiced_qty'] <= SalesOrder::EPS || currentUserRole() === ROLE_SUPER_ADMIN)): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/sales_order/edit/<?= $id ?>"><i class="bi bi-pencil"></i> Edit</a></li>
                                        <?php endif; ?>
                                        <?php if ($canPrint && $st !== 'dibatalkan'): ?>
                                            <li><a class="dropdown-item" href="<?= BASE_URL ?>/sales_order/print/<?= $id ?>" target="_blank"><i class="bi bi-printer"></i> Cetak</a></li>
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
