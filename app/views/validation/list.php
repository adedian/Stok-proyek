<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= !empty($isProblemView) ? 'Validasi Belum Sesuai' : 'Validasi Barang Datang' ?></h4>
        <small class="text-muted">Bandingkan barang PO vs barang yang benar-benar diterima</small>
    </div>
    <div class="d-flex gap-2">
        <?php if (empty($isProblemView)): ?>
            <a href="<?= BASE_URL ?>/validation/problem" class="btn btn-outline-warning">
                <i class="bi bi-exclamation-triangle"></i> Validasi Belum Sesuai
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>/validation" class="btn btn-outline-secondary">
                <i class="bi bi-list-check"></i> Semua Validasi
            </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/validation/approved" class="btn btn-outline-success">
            <i class="bi bi-check2-circle"></i> Validasi Sesuai
        </a>
        <a href="<?= BASE_URL ?>/validation/report" class="btn btn-outline-secondary">
            <i class="bi bi-file-earmark-bar-graph"></i> Laporan Selisih
        </a>
    </div>
</div>

<?php if (!empty($isProblemView)): ?>
    <div class="alert alert-info small">
        <i class="bi bi-info-circle"></i>
        Menampilkan semua item yang <strong>belum divalidasi</strong> atau hasil validasinya
        <strong>bukan Sesuai</strong> (Kurang / Lebih / Barang Lain) -- item di sini belum dianggap
        aman/valid untuk stok sampai ditindaklanjuti.
    </div>
<?php endif; ?>

<?php if ($pendingCount > 0): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <span>Ada <strong><?= (int) $pendingCount ?></strong> item dengan selisih yang masih menunggu validasi.</span>
        <?php if (($filters['validated'] ?? '') !== 'selisih'): ?>
            <a href="<?= BASE_URL ?>/validation?validated=selisih" class="btn btn-sm btn-warning ms-auto">
                <i class="bi bi-funnel"></i> Tampilkan yang selisih saja
            </a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/validation" class="row g-2 align-items-end">
            <?php if (!empty($isProblemView)): ?>
                <input type="hidden" name="action" value="problem">
            <?php endif; ?>
            <div class="col-md-4">
                <label class="form-label small text-muted mb-1">Cari (No. Penerimaan / No. PO / Nama Barang)</label>
                <input type="text" name="keyword" class="form-control form-control-sm" value="<?= e($filters['keyword']) ?>" placeholder="ketik potongan kode / nama...">
            </div>
            <div class="col-md-3">
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
            <div class="col-md-3">
                <label class="form-label small text-muted mb-1">Validasi</label>
                <select name="validated" class="form-select form-select-sm">
                    <option value="no" <?= $filters['validated'] === 'no' ? 'selected' : '' ?>>Belum Divalidasi</option>
                    <option value="selisih" <?= $filters['validated'] === 'selisih' ? 'selected' : '' ?>>Belum divalidasi &amp; ada selisih</option>
                    <option value="yes" <?= $filters['validated'] === 'yes' ? 'selected' : '' ?>>Sudah Divalidasi</option>
                    <option value="" <?= $filters['validated'] === '' ? 'selected' : '' ?>>Semua</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<?php
// Modal validasi dikumpulkan di sini lalu dirender DI LUAR <table> (bawah).
// Alasan: <div> di dalam <tbody> di-"foster parent" oleh parser HTML, dan di HP
// wrapper .table-responsive dapat class .rt-hide-table (display:none) saat tabel
// jadi kartu -- modal yang ada di dalamnya jadi tidak bisa dibuka. Pola sama
// seperti cash_validation/list.php.
$modals = '';
// Checklist massal hanya untuk pengguna yang berhak memvalidasi (izin penuh / mandiri Lampu).
$showBulk = can('validation', 'validate') || can('validation', 'validate_lamp');
$eligibleCount = 0;
foreach ($items as $__it) {
    if ($bulkReason($__it) === null) { $eligibleCount++; }
}
?>
<?php if ($showBulk): ?>
<div class="card border-0 shadow-sm mb-3" id="bulkBar">
    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
        <div class="small">
            <i class="bi bi-check2-square text-primary"></i>
            <strong id="bulkCount">0</strong> barang dipilih
            <span class="text-muted">dari <?= (int) $eligibleCount ?> yang memenuhi syarat</span>
        </div>
        <div class="text-muted small d-none d-md-block">
            Hanya barang <em>belum divalidasi</em> dengan hasil otomatis <strong>Sesuai</strong> yang bisa dicentang.
            Barang dengan selisih divalidasi satu per satu (wajib catatan).
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="bulkClear" disabled>
            <i class="bi bi-x-circle"></i> Kosongkan Pilihan
        </button>
        <button type="button" class="btn btn-success" id="bulkOpen" disabled
                title="Pilih barang dengan checkbox terlebih dahulu">
            <i class="bi bi-check2-all"></i> VALIDASI SEMUA
        </button>
    </div>
</div>
<?php endif; ?>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php if ($showBulk): ?>
                            <th class="text-center" style="width: 42px;">
                                <input type="checkbox" class="form-check-input" id="bulkAll" title="Pilih semua yang memenuhi syarat"
                                       <?= $eligibleCount === 0 ? 'disabled' : '' ?> aria-label="Pilih semua">
                            </th>
                        <?php endif; ?>
                        <th>No. Penerimaan</th>
                        <th>No. PO</th>
                        <th>Pembuat PO</th>
                        <th>Barang</th>
                        <th class="text-end">Qty PO</th>
                        <th class="text-end">Qty Diterima</th>
                        <th class="text-center">Status</th>
                        <th>Validator</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr><td colspan="<?= $showBulk ? 10 : 9 ?>" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-check2-square empty-icon"></i>
                                <div class="empty-title">Tidak ada data</div>
                                <div class="empty-desc mb-0">Tidak ada item penerimaan barang yang cocok dengan filter ini.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($items as $item): ?>
                        <?php $__reason = $bulkReason($item); ?>
                        <tr>
                            <?php if ($showBulk): ?>
                                <td class="text-center" data-label="Pilih">
                                    <input type="checkbox" class="form-check-input vi-check"
                                           data-id="<?= (int) $item['id'] ?>"
                                           data-name="<?= e($item['item_name']) ?>"
                                           data-unit="<?= e($item['unit'] ?? '') ?>"
                                           data-qty="<?= e($item['qty_received']) ?>"
                                           data-receipt="<?= e($item['receipt_number']) ?>"
                                           <?= $__reason === null ? '' : 'disabled title="' . e($__reason) . '"' ?>
                                           aria-label="Pilih <?= e($item['item_name']) ?>">
                                </td>
                            <?php endif; ?>
                            <td><?= e($item['receipt_number']) ?></td>
                            <td><?= e($item['po_number']) ?></td>
                            <td><?= e($item['pembuat_po'] ?? '-') ?></td>
                            <td><?= e($item['item_name']) ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_order'], 2, ',', '.') ?></td>
                            <td class="text-end"><?= number_format((float) $item['qty_received'], 2, ',', '.') ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?= e($statusBadgeClass[$item['comparison_status']]) ?>">
                                    <?= e($statusLabels[$item['comparison_status']]) ?>
                                </span>
                                <div class="mt-1">
                                    <?php if (!empty($item['validated_at'])): ?>
                                        <span class="badge text-bg-light border text-success">Sudah Validasi</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-light border text-secondary">Menunggu Validasi</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($item['validated_at'])): ?>
                                    <span class="small"><?= e($item['validated_by_name'] ?? '-') ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($canValidateItem($item)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#validateModal<?= (int) $item['id'] ?>">
                                        <i class="bi bi-clipboard-check"></i> Validasi
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!$canValidateItem($item)) { continue; } ?>
                        <?php ob_start(); ?>
                        <!-- Modal validasi per item -->
                        <div class="modal fade" id="validateModal<?= (int) $item['id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=validation&action=validateItem">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <div class="modal-header">
                                            <h6 class="modal-title">Validasi: <?= e($item['item_name']) ?></h6>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="small text-muted mb-3">
                                                No. PO <?= e($item['po_number']) ?> &middot;
                                                Penerimaan <?= e($item['receipt_number']) ?><br>
                                                Qty PO: <?= number_format((float) $item['qty_order'], 2, ',', '.') ?>
                                                &middot; Qty Diterima: <?= number_format((float) $item['qty_received'], 2, ',', '.') ?>
                                            </p>
                                            <div class="mb-3">
                                                <label class="form-label">Status Validasi</label>
                                                <select name="comparison_status" class="form-select" required>
                                                    <?php foreach ($statusLabels as $key => $label): ?>
                                                        <option value="<?= e($key) ?>" <?= $item['comparison_status'] === $key ? 'selected' : '' ?>>
                                                            <?= e($label) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label">Catatan (wajib jika bukan "Sesuai")</label>
                                                <textarea name="validation_notes" class="form-control" rows="2"><?= e($item['validation_notes'] ?? '') ?></textarea>
                                            </div>
                                            <?php if (!empty($validationHistory[(int) $item['id']])): ?>
                                                <hr>
                                                <div class="small fw-semibold mb-1"><i class="bi bi-clock-history"></i> Riwayat Validasi</div>
                                                <ul class="small text-muted mb-0 ps-3">
                                                    <?php foreach ($validationHistory[(int) $item['id']] as $vh): ?>
                                                        <li>
                                                            <?= e(formatTanggal(substr((string) $vh['validated_at'], 0, 10)) . ' ' . substr((string) $vh['validated_at'], 11, 5)) ?>
                                                            &mdash; <?= e($statusLabels[$vh['new_status']] ?? $vh['new_status']) ?>
                                                            oleh <?= e($vh['validator_name'] ?? '-') ?>
                                                            <?= $vh['mode'] === 'bulk' ? '(massal)' : ($vh['mode'] === 'legacy' ? '(data lama)' : '') ?>
                                                            <?= !empty($vh['notes']) ? '&middot; ' . e($vh['notes']) : '' ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary">Simpan Validasi</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php $modals .= ob_get_clean(); ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?= $modals ?>

<?php if ($showBulk): ?>
<!-- Validasi Semua (massal): langkah 1 = pilih tindakan, langkah 2 = konfirmasi peringatan -->
<div class="modal fade" id="bulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/index.php?module=validation&action=bulkValidate" id="bulkForm">
                <?= csrfField() ?>
                <input type="hidden" name="mode" value="sesuai">
                <input type="hidden" name="return" value="<?= e($bulkReturn ?? 'index') ?>">
                <div id="bulkIds"></div>

                <div id="bulkStep1">
                    <div class="modal-header">
                        <h6 class="modal-title"><i class="bi bi-check2-all"></i> Validasi Semua</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3"><strong id="bulkCount1">0</strong> barang terpilih. Pilih tindakan:</p>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success btn-lg" id="bulkChooseSesuai">
                                <i class="bi bi-check2-circle"></i> Validasi Sesuai Semua
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        </div>
                    </div>
                </div>

                <div id="bulkStep2" class="d-none">
                    <div class="modal-header bg-warning-subtle">
                        <h6 class="modal-title"><i class="bi bi-exclamation-triangle-fill text-warning"></i> PERINGATAN VALIDASI BARANG</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Pastikan seluruh barang yang dipilih sudah benar-benar sesuai dengan kondisi fisik barang, jumlah, spesifikasi, dan dokumen penerimaan. Setelah validasi dilakukan, status barang akan berubah menjadi tervalidasi.</p>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><div class="border rounded p-2"><div class="small text-muted">Jumlah barang divalidasi</div><div class="fs-5 fw-bold" id="bulkCount2">0</div></div></div>
                            <div class="col-6"><div class="border rounded p-2"><div class="small text-muted">Total kuantitas</div><div class="fw-bold" id="bulkQty">-</div></div></div>
                        </div>
                        <div class="table-responsive border rounded" style="max-height: 280px; overflow:auto;">
                            <table class="table table-sm mb-0 align-middle">
                                <thead class="table-light"><tr><th>#</th><th>Nama Barang</th><th>No. Penerimaan</th><th class="text-end">Qty</th></tr></thead>
                                <tbody id="bulkList"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success" id="bulkConfirm">
                            <i class="bi bi-check2-circle"></i> Konfirmasi Validasi Sesuai Semua
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checks = Array.prototype.slice.call(document.querySelectorAll('.vi-check'));
    const all = document.getElementById('bulkAll');
    const openBtn = document.getElementById('bulkOpen');
    const clearBtn = document.getElementById('bulkClear');
    const modalEl = document.getElementById('bulkModal');
    const step1 = document.getElementById('bulkStep1');
    const step2 = document.getElementById('bulkStep2');
    const enabled = function () { return checks.filter(function (c) { return !c.disabled; }); };
    const selected = function () { return enabled().filter(function (c) { return c.checked; }); };

    function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function fmtQty(n) {
        return (Math.round(n * 100) / 100).toLocaleString('id-ID', { maximumFractionDigits: 2 });
    }

    function refresh() {
        const n = selected().length;
        document.getElementById('bulkCount').textContent = n;
        openBtn.disabled = n === 0;
        clearBtn.disabled = n === 0;
        openBtn.title = n === 0 ? 'Pilih barang dengan checkbox terlebih dahulu' : 'Validasi ' + n + ' barang terpilih';
        if (all) {
            const en = enabled().length;
            all.checked = en > 0 && n === en;
            all.indeterminate = n > 0 && n < en;
        }
    }
    checks.forEach(function (c) { c.addEventListener('change', refresh); });
    if (all) {
        all.addEventListener('change', function () {
            enabled().forEach(function (c) { c.checked = all.checked; });
            refresh();
        });
    }
    clearBtn.addEventListener('click', function () {
        checks.forEach(function (c) { c.checked = false; });
        refresh();
    });

    // Modal dipindah ke <body> supaya tak terpotong wrapper (pola sama modal lain di halaman ini).
    document.body.appendChild(modalEl);
    const bsModal = new bootstrap.Modal(modalEl);

    openBtn.addEventListener('click', function () {
        const sel = selected();
        if (sel.length === 0) { return; } // tidak pernah memvalidasi "semua yang tampil" tanpa pilihan
        document.getElementById('bulkCount1').textContent = sel.length;
        step1.classList.remove('d-none');
        step2.classList.add('d-none');
        bsModal.show();
    });

    document.getElementById('bulkChooseSesuai').addEventListener('click', function () {
        const sel = selected();
        const list = document.getElementById('bulkList');
        list.innerHTML = '';
        const perUnit = {};
        const ids = document.getElementById('bulkIds');
        ids.innerHTML = '';
        sel.forEach(function (c, i) {
            const qty = parseFloat(c.dataset.qty) || 0;
            const unit = c.dataset.unit || '';
            perUnit[unit] = (perUnit[unit] || 0) + qty;
            list.insertAdjacentHTML('beforeend', '<tr><td>' + (i + 1) + '</td><td>' + esc(c.dataset.name) + '</td><td>' + esc(c.dataset.receipt)
                + '</td><td class="text-end">' + fmtQty(qty) + ' ' + esc(unit) + '</td></tr>');
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = 'ids[]'; h.value = c.dataset.id;
            ids.appendChild(h);
        });
        document.getElementById('bulkCount2').textContent = sel.length;
        document.getElementById('bulkQty').textContent = Object.keys(perUnit).map(function (u) {
            return fmtQty(perUnit[u]) + (u ? ' ' + u : '');
        }).join(' + ');
        step1.classList.add('d-none');
        step2.classList.remove('d-none');
    });

    // Cegah klik ganda saat memproses (satu kali kirim).
    document.getElementById('bulkForm').addEventListener('submit', function () {
        const b = document.getElementById('bulkConfirm');
        b.disabled = true;
        b.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Memproses...';
    });

    refresh();
});
</script>
<?php endif; ?>
