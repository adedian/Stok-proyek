<?php
/** @var array $ledger @var array $filters @var array $banks @var array $projects */
$qs = http_build_query(array_filter([
    'date_from'  => $filters['date_from'],
    'date_to'    => $filters['date_to'],
    'project_id' => $filters['project_id'],
    'mutasi'     => $filters['mutasi'],
]));
$rp = static fn($v) => number_format((float) $v, 0, ',', '.');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <div>
        <h4 class="mb-0">Laporan Bank</h4>
        <small class="text-muted">Mutasi masuk/keluar &amp; saldo berjalan per Bank</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= BASE_URL ?>/bank" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
        <a href="<?= BASE_URL ?>/index.php?module=bank&action=printReport<?= $qs ? '&' . e($qs) : '' ?>" class="btn btn-outline-danger" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/bank/report" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="report">
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small text-muted mb-1">Project</label>
                <select name="project_id" class="form-select form-select-sm">
                    <option value="">Semua Project</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) $filters['project_id'] === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['project_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Mutasi</label>
                <select name="mutasi" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="masuk" <?= $filters['mutasi'] === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                    <option value="keluar" <?= $filters['mutasi'] === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/bank/report" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="text-center mb-3">Laporan Bank</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th>Tgl</th>
                        <th>No Bukti</th>
                        <th>Bank</th>
                        <th>Project</th>
                        <th>Uraian</th>
                        <th class="text-end">Masuk</th>
                        <th class="text-end">Keluar</th>
                        <th class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ledger['rows'])): ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada transaksi Bank pada filter ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ledger['rows'] as $row): ?>
                        <tr>
                            <td><?= e(formatTanggal($row['trx_date'])) ?></td>
                            <td><?= e($row['no_bukti']) ?></td>
                            <td><?= e($row['bank_name']) ?></td>
                            <td><?= e($row['project_name'] ?? '-') ?></td>
                            <td><?= e($row['uraian']) ?></td>
                            <td class="text-end"><?= $row['masuk'] != 0 ? $rp($row['masuk']) : '' ?></td>
                            <td class="text-end"><?= $row['keluar'] != 0 ? $rp($row['keluar']) : '' ?></td>
                            <td class="text-end"><?= $rp($row['saldo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="fw-bold table-light">
                        <td colspan="7" class="text-end">Saldo Akhir</td>
                        <td class="text-end"><?= $rp($ledger['saldo_akhir']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="text-end small text-muted mt-3 d-none d-print-block">
            <?= e(printedAtLabel()) ?>, <?= e(printedByLabel()) ?>
        </div>
    </div>
</div>
