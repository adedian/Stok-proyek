<?php
/** Laporan Pengajuan Budget (layar). Variabel: $filters, $report, $filterQuery, $banks, $creators, $categories, $statuses */
$canPrint = can('pengajuan_budget_report', 'print');
$canExport = can('pengajuan_budget_report', 'export');
$qs = http_build_query($filterQuery);
?>
<style>
    .po-wrap { overflow-x: auto; }
    .po-table { width: 100%; min-width: 980px; border-collapse: collapse; font-size: 13px; background: #fff; }
    .po-table th { background: #B4E5A2; border: 1px solid #000; padding: 4px 6px; text-align: center; font-weight: 700; color: #000; }
    .po-table td { border: 1px solid #000; padding: 3px 6px; vertical-align: top; color: #000; }
    .po-table td.c { text-align: center; }
    .po-table td.num { text-align: right; white-space: nowrap; }
    .po-table td.wrap { white-space: normal; word-break: break-word; }
    .po-table td.nob { border: 0; background: transparent; }
    .po-table tr.po-gap td { border: 0; height: 8px; padding: 0; }
    .po-table .po-red { color: #C00000 !important; font-weight: 600; }
    .po-table tr.po-grand td:not(.nob) { border: 2px solid #000; font-size: 1.1em; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Laporan Pengajuan Budget</h4>
        <small class="text-muted">Permintaan Otorisasi &mdash; saldo awal dibaca dari saldo aktual Bank (tidak diubah oleh pengajuan)</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (can('pengajuan_budget', 'view')): ?>
            <a href="<?= BASE_URL ?>/pengajuan_budget" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-plus"></i> Form Pengajuan</a>
        <?php endif; ?>
        <?php if ($canPrint): ?>
            <a href="<?= BASE_URL ?>/index.php?module=pengajuan_budget_report&action=print&<?= e($qs) ?>" target="_blank" rel="noopener" class="btn btn-dark"><i class="bi bi-printer"></i> Cetak</a>
        <?php endif; ?>
        <?php if ($canExport): ?>
            <a href="<?= BASE_URL ?>/index.php?module=pengajuan_budget_report&action=exportExcel&<?= e($qs) ?>" class="btn btn-success"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/pengajuan_budget_report" class="row g-2 align-items-end" id="pbFilter">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Tanggal Tertentu</label>
                <input type="date" name="tanggal" id="fTanggal" class="form-control form-control-sm" value="<?= e($filters['tanggal']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Tanggal Awal</label>
                <input type="date" name="date_from" id="fFrom" class="form-control form-control-sm" value="<?= e($filters['tanggal'] !== '' ? '' : $filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Tanggal Akhir</label>
                <input type="date" name="date_to" id="fTo" class="form-control form-control-sm" value="<?= e($filters['tanggal'] !== '' ? '' : $filters['date_to']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Rekening Sumber</label>
                <select name="bank_id" class="form-select form-select-sm">
                    <option value="">Semua Rekening</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?= (int) $b['id'] ?>" <?= (int) $filters['bank_id'] === (int) $b['id'] ? 'selected' : '' ?>><?= e(MasterBank::reportLabel($b)) ?><?= !empty($b['inactive']) ? ' (nonaktif)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Pengaju</label>
                <select name="created_by" class="form-select form-select-sm">
                    <option value="">Semua Pengaju</option>
                    <?php foreach ($creators as $u): ?>
                        <option value="<?= (int) $u['id'] ?>" <?= (int) $filters['created_by'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua (kecuali Dibatalkan)</option>
                    <?php foreach ($statuses as $slug => $label): ?>
                        <option value="<?= e($slug) ?>" <?= $filters['status'] === $slug ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Kategori</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) $filters['category_id'] === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search"></i> Tampilkan</button>
                <a href="<?= BASE_URL ?>/pengajuan_budget_report" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-x-circle"></i> Reset</a>
            </div>
        </form>
        <div class="form-text mt-2">Bila semua tanggal dikosongkan, laporan menampilkan <strong>hari ini</strong>. Saldo diambil sampai tanggal akhir (atau tanggal tertentu).</div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="mb-2">
            <div class="fw-bold">PERMINTAAN OTORISASI</div>
            <div class="fw-bold"><?= e(PengajuanBudgetReportController::periodLabel($filters)) ?></div>
        </div>
        <div class="po-wrap">
            <?php $print = false; require __DIR__ . '/_report_table.php'; ?>
        </div>
        <div class="form-text mt-2">
            <strong>Sisa Saldo</strong> = saldo aktual Bank pada <?= e(formatTanggal($report['saldo_date'])) ?> &minus; total pengajuan yang belum direalisasikan (Draft + Diajukan).
            Pengajuan <em>Direalisasikan</em> dianggap sudah tercatat sebagai Bank Keluar sehingga tidak dikurangkan lagi (tidak ada hitung ganda). Ini proyeksi saja &mdash; saldo Bank tidak berubah.
        </div>
    </div>
</div>

<script>
(function () {
    var t = document.getElementById('fTanggal'), f = document.getElementById('fFrom'), o = document.getElementById('fTo');
    // "Tanggal tertentu" dan rentang saling meniadakan (tanggal tertentu menang di server).
    t.addEventListener('input', function () { if (t.value) { f.value = ''; o.value = ''; } });
    [f, o].forEach(function (el) { el.addEventListener('input', function () { if (el.value) { t.value = ''; } }); });
})();
</script>
