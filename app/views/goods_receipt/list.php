<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Penerimaan Barang</h4>
        <small class="text-muted">Daftar seluruh penerimaan barang dari supplier</small>
    </div>
    <?php if (can('goods_receipt', 'create')): ?>
    <a href="<?= BASE_URL ?>/goods_receipt/create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Tambah Penerimaan
    </a>
    <?php endif; ?>
</div>

<?php if (hasRole([ROLE_SUPER_ADMIN])): ?>
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rangeDeleteModal">
            <i class="bi bi-calendar-x"></i> Hapus per Rentang Tanggal
        </button>
    </div>
    <?php $rangeDeleteAction = 'goods_receipt/rangeDelete'; $rangeDeleteLabel = 'Penerimaan Barang';
          require ROOT_PATH . '/app/views/partials/range_delete_modal.php'; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/goods_receipt" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No. Penerimaan / No. PO / Supplier)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Kategori Stok</label>
                <select name="stock_scope" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="proyek" <?= ($filters['stock_scope'] ?? '') === 'proyek' ? 'selected' : '' ?>>Stok Proyek</option>
                    <option value="kantor" <?= ($filters['stock_scope'] ?? '') === 'kantor' ? 'selected' : '' ?>>Stok Kantor</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
                <a href="<?= BASE_URL ?>/goods_receipt" class="btn btn-sm btn-outline-secondary">
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
                        <th>No. Penerimaan</th>
                        <th>Sumber</th>
                        <th>No. PO</th>
                        <th>Pembuat PO</th>
                        <th>Supplier / Pemakai</th>
                        <th>Tanggal</th>
                        <th>Nama Penerima</th>
                        <th class="text-center">Foto</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($receipts)): ?>
                        <tr><td colspan="9" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-box-seam empty-icon"></i>
                                <div class="empty-title">Belum ada penerimaan barang</div>
                                <div class="empty-desc">Catat penerimaan barang dari supplier untuk PO yang sudah disetujui.</div>
                                <?php if (can('goods_receipt', 'create')): ?>
                                <a href="<?= BASE_URL ?>/goods_receipt/create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Penerimaan
                                </a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($receipts as $r): ?>
                        <tr>
                            <td class="fw-semibold"><?= e($r['receipt_number']) ?></td>
                            <td>
                                <?php
                                    $sourceBadge = ['purchase_order' => ['primary', 'PO'], 'offline_purchase' => ['warning text-dark', 'Offline'], 'cash' => ['success', 'Kas'], 'pemakai' => ['info text-dark', 'Pemakai']];
                                    [$sourceClass, $sourceLabel] = $sourceBadge[$r['receipt_type']] ?? ['secondary', $r['receipt_type']];
                                ?>
                                <span class="badge bg-<?= e($sourceClass) ?>"><?= e($sourceLabel) ?></span>
                                <span class="badge bg-<?= $r['stock_scope'] === 'kantor' ? 'secondary' : 'success' ?>">
                                    <?= $r['stock_scope'] === 'kantor' ? 'Kantor' : 'Proyek' ?>
                                </span>
                            </td>
                            <td><?= e($r['po_number']) ?></td>
                            <td><?= e($r['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($r['supplier_name']) ?></td>
                            <td><?= formatTanggal($r['receipt_date']) ?></td>
                            <td><?= e($r['received_by_name'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php if (!empty($r['photo_goods'])): ?>
                                    <a href="<?= BASE_URL ?>/<?= e($r['photo_goods']) ?>" target="_blank">
                                        <i class="bi bi-image text-success fs-5"></i>
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
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/goods_receipt/detail/<?= (int) $r['id'] ?>">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        </li>
                                        <?php if (isPeriodClosed('goods_receipt', $r['receipt_date'])): ?>
                                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-lock-fill"></i> Periode ditutup</span></li>
                                        <?php else: ?>
                                        <?php if ($r['receipt_type'] !== 'pemakai' && can('goods_receipt', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/goods_receipt/edit/<?= (int) $r['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (can('goods_receipt', 'delete')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=goods_receipt&action=delete"
                                                  onsubmit="return confirm('Yakin ingin menghapus penerimaan <?= e($r['receipt_number']) ?>? Stok yang sudah masuk akan dikoreksi kembali.');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
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
