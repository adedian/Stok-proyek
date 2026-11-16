<?php
/** Riwayat perubahan saldo bank manual. Variabel: $bank (opsional), $rows, $backQuery. */
$back = BASE_URL . '/index.php?' . http_build_query(['module' => 'pengajuan_budget_report'] + $backQuery);
$fmt = function ($v) {
    $v = (float) $v;
    return ($v < 0 ? '-' : '') . number_format(abs($v), fmod(abs($v), 1) == 0.0 ? 0 : 2, '.', ',');
};
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">Riwayat Saldo Bank<?= $bank ? ' &mdash; ' . e($bank['bank_name']) : '' ?></h4>
        <small class="text-muted">Setiap perubahan saldo manual tercatat permanen (tidak bisa diubah atau dihapus)</small>
    </div>
    <div class="d-flex gap-2">
        <?php if ($bank): ?>
            <a href="<?= BASE_URL ?>/index.php?<?= e(http_build_query(['module' => 'pengajuan_budget_report', 'action' => 'saldoHistory'])) ?>" class="btn btn-outline-secondary">Semua Rekening</a>
        <?php endif; ?>
        <a href="<?= e($back) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Laporan</a>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Waktu Perubahan</th>
                        <th>Rekening</th>
                        <th>Berlaku Mulai</th>
                        <th class="text-end">Saldo Sebelumnya</th>
                        <th class="text-end">Saldo Baru</th>
                        <th class="text-end">Selisih</th>
                        <th>Pengguna</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="p-0"><div class="empty-state"><i class="bi bi-clock-history empty-icon"></i><div class="empty-title">Belum ada perubahan saldo manual.</div></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): $d = (float) $r['diff_amount']; ?>
                        <tr>
                            <td data-label="Waktu" class="text-nowrap"><?= e(date('d/m/Y H:i', strtotime($r['created_at']))) ?></td>
                            <td data-label="Rekening"><?= e(MasterBank::reportLabel($r)) ?> <span class="badge bg-light text-secondary border"><?= e(strtoupper($r['jenis'])) ?></span></td>
                            <td data-label="Berlaku" class="text-nowrap"><?= e(date('d/m/Y', strtotime($r['effective_date']))) ?></td>
                            <td data-label="Sebelumnya" class="text-end text-nowrap"><?= e($fmt($r['old_balance'])) ?></td>
                            <td data-label="Baru" class="text-end text-nowrap fw-semibold"><?= e($fmt($r['new_balance'])) ?></td>
                            <td data-label="Selisih" class="text-end text-nowrap <?= $d > 0 ? 'text-success' : ($d < 0 ? 'text-danger' : '') ?>"><?= ($d > 0 ? '+' : '') . e($fmt($d)) ?></td>
                            <td data-label="Pengguna"><?= e($r['user_name'] ?? '-') ?></td>
                            <td data-label="Keterangan" style="min-width:240px"><?= e($r['note']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
