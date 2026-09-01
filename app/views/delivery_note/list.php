<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Riwayat Surat Jalan</h4>
        <small class="text-muted">Dibuat dari baris Pengeluaran Barang yang dikelompokkan</small>
    </div>
    <a href="<?= BASE_URL ?>/stock_out" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Pengeluaran Barang
    </a>
</div>

<?php if (hasRole([ROLE_SUPER_ADMIN])): ?>
    <div class="d-flex justify-content-end mb-2">
        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rangeDeleteModal">
            <i class="bi bi-calendar-x"></i> Hapus per Rentang Tanggal
        </button>
    </div>
    <?php $rangeDeleteAction = 'delivery_note/rangeDelete'; $rangeDeleteLabel = 'Surat Jalan';
          require ROOT_PATH . '/app/views/partials/range_delete_modal.php'; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/delivery_note" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No. Surat Jalan / Tujuan)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2 no-print">
    <div class="form-check">
        <input type="checkbox" class="form-check-input" id="dnSelectAll">
        <label class="form-check-label small" for="dnSelectAll">Centang Semua</label>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-dark" id="dnPrintSelectedBtn" disabled>
            <i class="bi bi-printer"></i> Cetak Terpilih
        </button>
        <a href="<?= BASE_URL ?>/index.php?module=delivery_note&action=printMany&<?= e(http_build_query(array_filter($filters))) ?>"
           target="_blank" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-printer-fill"></i> Cetak Semua
        </a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="no-print" style="width: 36px;"></th>
                        <th>No. Surat Jalan</th>
                        <th>Tanggal</th>
                        <th>Tujuan</th>
                        <th class="text-end">Jml Item</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notes)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-truck empty-icon"></i>
                                <div class="empty-title">Belum ada Surat Jalan</div>
                                <div class="empty-desc">Buat Surat Jalan dari Pengeluaran Barang yang sudah dicentang.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($notes as $n): ?>
                        <tr>
                            <td class="no-print"><input type="checkbox" class="form-check-input dn-row-check" value="<?= (int) $n['id'] ?>"></td>
                            <td class="fw-semibold"><?= e($n['delivery_number']) ?></td>
                            <td><?= formatTanggal($n['delivery_date']) ?></td>
                            <td><?= e($n['destination_name'] ?: ($n['destination_type'] === 'client' ? ($n['client_name'] ?? '-') : ($n['project_name'] ?? '-'))) ?></td>
                            <td class="text-end"><?= (int) $n['item_count'] ?></td>
                            <td class="text-center no-print">
                                <div class="dropdown row-actions">
                                    <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= BASE_URL ?>/delivery_note/print/<?= (int) $n['id'] ?>" target="_blank">
                                                <i class="bi bi-printer"></i> Cetak
                                            </a>
                                        </li>
                                        <?php if (can('delivery_note', 'delete')): ?>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=delivery_note&action=delete"
                                                      class="js-confirm-delete" data-message="Hapus Surat Jalan <?= e($n['delivery_number']) ?>? Baris Pengeluaran Barang terkait akan dikembalikan sebagai belum dikelompokkan.">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    var printBtn = document.getElementById('dnPrintSelectedBtn');

    function refreshPrintBtn() {
        printBtn.disabled = document.querySelector('.dn-row-check:checked') === null;
    }

    wireSelectAllCheckbox('#dnSelectAll', '.dn-row-check', refreshPrintBtn);

    printBtn.addEventListener('click', function () {
        var ids = Array.prototype.filter.call(document.querySelectorAll('.dn-row-check'), function (c) { return c.checked; })
            .map(function (c) { return c.value; });
        if (ids.length === 0) return;
        // Cetak Terpilih untuk Surat Jalan: buka tiap dokumen (satu Surat Jalan =
        // satu grup barang tersendiri, beda dari Invoice yang murni 1 baris = 1 dokumen
        // sejenis) -- tetap satu template yang sama, dibuka berurutan via query ids[].
        window.open('<?= BASE_URL ?>/index.php?module=delivery_note&action=printMany&ids=' + ids.join(','), '_blank');
    });

    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });

    refreshPrintBtn();
});
</script>
