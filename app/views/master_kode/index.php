<?php
/**
 * Master Kode: PUSAT PENGATURAN KELOMPOK KODE. Halaman ini hanya menampilkan
 * pilihan kelompok -- klik satu kelompok untuk atur prefix & lihat daftar
 * kodenya (app/views/master_kode/group.php). Data kode TIDAK ditampilkan
 * gabungan di sini (lihat app/models/CodeConfig.php untuk kenapa).
 */
$icons = [
    'item_stok_proyek' => 'bi-box-seam',
    'item_stok_lampu' => 'bi-lightbulb',
    'item_inventory_kantor' => 'bi-archive',
    'supplier' => 'bi-truck',
    'client' => 'bi-people',
    'warehouse' => 'bi-building',
    'project' => 'bi-kanban',
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Master Kode</h4>
        <small class="text-muted">Atur pola kode (prefix &amp; nomor otomatis) per kelompok. Data barang/supplier/dst tetap dikelola di Master Data masing-masing.</small>
    </div>
    <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Master Data
    </a>
</div>

<div class="row g-3">
    <?php foreach ($groups as $g): ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= BASE_URL ?>/master_kode/group?type=<?= e($g['type']) ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-2 text-primary"><i class="bi <?= e($icons[$g['type']] ?? 'bi-upc-scan') ?>"></i></div>
                    <div>
                        <div class="fw-semibold text-dark fs-5"><?= e($g['label']) ?></div>
                        <?php if (($g['prefixCount'] ?? 0) > 0): ?>
                            <span class="badge bg-success"><?= (int) $g['prefixCount'] ?> prefix</span>
                            <span class="badge bg-light text-dark border">.<?= e($g['masterCode'] ?: '-') ?></span>
                            <div class="small text-muted mt-1"><?= e(implode(', ', array_slice($g['prefixes'], 0, 4))) ?><?= count($g['prefixes']) > 4 ? '…' : '' ?></div>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Belum ada prefix</span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
