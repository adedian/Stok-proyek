<?php
/** @var array $ledger @var array $filters @var array $categories @var array $picOptions */
$qs = http_build_query(array_filter([
    'date_from'   => $filters['date_from'],
    'date_to'     => $filters['date_to'],
    'pic'         => $filters['pic'],
    'category_id' => $filters['category_id'],
    'mutasi'      => $filters['mutasi'],
]));
$rp = static fn($v) => number_format((float) $v, 0, ',', '.');
$qtyFmt = static fn($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
    <div>
        <h4 class="mb-0">Laporan Kas</h4>
        <small class="text-muted">Buku kas &mdash; Saldo Awal, mutasi masuk/keluar, dan saldo berjalan</small>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/index.php?module=cash" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Kembali</a>
        <button type="button" class="btn btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i> Cetak</button>
        <a href="<?= BASE_URL ?>/index.php?module=cash&action=printReport<?= $qs ? '&' . e($qs) : '' ?>" class="btn btn-outline-danger" target="_blank"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <a href="<?= BASE_URL ?>/index.php?module=cash&action=exportReport<?= $qs ? '&' . e($qs) : '' ?>" class="btn btn-outline-success"><i class="bi bi-file-earmark-excel"></i> Excel</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="module" value="cash">
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
                <label class="form-label small text-muted mb-1">PIC</label>
                <?php if (!empty($picOptions)): ?>
                    <select name="pic" class="form-select form-select-sm">
                        <option value="">Semua PIC</option>
                        <?php foreach ($picOptions as $p): ?>
                            <option value="<?= e($p) ?>" <?= $filters['pic'] === $p ? 'selected' : '' ?>><?= e($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input type="text" name="pic" class="form-control form-control-sm" value="<?= e($filters['pic']) ?>" placeholder="Nama PIC">
                <?php endif; ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Kategori</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (string) $filters['category_id'] === (string) $c['id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small text-muted mb-1">Mutasi</label>
                <select name="mutasi" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    <option value="masuk"  <?= $filters['mutasi'] === 'masuk' ? 'selected' : '' ?>>Masuk</option>
                    <option value="keluar" <?= $filters['mutasi'] === 'keluar' ? 'selected' : '' ?>>Keluar</option>
                </select>
            </div>
            <div class="col-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-search"></i></button>
                <a href="<?= BASE_URL ?>/index.php?module=cash&action=report" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <h5 class="text-center mb-3">Laporan Kas</h5>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th>Tgl</th>
                        <th>No Bukti</th>
                        <th>Uraian</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Satuan</th>
                        <th class="text-end">Masuk</th>
                        <th class="text-end">Keluar</th>
                        <th class="text-end">Saldo Akhir</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="fw-bold">
                        <td colspan="7" class="text-center">Saldo Awal</td>
                        <td class="text-end"><?= $rp($ledger['saldo_awal']) ?></td>
                    </tr>
                    <?php if (empty($ledger['rows'])): ?>
                        <tr><td colspan="8" class="text-center text-muted py-3">Tidak ada transaksi Kas pada filter ini.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($ledger['rows'] as $row): ?>
                        <tr>
                            <td><?= $row['trx_date'] !== '' ? e(date('j-M-y', strtotime($row['trx_date']))) : '' ?></td>
                            <td><?= e($row['no_bukti']) ?></td>
                            <td><?= e($row['uraian']) ?></td>
                            <td class="text-end"><?= $qtyFmt($row['qty']) ?></td>
                            <td class="text-end"><?= $rp($row['satuan']) ?></td>
                            <td class="text-end"><?= $row['masuk'] > 0 ? $rp($row['masuk']) : '' ?></td>
                            <td class="text-end"><?= $row['keluar'] > 0 ? $rp($row['keluar']) : '' ?></td>
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
