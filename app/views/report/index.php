<?php
$reports = [
    ['key' => 'po', 'label' => 'Purchase Order', 'icon' => 'bi-cart-check'],
    ['key' => 'payment', 'label' => 'Pembayaran', 'icon' => 'bi-credit-card'],
    ['key' => 'goodsReceipt', 'label' => 'Penerimaan Barang', 'icon' => 'bi-box-seam'],
    ['key' => 'stockOut', 'label' => 'Pengeluaran Barang', 'icon' => 'bi-box-arrow-up'],
    ['key' => 'inventory', 'label' => 'Stok Barang', 'icon' => 'bi-clipboard-data'],
    ['key' => 'stockOpname', 'label' => 'Stok Opname', 'icon' => 'bi-clipboard-check'],
    ['key' => 'invoice', 'label' => 'Invoice Keluar', 'icon' => 'bi-cash-stack'],
    ['key' => 'offlinePurchase', 'label' => 'Pembelian Offline', 'icon' => 'bi-shop'],
    ['key' => 'activityLog', 'label' => 'Riwayat Aktivitas (Audit Log)', 'icon' => 'bi-clock-history'],
];
?>
<div class="mb-3">
    <h4 class="mb-0">Laporan</h4>
    <small class="text-muted">Pilih jenis laporan -- setiap laporan bisa difilter, dicetak, dan diekspor</small>
</div>

<div class="row g-3">
    <?php foreach ($reports as $r): ?>
        <div class="col-md-4">
            <a href="<?= BASE_URL ?>/index.php?module=report&action=<?= e($r['key']) ?>"
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
