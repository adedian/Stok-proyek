<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= e($receipt['receipt_number']) ?></h4>
        <small class="text-muted">Detail Penerimaan Barang</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=goods_receipt&action=print&id=<?= (int) $receipt['id'] ?>"
           class="btn btn-outline-dark" target="_blank">
            <i class="bi bi-printer"></i> Cetak
        </a>
        <?php if ($receipt['receipt_type'] !== 'pemakai'): ?>
        <a href="<?= BASE_URL ?>/index.php?module=goods_receipt&action=edit&id=<?= (int) $receipt['id'] ?>"
           class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/index.php?module=goods_receipt" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small">Sumber</div>
                        <div class="fw-semibold">
                            <?php if ($receipt['receipt_type'] === 'pemakai'): ?>
                                <span class="badge bg-info text-dark">Pemakai/Internal</span>
                            <?php elseif ($receipt['receipt_type'] === 'offline_purchase'): ?>
                                <span class="badge bg-warning text-dark">Pembelian Offline</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Purchase Order</span>
                            <?php endif; ?>
                            <span class="badge bg-<?= $receipt['stock_scope'] === 'kantor' ? 'secondary' : 'success' ?>">
                                <?= $receipt['stock_scope'] === 'kantor' ? 'Stok Kantor' : 'Stok Proyek' ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($receipt['receipt_type'] === 'pemakai'): ?>
                        <div class="col-md-4">
                            <div class="text-muted small">Nama Pemakai/Departemen</div>
                            <div class="fw-semibold"><?= e($receipt['source_detail'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Project</div>
                            <div class="fw-semibold"><?= e($receipt['project_name'] ?? '-') ?></div>
                        </div>
                    <?php elseif ($receipt['receipt_type'] === 'offline_purchase'): ?>
                        <div class="col-md-4">
                            <div class="text-muted small">No. Pembelian Offline</div>
                            <div class="fw-semibold">
                                <a href="<?= BASE_URL ?>/index.php?module=offline_purchase&action=detail&id=<?= (int) $receipt['offline_purchase_id'] ?>">
                                    <?= e($receipt['po_number']) ?>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Supplier</div>
                            <div class="fw-semibold"><?= e($receipt['supplier_name']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Project</div>
                            <div class="fw-semibold"><?= e($receipt['project_name']) ?></div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-4">
                            <div class="text-muted small">No. PO</div>
                            <div class="fw-semibold"><?= e($receipt['po_number']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Pembuat PO</div>
                            <div class="fw-semibold"><?= e($receipt['pembuat_po'] ?? '-') ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Supplier</div>
                            <div class="fw-semibold"><?= e($receipt['supplier_name']) ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-muted small">Project</div>
                            <div class="fw-semibold"><?= e($receipt['project_name']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-4">
                        <div class="text-muted small">Tanggal Datang</div>
                        <div class="fw-semibold"><?= formatTanggal($receipt['receipt_date']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Nama Penerima</div>
                        <div class="fw-semibold"><?= e($receipt['received_by_name'] ?? '-') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Foto Barang</div>
                        <?php if (!empty($receipt['photo_goods'])): ?>
                            <a href="<?= BASE_URL ?>/<?= e($receipt['photo_goods']) ?>" target="_blank">Lihat foto</a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Invoice</div>
                        <?php if (!empty($receipt['invoice_file'])): ?>
                            <a href="<?= BASE_URL ?>/<?= e($receipt['invoice_file']) ?>" target="_blank"><i class="bi bi-file-earmark-text"></i> Lihat Invoice</a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($receipt['notes'])): ?>
                        <div class="col-12">
                            <div class="text-muted small">Catatan</div>
                            <div><?= e($receipt['notes']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Barang Pesanan</th>
                            <th>Barang Aktual Diterima</th>
                            <th>Satuan</th>
                            <th class="text-end">Qty PO</th>
                            <th class="text-end">Qty Diterima</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Stok</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['po_item_name'] ?? '-') ?></td>
                                <td>
                                    <?= e($item['item_name']) ?>
                                    <?php if (!empty($item['is_item_mismatch'])): ?>
                                        <span class="badge bg-dark ms-1">Beda dari PO</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($item['unit']) ?></td>
                                <td class="text-end"><?= $item['qty_order'] !== null ? number_format((float) $item['qty_order'], 2, ',', '.') : '-' ?></td>
                                <td class="text-end"><?= number_format((float) $item['qty_received'], 2, ',', '.') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-<?= e($statusBadgeClass[$item['comparison_status']]) ?>">
                                        <?= e($statusLabels[$item['comparison_status']]) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($item['stock_posted_at'])): ?>
                                        <span class="badge bg-success" title="Sudah masuk stok">Masuk Stok</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary" title="Menunggu validasi">Menunggu Validasi</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($documents)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-2">Dokumen Surat Jalan</h6>
                <?php foreach ($documents as $doc): ?>
                    <a href="<?= BASE_URL ?>/<?= e($doc['file_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary me-2 mb-2">
                        <i class="bi bi-file-earmark-text"></i> <?= e($doc['document_number'] ?? 'Lihat Dokumen') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-2"><i class="bi bi-info-circle"></i> Ringkasan Selisih</h6>
                <?php
                    $countKurang = count(array_filter($items, fn($i) => $i['comparison_status'] === 'kurang'));
                    $countLebih  = count(array_filter($items, fn($i) => $i['comparison_status'] === 'lebih'));
                    $countSalah  = count(array_filter($items, fn($i) => $i['comparison_status'] === 'barang_lain'));
                    $countSesuai = count(array_filter($items, fn($i) => $i['comparison_status'] === 'sesuai'));
                ?>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-1"><span class="badge bg-success">&nbsp;</span> Sesuai: <?= $countSesuai ?> item</li>
                    <li class="mb-1"><span class="badge bg-warning">&nbsp;</span> Kurang: <?= $countKurang ?> item</li>
                    <li class="mb-1"><span class="badge bg-danger">&nbsp;</span> Lebih: <?= $countLebih ?> item</li>
                    <li><span class="badge bg-dark">&nbsp;</span> Barang Lain: <?= $countSalah ?> item</li>
                </ul>
                <?php if ($countKurang > 0 || $countLebih > 0 || $countSalah > 0): ?>
                    <div class="alert alert-warning small mt-3 mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        Ada selisih pada penerimaan ini. Silakan tindak lanjuti di modul Validasi Barang.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
