<?php
/**
 * Daftar kartu laporan disiapkan di ReportController::availableReports() --
 * sudah difilter sesuai izin modul tiap user (konsisten dgn guardReportScope()).
 */
$reports = $reports ?? [];
?>
<div class="mb-3">
    <h4 class="mb-0">Laporan</h4>
    <small class="text-muted">Pilih jenis laporan -- setiap laporan bisa difilter, dicetak, dan diekspor</small>
</div>

<div class="row g-3">
    <?php foreach ($reports as $r): ?>
        <div class="col-md-4">
            <a href="<?= isset($r['url']) ? e($r['url']) : e(route('report', $r['key'])) ?>"
               class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-3 text-primary"><i class="bi <?= e($r['icon']) ?>"></i></div>
                    <div>
                        <div class="fw-semibold text-dark"><?= e($r['label']) ?></div>
                        <small class="text-muted">Lihat, cetak, & export</small>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
