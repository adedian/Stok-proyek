<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Pengeluaran Barang</h4>
        <small class="text-muted">Daftar barang yang keluar dari gudang, termasuk mutasi antar gudang</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/delivery_note" class="btn btn-outline-secondary">
            <i class="bi bi-truck"></i> <span class="d-sm-none">Riwayat</span><span class="d-none d-sm-inline">Riwayat Surat Jalan</span>
        </a>
        <?php if (can('stock_out', 'create')): ?>
        <a href="<?= BASE_URL ?>/stock_out/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> <span class="d-sm-none">Tambah</span><span class="d-none d-sm-inline">Tambah Pengeluaran</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (hasRole([ROLE_SUPER_ADMIN])): ?>
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rangeDeleteModal">
            <i class="bi bi-calendar-x"></i> Hapus per Rentang Tanggal
        </button>
    </div>
    <?php $rangeDeleteAction = 'stock_out/rangeDelete'; $rangeDeleteLabel = 'Pengeluaran Barang';
          require ROOT_PATH . '/app/views/partials/range_delete_modal.php'; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/stock_out" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Cari (Barang / Tujuan / PIC)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="ketik potongan kode / nama...">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">Semua Project</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $filters['project_id'] === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= e($p['project_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Gudang (asal / tujuan)</label>
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">Semua Gudang</option>
                    <?php foreach ($warehouses ?? [] as $w): ?>
                        <option value="<?= (int) $w['id'] ?>" <?= (string) ($filters['warehouse_id'] ?? '') === (string) $w['id'] ? 'selected' : '' ?>><?= e($w['warehouse_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Jenis Tujuan</label>
                <select name="destination_type" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="project" <?= ($filters['destination_type'] ?? '') === 'project' ? 'selected' : '' ?>>Project</option>
                    <option value="client" <?= ($filters['destination_type'] ?? '') === 'client' ? 'selected' : '' ?>>Client</option>
                    <option value="gudang" <?= ($filters['destination_type'] ?? '') === 'gudang' ? 'selected' : '' ?>>Gudang (Mutasi)</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> <span class="d-md-none">Cari</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (can('delivery_note', 'view')): ?>
<div class="d-flex justify-content-between align-items-center mb-2 no-print">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="soSelectAll">
        <label class="form-check-label small" for="soSelectAll">Centang Semua</label>
    </div>
    <div class="d-flex gap-2">
        <?php if (can('delivery_note', 'create')): ?>
            <button type="button" class="btn btn-sm btn-primary" id="soCreateDeliveryNoteBtn" disabled>
                <i class="bi bi-truck"></i> Buat Surat Jalan
            </button>
            <?php if (can('sales_order', 'process')): ?>
                <a href="<?= BASE_URL ?>/delivery_note/createFromOrder" class="btn btn-sm btn-success">
                    <i class="bi bi-cart-check"></i> Surat Jalan dari Order Penjualan
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-dark" id="soPrintDeliveryNoteBtn" disabled>
            <i class="bi bi-printer"></i> Cetak Surat Jalan Terpilih
        </button>
    </div>
</div>
<div class="small text-muted mb-2 no-print" id="soSelectHint"></div>
<?php endif; ?>

<style>
    /* Desktop/tablet: kolom diberi lebar wajar & kolom pendek tidak dipecah ke banyak baris (tabel scroll ke samping bila sempit). HP tetap tampilan kartu. */
    @media (min-width: 768px) {
        table.so-list { min-width: 1800px; }
        table.so-list .so-nw { white-space: nowrap; }
        table.so-list .so-barang { min-width: 200px; }
        table.so-list .so-proj { min-width: 270px; }
        table.so-list .so-tujuan { min-width: 440px; }
    }
</style>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 so-list">
                <thead class="table-light">
                    <tr>
                        <?php if (can('delivery_note', 'view')): ?>
                            <th class="no-print" style="width: 36px;"></th>
                        <?php endif; ?>
                        <th class="so-nw">No. Dokumen</th>
                        <th class="so-nw">Tanggal</th>
                        <th class="so-barang">Barang</th>
                        <th class="text-end so-nw">Qty</th>
                        <th class="so-proj">Project / Client</th>
                        <th class="so-nw">Gudang</th>
                        <th class="so-tujuan">Tujuan</th>
                        <th class="so-nw">PIC</th>
                        <th class="so-nw">Surat Jalan</th>
                        <th>Validasi</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stockOuts)): ?>
                        <tr><td colspan="11" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-box-arrow-up empty-icon"></i>
                                <div class="empty-title">Belum ada pengeluaran barang</div>
                                <div class="empty-desc">Catat barang yang keluar dari gudang menuju project.</div>
                                <?php if (can('stock_out', 'create')): ?>
                                <a href="<?= BASE_URL ?>/stock_out/create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Pengeluaran
                                </a>
                                <?php endif; ?>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($stockOuts as $so): ?>
                        <tr>
                            <?php if (can('delivery_note', 'view')): ?>
                                <td class="no-print">
                                    <?php
                                    // Checkbox SELALU ada di setiap baris (dulu cuma muncul kalau baris
                                    // SUDAH punya Surat Jalan -- akibatnya Pengeluaran Barang yang baru
                                    // dibuat tidak bisa dipilih untuk diterbitkan Surat Jalan sama sekali).
                                    // data-has-dn menandai baris yang sudah punya SJ: JS memakainya untuk
                                    // memilah tombol "Buat Surat Jalan" (baris tanpa SJ) vs "Cetak Surat
                                    // Jalan Terpilih" (baris yang sudah punya SJ).
                                    ?>
                                    <input type="checkbox" class="form-check-input so-row-check"
                                           value="<?= (int) $so['id'] ?>"
                                           data-stock-out-id="<?= (int) $so['id'] ?>"
                                           data-doc-id="<?= (int) ($so['document_id'] ?: $so['id']) ?>"
                                           <?php if (!empty($so['delivery_note_id'])): ?>
                                               data-has-dn="1"
                                               data-delivery-note-id="<?= (int) $so['delivery_note_id'] ?>"
                                           <?php endif; ?>>
                                </td>
                            <?php endif; ?>
                            <td class="so-nw"><?= e($so['stock_out_number'] ?? '-') ?></td>
                            <td class="so-nw"><?= formatTanggal($so['out_date']) ?></td>
                            <td class="so-barang"><?= e($so['item_name']) ?></td>
                            <td class="text-end so-nw"><?= number_format((float) $so['qty'], 2, ',', '.') ?> <?= e($so['unit']) ?></td>
                            <td>
                                <?php if ($so['destination_type'] === 'gudang'): ?>
                                    <span class="badge text-bg-primary">Mutasi Gudang</span>
                                <?php elseif ($so['destination_type'] === 'client'): ?>
                                    <span class="badge text-bg-info text-dark"><?= e($so['client_name'] ?? '-') ?></span>
                                    <?php if (!empty($so['sales_order_number'])): ?>
                                        <div class="text-muted small">
                                            <a href="<?= BASE_URL ?>/sales_order/detail/<?= (int) $so['sales_order_id'] ?>" class="text-decoration-none"><?= e($so['sales_order_number']) ?></a>
                                            &middot; PO <?= e($so['sales_order_po_number']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-muted small"><?= e($so['invoice_number'] ?? '-') ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?= e($so['project_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td class="so-nw">
                                <?php if (($so['destination_type'] ?? '') === 'gudang'): ?>
                                    <div><?= e($so['source_warehouse_name'] ?? '-') ?> <i class="bi bi-arrow-right"></i> <?= e($so['dest_warehouse_name'] ?? '-') ?></div>
                                    <?php $soTsBadge = ['dalam_perjalanan' => 'warning text-dark', 'diterima' => 'success', 'dibatalkan' => 'secondary'][$so['transfer_status'] ?? ''] ?? 'secondary'; ?>
                                    <span class="badge bg-<?= $soTsBadge ?>"><?= e(StockOut::transferStatusLabel($so['transfer_status'] ?? null)) ?></span>
                                <?php else: ?>
                                    <?= e($so['source_warehouse_name'] ?? '-') ?>
                                <?php endif; ?>
                            </td>
                            <td class="so-tujuan"><?= e($so['destination']) ?></td>
                            <td class="so-nw"><?= e($so['pic_name']) ?></td>
                            <td>
                                <?php if (!empty($so['delivery_number'])): ?>
                                    <a href="<?= BASE_URL ?>/delivery_note/print/<?= (int) $so['delivery_note_id'] ?>" target="_blank" class="badge bg-info text-dark text-decoration-none">
                                        <?= e($so['delivery_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $soValBadge = ['menunggu' => 'warning text-dark', 'tervalidasi' => 'success', 'ditolak' => 'danger', 'tidak_perlu' => 'light text-muted border'];
                                    $soValLabel = ['menunggu' => 'Menunggu', 'tervalidasi' => 'Tervalidasi', 'ditolak' => 'Ditolak', 'tidak_perlu' => 'Tidak perlu (non-project)'];
                                    $soVs = $so['validation_status'] ?? 'tervalidasi';
                                ?>
                                <span class="badge bg-<?= $soValBadge[$soVs] ?? 'secondary' ?>"><?= $soValLabel[$soVs] ?? $soVs ?></span>
                            </td>
                            <td class="text-center">
                                <?php $soCanEditDn = !empty($so['delivery_note_id']) && can('delivery_note', 'edit'); ?>
                                <?php if (can('stock_out', 'edit') || can('stock_out', 'delete') || can('stock_out', 'receive_transfer') || $soCanEditDn): ?>
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php
                                            // Sama dengan StockOutController::assertValidationAllowsChange():
                                            // pengeluaran PROJECT yang sudah tervalidasi hanya bisa diubah Super Admin.
                                            $soLocked = StockOut::requiresValidation($so['project_id'] ?? null)
                                                && ($so['validation_status'] ?? '') === 'tervalidasi'
                                                && currentUserRole() !== ROLE_SUPER_ADMIN;
                                        ?>
                                        <?php if ($soCanEditDn): ?>
                                        <?php // Edit header Surat Jalan: tidak ikut terkunci validasi/periode Pengeluaran (yang diubah hanya dokumen SJ). ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/delivery_note/edit/<?= (int) $so['delivery_note_id'] ?>">
                                                <i class="bi bi-truck"></i> Edit Surat Jalan
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php $soIsTransfer = ($so['destination_type'] ?? '') === 'gudang'; $soTs = (string) ($so['transfer_status'] ?? ''); ?>
                                        <?php if ($soIsTransfer && $soTs === 'dalam_perjalanan' && can('stock_out', 'receive_transfer')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=stock_out&action=receiveTransfer"
                                                  onsubmit="return confirm('Terima mutasi ini? Stok gudang tujuan akan bertambah dan tidak bisa diterima dua kali.');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $so['id'] ?>">
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="bi bi-box-arrow-in-down"></i> Terima di Gudang Tujuan
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (!empty($so['sales_order_id'])): ?>
                                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-lock-fill"></i> Dari Order Penjualan &mdash; koreksi lewat order</span></li>
                                        <?php elseif ($soLocked): ?>
                                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-lock-fill"></i> Sudah divalidasi (hubungi Super Admin)</span></li>
                                        <?php elseif (isPeriodClosed('stock_out', $so['out_date'])): ?>
                                        <li><span class="dropdown-item-text text-muted small"><i class="bi bi-lock-fill"></i> Periode ditutup</span></li>
                                        <?php else: ?>
                                        <?php if (can('stock_out', 'edit') && (!$soIsTransfer || $soTs === 'dalam_perjalanan')): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/stock_out/edit/<?= (int) $so['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($soIsTransfer && $soTs !== 'dibatalkan' && can('stock_out', 'delete')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=stock_out&action=cancelTransfer"
                                                  onsubmit="return confirm('Batalkan mutasi ini? Stok akan dikoreksi (dikembalikan ke gudang asal). Riwayat tetap tersimpan.');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $so['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-x-octagon"></i> Batalkan Mutasi
                                                </button>
                                            </form>
                                        </li>
                                        <?php elseif (can('stock_out', 'delete') && (!$soIsTransfer || $soTs === 'dibatalkan')): ?>
                                        <li>
                                            <form method="POST" action="<?= BASE_URL ?>/index.php?module=stock_out&action=delete"
                                                  onsubmit="return confirm('Yakin ingin menghapus pengeluaran barang ini? Stok akan dikembalikan.');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $so['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash"></i> Hapus
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if (can('delivery_note', 'view')): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var createBtn = document.getElementById('soCreateDeliveryNoteBtn'); // null kalau tak punya izin create
    var printBtn  = document.getElementById('soPrintDeliveryNoteBtn');
    var hint      = document.getElementById('soSelectHint');

    function checkedBoxes() {
        return Array.prototype.filter.call(
            document.querySelectorAll('.so-row-check'),
            function (c) { return c.checked; }
        );
    }

    function refresh() {
        var sel = checkedBoxes();
        var withoutDn = sel.filter(function (c) { return !c.dataset.hasDn; });
        var withDn    = sel.filter(function (c) { return c.dataset.hasDn === '1'; });

        if (createBtn) { createBtn.disabled = withoutDn.length === 0; }
        printBtn.disabled = withDn.length === 0;

        if (hint) {
            var parts = [];
            if (withoutDn.length) { parts.push(withoutDn.length + ' baris belum ada Surat Jalan'); }
            if (withDn.length)    { parts.push(withDn.length + ' baris sudah ada Surat Jalan'); }
            hint.textContent = parts.length ? 'Terpilih: ' + parts.join('  •  ') : '';
        }
    }

    // Barang dalam satu dokumen pengeluaran (nomor sama) dicentang/dilepas bersamaan,
    // supaya hanya satu Surat Jalan yang terbit untuk dokumen itu. Listener ini
    // didaftarkan SEBELUM wireSelectAllCheckbox agar status "Centang Semua" ikut benar.
    Array.prototype.forEach.call(document.querySelectorAll('.so-row-check'), function (box) {
        box.addEventListener('change', function () {
            Array.prototype.forEach.call(
                document.querySelectorAll('.so-row-check[data-doc-id="' + box.dataset.docId + '"]'),
                function (sib) { sib.checked = box.checked; }
            );
        });
    });

    wireSelectAllCheckbox('#soSelectAll', '.so-row-check', refresh);

    if (createBtn) {
        createBtn.addEventListener('click', function () {
            var ids = checkedBoxes()
                .filter(function (c) { return !c.dataset.hasDn; })
                .map(function (c) { return c.dataset.stockOutId; });
            if (ids.length === 0) { return; }
            window.location.href = '<?= BASE_URL ?>/index.php?module=delivery_note&action=select&ids=' + ids.join(',');
        });
    }

    printBtn.addEventListener('click', function () {
        var deliveryNoteIds = checkedBoxes()
            .filter(function (c) { return c.dataset.hasDn === '1'; })
            .map(function (c) { return c.dataset.deliveryNoteId; });
        // Dedup -- beberapa baris terpilih bisa saja satu Surat Jalan yang sama.
        var uniqueIds = deliveryNoteIds.filter(function (id, i) { return deliveryNoteIds.indexOf(id) === i; });
        if (uniqueIds.length === 0) { return; }
        window.open('<?= BASE_URL ?>/index.php?module=delivery_note&action=printMany&ids=' + uniqueIds.join(','), '_blank');
    });

    refresh();
});
</script>
<?php endif; ?>
