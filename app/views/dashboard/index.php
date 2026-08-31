<?php $pageTitle = 'Dashboard'; ?>

<div class="page-header d-flex justify-content-between align-items-center mb-4">
    <div class="dashboard-greeting">
        <h4 class="mb-1">Selamat datang, <?= e(currentUserName()) ?> 👋</h4>
        <p class="text-muted mb-0">Ringkasan aktivitas stok proyek hari ini.</p>
    </div>
    <span class="dashboard-date">
        <i class="bi bi-calendar3"></i> <?= e(formatTanggalLengkap()) ?>
    </span>
</div>

<?php
    $alertCards = [];
    if ($notifFlags['selisih_barang'] && !empty($stats['selisih_belum_validasi']) && hasRole([ROLE_SUPER_ADMIN, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT])) {
        $alertCards[] = [
            'variant' => 'warning', 'icon' => 'bi-exclamation-triangle-fill', 'title' => 'Selisih Barang',
            'desc'    => (int) $stats['selisih_belum_validasi'] . ' item penerimaan barang dengan selisih belum divalidasi.',
            'url'     => route('validation'), 'cta' => 'Validasi Sekarang',
        ];
    }
    if ($notifFlags['stok_minimum'] && !empty($belowMinStockItems) && hasRole([ROLE_SUPER_ADMIN, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT])) {
        $alertCards[] = [
            'variant' => 'danger', 'icon' => 'bi-exclamation-octagon-fill', 'title' => 'Stok Minimum',
            'desc'    => count($belowMinStockItems) . ' barang dengan stok di bawah batas minimum.',
            'url'     => route('master_data'), 'cta' => 'Lihat Barang',
        ];
    }
    if ($notifFlags['po_belum_diproses'] && !empty($stats['po_belum_diproses']) && hasRole([ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE])) {
        $alertCards[] = [
            'variant' => 'info', 'icon' => 'bi-hourglass-split', 'title' => 'PO Belum Diproses',
            'desc'    => (int) $stats['po_belum_diproses'] . ' Purchase Order masih menunggu approval.',
            'url'     => route('purchase_order', 'index', ['status' => 'waiting_approval']), 'cta' => 'Lihat PO',
        ];
    }
?>
<?php if (!empty($alertCards)): ?>
<div class="mb-4">
    <div class="card-section-title mb-2">Peringatan &amp; Informasi Penting</div>
    <div class="row g-3">
        <?php foreach ($alertCards as $ac): ?>
            <div class="col-md-6 col-xl-4">
                <div class="alert-card variant-<?= e($ac['variant']) ?>">
                    <div class="d-flex gap-3">
                        <span class="alert-card-icon"><i class="bi <?= e($ac['icon']) ?>"></i></span>
                        <div>
                            <div class="alert-card-title"><?= e($ac['title']) ?></div>
                            <div class="alert-card-desc"><?= e($ac['desc']) ?></div>
                        </div>
                    </div>
                    <a href="<?= e($ac['url']) ?>" class="btn btn-sm btn-outline-<?= e($ac['variant']) ?> flex-shrink-0">
                        <?= e($ac['cta']) ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php
    $trendDiterima = $stats['diterima_hari_ini'] <=> $stats['diterima_kemarin'];
    $trendKeluar = $stats['keluar_hari_ini'] <=> $stats['keluar_kemarin'];
    $today = date('Y-m-d');
    $kpis = [
        ['icon' => 'bi-cart-check', 'color' => 'brand', 'label' => 'Total PO', 'value' => (int) $stats['total_po'],
            'url' => route('purchase_order')],
        ['icon' => 'bi-hourglass-split', 'color' => 'warning', 'label' => 'Barang Menunggu Datang', 'value' => (int) $stats['menunggu_datang'],
            'url' => route('purchase_order')],
        ['icon' => 'bi-box-seam', 'color' => 'success', 'label' => 'Barang Diterima Hari Ini', 'value' => (int) $stats['diterima_hari_ini'], 'trend' => $trendDiterima,
            'url' => route('goods_receipt', 'index', ['date_from' => $today, 'date_to' => $today])],
        ['icon' => 'bi-box-arrow-up', 'color' => 'danger', 'label' => 'Barang Keluar Hari Ini', 'value' => (int) $stats['keluar_hari_ini'], 'trend' => $trendKeluar,
            'url' => route('stock_out', 'index', ['date_from' => $today, 'date_to' => $today])],
        ['icon' => 'bi-clipboard-data', 'color' => 'info', 'label' => 'Stok Tersedia', 'breakdown' => $stats['stok_tersedia_per_satuan'],
            'url' => route('inventory')],
    ];
    if ($notifFlags['invoice_pending']) {
        $kpis[] = ['icon' => 'bi-receipt', 'color' => 'purple', 'label' => 'Invoice Belum Tertagih', 'value' => (int) $stats['invoice_pending'],
            'url' => route('sales_invoice', 'index', ['billing_status' => 'belum_tertagih'])];
    }
    $kpis[] = ['icon' => 'bi-shop', 'color' => 'brand', 'label' => 'Pembelian Offline', 'value' => (int) $stats['pembelian_offline'],
        'url' => route('offline_purchase')];
    if ($paymentProgress !== null) {
        $kpis[] = [
            'icon' => 'bi-credit-card', 'color' => 'purple', 'label' => 'Sisa Tagihan PO',
            'value' => formatRupiah($paymentProgress['remaining']),
            'percent' => $paymentProgress['percentage'],
            'url' => route('payment', 'summary'),
        ];
    }
?>
<div class="row g-3 mb-4">
    <?php foreach ($kpis as $kpi): ?>
        <div class="col-sm-6 col-lg-4 col-xl-3">
            <a href="<?= e($kpi['url']) ?>" class="kpi-card text-decoration-none text-reset">
                <div class="kpi-icon <?= e($kpi['color']) ?>">
                    <i class="bi <?= e($kpi['icon']) ?>"></i>
                </div>
                <div class="kpi-label"><?= e($kpi['label']) ?></div>
                <?php if (isset($kpi['breakdown'])): ?>
                    <?php if (empty($kpi['breakdown'])): ?>
                        <div class="kpi-value">0</div>
                    <?php else: ?>
                        <div class="kpi-breakdown">
                            <?php foreach (array_slice($kpi['breakdown'], 0, 4) as $b): ?>
                                <div class="kpi-breakdown-row">
                                    <span class="kpi-breakdown-unit"><?= e($b['unit']) ?></span>
                                    <span class="kpi-breakdown-qty"><?= number_format((float) $b['total'], 0, ',', '.') ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($kpi['breakdown']) > 4): ?>
                                <div class="kpi-breakdown-more">+<?= count($kpi['breakdown']) - 4 ?> satuan lainnya</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="kpi-value"><?= e($kpi['value']) ?></div>
                <?php endif; ?>
                <?php if (isset($kpi['trend'])): ?>
                    <?php if ($kpi['trend'] > 0): ?>
                        <span class="kpi-trend up"><i class="bi bi-arrow-up-short"></i> Naik dari kemarin</span>
                    <?php elseif ($kpi['trend'] < 0): ?>
                        <span class="kpi-trend down"><i class="bi bi-arrow-down-short"></i> Turun dari kemarin</span>
                    <?php else: ?>
                        <span class="kpi-trend flat"><i class="bi bi-dash"></i> Sama seperti kemarin</span>
                    <?php endif; ?>
                <?php elseif (isset($kpi['percent'])): ?>
                    <span class="kpi-trend flat"><i class="bi bi-percent"></i> <?= number_format($kpi['percent'], 1, ',', '.') ?>% dari total PO sudah dibayar</span>
                <?php endif; ?>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="card-section-title">Grafik Aktivitas Stok</div>
                    <div class="btn-group chart-period-filter" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-period="7d">7 Hari</button>
                        <button type="button" class="btn btn-outline-secondary" data-period="30d">30 Hari</button>
                        <button type="button" class="btn btn-outline-secondary" data-period="month">Bulan Ini</button>
                        <button type="button" class="btn btn-outline-secondary" data-period="year">Tahun Ini</button>
                    </div>
                </div>
                <canvas id="stockActivityChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="card-section-title mb-3">Aktivitas Terbaru</div>
                <?php if (empty($recentActivities)): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-clock-history empty-icon"></i>
                        <div class="empty-title">Belum ada aktivitas</div>
                        <div class="empty-desc mb-0">Aktivitas terbaru akan muncul di sini.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentActivities as $act): ?>
                        <div class="activity-feed-item">
                            <span class="activity-feed-icon">
                                <i class="bi <?= e($act['icon']) ?>"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="activity-feed-text"><?= e($act['description'] ?: ($act['module'] . ' ' . $act['action'])) ?></div>
                                <div class="activity-feed-meta">
                                    <?= e($act['full_name'] ?? 'Sistem') ?> &middot; <?= e(waktuLalu($act['created_at'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var canvas = document.getElementById('stockActivityChart');
    if (!canvas || !window.Chart) { return; }

    var chart = new Chart(canvas, {
        type: 'line',
        data: { labels: [], datasets: [
            { label: 'PO', data: [], borderColor: '#2A5298', backgroundColor: 'rgba(42,82,152,.08)', tension: .3, fill: true },
            { label: 'Barang Diterima', data: [], borderColor: '#16A34A', backgroundColor: 'rgba(22,163,74,.08)', tension: .3, fill: true },
            { label: 'Barang Keluar', data: [], borderColor: '#DC2626', backgroundColor: 'rgba(220,38,38,.08)', tension: .3, fill: true }
        ] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, usePointStyle: true } } }
        }
    });
    canvas.style.height = '300px';

    function loadPeriod(period) {
        fetch('<?= BASE_URL ?>/index.php?module=dashboard&action=chartData&period=' + encodeURIComponent(period))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.po;
                chart.data.datasets[1].data = data.diterima;
                chart.data.datasets[2].data = data.keluar;
                chart.update();
            })
            .catch(function () { if (window.notifyError) { notifyError('Gagal memuat data grafik.'); } });
    }

    var filterButtons = document.querySelectorAll('.chart-period-filter [data-period]');
    filterButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filterButtons.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            loadPeriod(btn.getAttribute('data-period'));
        });
    });

    loadPeriod('7d');
});
</script>
