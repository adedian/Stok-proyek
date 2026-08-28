<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Pengeluaran Barang</h4>
        <small class="text-muted">Daftar barang yang keluar dari gudang</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=delivery_note" class="btn btn-outline-secondary">
            <i class="bi bi-truck"></i> <span class="d-sm-none">Riwayat</span><span class="d-none d-sm-inline">Riwayat Surat Jalan</span>
        </a>
        <a href="<?= BASE_URL ?>/index.php?module=stock_out&action=create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> <span class="d-sm-none">Tambah</span><span class="d-none d-sm-inline">Tambah Pengeluaran</span>
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="stock_out">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (Barang / Tujuan / PIC)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-3">
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
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-dark" id="soPrintDeliveryNoteBtn" disabled>
            <i class="bi bi-printer"></i> Cetak Surat Jalan Terpilih
        </button>
    </div>
</div>
<div class="small text-muted mb-2 no-print" id="soSelectHint"></div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php if (can('delivery_note', 'view')): ?>
                            <th class="no-print" style="width: 36px;"></th>
                        <?php endif; ?>
                        <th>No. Dokumen</th>
                        <th>Tanggal</th>
                        <th>Barang</th>
                        <th class="text-end">Qty</th>
                        <th>Project / Client</th>
                        <th>Tujuan</th>
                        <th>PIC</th>
                        <th>Surat Jalan</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($stockOuts)): ?>
                        <tr><td colspan="9" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-box-arrow-up empty-icon"></i>
                                <div class="empty-title">Belum ada pengeluaran barang</div>
                                <div class="empty-desc">Catat barang yang keluar dari gudang menuju project.</div>
                                <a href="<?= BASE_URL ?>/index.php?module=stock_out&action=create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah Pengeluaran
                                </a>
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
                                           <?php if (!empty($so['delivery_note_id'])): ?>
                                               data-has-dn="1"
                                               data-delivery-note-id="<?= (int) $so['delivery_note_id'] ?>"
                                           <?php endif; ?>>
                                </td>
                            <?php endif; ?>
                            <td><?= e($so['stock_out_number'] ?? '-') ?></td>
                            <td><?= formatTanggal($so['out_date']) ?></td>
                            <td><?= e($so['item_name']) ?></td>
                            <td class="text-end"><?= number_format((float) $so['qty'], 2, ',', '.') ?> <?= e($so['unit']) ?></td>
                            <td>
                                <?php if ($so['destination_type'] === 'client'): ?>
                                    <span class="badge text-bg-info text-dark"><?= e($so['client_name'] ?? '-') ?></span>
                                    <div class="text-muted small"><?= e($so['invoice_number'] ?? '-') ?></div>
                                <?php else: ?>
                                    <?= e($so['project_name']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e($so['destination']) ?></td>
                            <td><?= e($so['pic_name']) ?></td>
                            <td>
                                <?php if (!empty($so['delivery_number'])): ?>
                                    <a href="<?= BASE_URL ?>/index.php?module=delivery_note&action=print&id=<?= (int) $so['delivery_note_id'] ?>" target="_blank" class="badge bg-info text-dark text-decoration-none">
                                        <?= e($so['delivery_number']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/index.php?module=stock_out&action=edit&id=<?= (int) $so['id'] ?>">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        </li>
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
