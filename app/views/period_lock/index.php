<?php
/** @var array $modules  [slug => label]
 *  @var array $history  baris accounting_period_locks + nama user
 *  @var array $closedEnds [slug => 'YYYY-MM-DD'] batas terkunci aktif */
$today = date('Y-m-d');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Tutup Bulan</h4>
        <small class="text-muted">Kunci transaksi per-modul sampai tanggal tertentu &mdash; khusus Super Admin</small>
    </div>
    <a href="<?= BASE_URL ?>/report" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Laporan
    </a>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle"></i>
    Setelah periode ditutup, transaksi modul tsb dengan tanggal <strong>&le; Tanggal Tutup</strong> tidak dapat
    <strong>dibuat / diedit / dihapus</strong> (ditolak di server, bukan sekadar tombol disembunyikan).
    Tetap bisa <strong>dilihat, dicetak, diekspor, dan masuk laporan</strong>. Bulan berjalan tetap bisa diproses.
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">Tutup Periode</h6>
                <form method="POST" action="<?= BASE_URL ?>/period_lock/close" class="js-confirm-submit"
                      data-message="Tutup periode untuk modul yang dipilih? Transaksi periode terkunci tidak bisa diedit/dihapus (bisa dibuka kembali nanti).">
                    <?= csrfField() ?>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label">Periode (Bulan)</label>
                            <input type="month" name="period_month" class="form-control" value="<?= e(date('Y-m')) ?>">
                            <div class="form-text">Info tampilan saja.</div>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Tanggal Tutup <span class="text-danger">*</span></label>
                            <input type="date" name="close_date" class="form-control" value="<?= e(date('Y-m-t', strtotime('first day of last month'))) ?>" required>
                            <div class="form-text">Transaksi &le; tanggal ini dikunci.</div>
                        </div>
                    </div>

                    <label class="form-label d-flex justify-content-between align-items-center">
                        <span>Modul yang ditutup <span class="text-danger">*</span></span>
                        <span class="btn-group btn-group-sm">
                            <button type="button" class="btn btn-outline-secondary" id="btnSelectAllModules">Select All</button>
                            <button type="button" class="btn btn-outline-secondary" id="btnClearAllModules">Clear All</button>
                        </span>
                    </label>
                    <div class="border rounded p-2 mb-3">
                        <?php foreach ($modules as $slug => $label): ?>
                            <?php $end = $closedEnds[$slug] ?? null; ?>
                            <div class="form-check">
                                <input class="form-check-input module-check" type="checkbox"
                                       name="modules[]" value="<?= e($slug) ?>" id="mod_<?= e($slug) ?>">
                                <label class="form-check-label" for="mod_<?= e($slug) ?>">
                                    <?= e($label) ?>
                                    <?php if ($end): ?>
                                        <span class="badge bg-secondary ms-1" title="Batas terkunci saat ini">
                                            terkunci s/d <?= e(formatTanggal($end)) ?>
                                        </span>
                                    <?php endif; ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-lock-fill"></i> Tutup Periode
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Periode</th>
                                <th>Modul</th>
                                <th>Tgl Tutup</th>
                                <th>Ditutup Oleh</th>
                                <th>Status</th>
                                <th>Dibuka</th>
                                <th class="text-end">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada penutupan periode.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td><?= e(date('M Y', strtotime($h['period_start']))) ?></td>
                                    <td><?= e($modules[$h['module']] ?? $h['module']) ?></td>
                                    <td><?= e(formatTanggal($h['period_end'])) ?></td>
                                    <td><?= e($h['closed_by_name'] ?? '-') ?><br>
                                        <small class="text-muted"><?= $h['closed_at'] ? e(formatTanggal($h['closed_at'])) : '' ?></small>
                                    </td>
                                    <td>
                                        <?php if ($h['status'] === 'closed'): ?>
                                            <span class="badge bg-danger">CLOSED</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">OPEN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($h['reopened_at']): ?>
                                            <?= e($h['reopened_by_name'] ?? '-') ?><br>
                                            <small class="text-muted"><?= e(formatTanggal($h['reopened_at'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($h['status'] === 'closed'): ?>
                                            <form method="POST" action="<?= BASE_URL ?>/period_lock/reopen" class="js-confirm-submit d-inline"
                                                  data-message="Apakah Anda yakin ingin membuka kembali periode <?= e($modules[$h['module']] ?? $h['module']) ?> s/d <?= e(formatTanggal($h['period_end'])) ?>?">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="id" value="<?= (int) $h['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    <i class="bi bi-unlock"></i> Buka Kembali
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">&ndash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    document.getElementById('btnSelectAllModules')?.addEventListener('click', function () {
        document.querySelectorAll('.module-check').forEach(function (c) { c.checked = true; });
    });
    document.getElementById('btnClearAllModules')?.addEventListener('click', function () {
        document.querySelectorAll('.module-check').forEach(function (c) { c.checked = false; });
    });
    // Konfirmasi sebelum submit (pola sederhana, tanpa dependency baru).
    document.querySelectorAll('form.js-confirm-submit').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.dataset.message || 'Lanjutkan?')) {
                e.preventDefault();
            }
        });
    });
})();
</script>
