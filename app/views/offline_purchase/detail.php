<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= e($purchase['purchase_number']) ?></h4>
        <small class="text-muted">Detail Pembelian Offline</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/offline_purchase/edit/<?= (int) $purchase['id'] ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil"></i> Edit
        </a>
        <a href="<?= BASE_URL ?>/offline_purchase" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-muted small">Status</div>
                        <div class="fw-semibold">
                            <span class="badge bg-<?= e($statusBadgeClass[$purchase['status']] ?? 'secondary') ?>">
                                <?= e($statusLabels[$purchase['status']] ?? $purchase['status']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Tanggal Pembelian</div>
                        <div class="fw-semibold"><?= formatTanggal($purchase['purchase_date']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Supplier</div>
                        <div class="fw-semibold"><?= e($purchase['supplier_name']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Project</div>
                        <div class="fw-semibold"><?= e($purchase['project_name']) ?></div>
                    </div>
                    <?php if (!empty($purchase['notes'])): ?>
                        <div class="col-12">
                            <div class="text-muted small">Keterangan</div>
                            <div><?= e($purchase['notes']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <div class="text-muted small">Bukti Pembelian</div>
                        <?php if (!empty($purchase['proof_file'])): ?>
                            <a href="<?= e(fileUrl($purchase['proof_file'])) ?>" target="_blank">Lihat bukti</a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Foto Barang</div>
                        <?php if (!empty($purchase['photo_file'])): ?>
                            <a href="<?= BASE_URL ?>/<?= e($purchase['photo_file']) ?>" target="_blank">Lihat foto</a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body p-0">
                <table class="table mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Barang</th>
                            <th>Satuan</th>
                            <th class="text-end">Qty</th>
                            <th class="text-end">Harga</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= e($item['item_name']) ?></td>
                                <td><?= e($item['unit']) ?></td>
                                <td class="text-end"><?= number_format((float) $item['qty'], 2, ',', '.') ?></td>
                                <td class="text-end"><?= formatRupiah($item['price']) ?></td>
                                <td class="text-end"><?= formatRupiah($item['subtotal']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold"><?= formatRupiah($purchase['total_amount']) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3"><i class="bi bi-box-seam"></i> Penerimaan Barang</h6>
                <?php if (empty($receipts)): ?>
                    <p class="text-muted small mb-2">Tidak ada penerimaan barang untuk pembelian offline ini.</p>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($receipts as $r): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div>
                                    <a href="<?= BASE_URL ?>/goods_receipt/detail/<?= (int) $r['id'] ?>">
                                        <?= e($r['receipt_number']) ?>
                                    </a>
                                    <span class="text-muted small ms-2"><?= formatTanggal($r['receipt_date']) ?> &middot; Diterima oleh <?= e($r['received_by_name'] ?? '-') ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <div class="alert alert-info small mt-3 mb-0">
                    Pembelian barang di luar PO kini dicatat lewat modul <strong>Kas</strong>.
                    Penerimaan barangnya dibuat dari menu <strong>Penerimaan Barang</strong> &rarr;
                    sumber "Dari Pembelian Kas". Data di sini hanya menampilkan riwayat lama.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-2"><i class="bi bi-diagram-3"></i> Alur Pembelian Offline</h6>
                <p class="small text-muted mb-0">
                    Pembelian Offline &rarr; Penerimaan Barang &rarr; Validasi &rarr; Stok.
                    Barang baru dianggap stok valid setelah item penerimaannya divalidasi
                    "Sesuai/Kurang/Lebih" di modul Validasi.
                </p>
            </div>
        </div>
    </div>
</div>
