<?php
$subModules = [
    ['label' => 'Supplier', 'module' => 'supplier', 'icon' => 'bi-truck', 'desc' => 'Data pemasok barang'],
    ['label' => 'Client', 'module' => 'client', 'icon' => 'bi-people', 'desc' => 'Data client/pemilik proyek'],
    ['label' => 'Project', 'module' => 'project', 'icon' => 'bi-kanban', 'desc' => 'Data proyek berjalan'],
    ['label' => 'Barang', 'module' => 'item', 'icon' => 'bi-box-seam', 'desc' => 'Katalog master barang'],
    ['label' => 'Kategori Barang', 'module' => 'item_category', 'icon' => 'bi-tags', 'desc' => 'Pengelompokan barang'],
    ['label' => 'Satuan', 'module' => 'unit', 'icon' => 'bi-rulers', 'desc' => 'Satuan ukur barang'],
    ['label' => 'Gudang', 'module' => 'warehouse', 'icon' => 'bi-building', 'desc' => 'Data gudang penyimpanan'],
    ['label' => 'Metode Pembayaran', 'module' => 'payment_method', 'icon' => 'bi-credit-card-2-front', 'desc' => 'Cara pembayaran ke supplier'],
    ['label' => 'Tanda Tangan', 'module' => 'signature', 'icon' => 'bi-pen', 'desc' => 'Tanda tangan untuk dokumen cetak'],
    ['label' => 'Persentase DP', 'module' => 'dp_percentage', 'icon' => 'bi-percent', 'desc' => 'Pilihan Tagihan DP untuk Invoice Keluar'],
    ['label' => 'Kategori Kas', 'module' => 'cash_category', 'icon' => 'bi-cash-stack', 'desc' => 'Kategori transaksi Kas (Material Projek, Inventory Kantor, dst)'],
    ['label' => 'PIC Kas', 'module' => 'user_pic', 'icon' => 'bi-person-badge', 'desc' => 'Kaitkan user ke PIC untuk pembatasan akses Kas'],
    ['label' => 'Master Kode', 'module' => 'master_kode', 'icon' => 'bi-upc-scan', 'desc' => 'Atur prefix & nomor otomatis kode Barang/Supplier/Client/Gudang/Project'],
];
?>
<div class="mb-3">
    <h4 class="mb-0">Master Data</h4>
    <small class="text-muted">Kelola seluruh data acuan yang dipakai di modul transaksi</small>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-3 me-3"><i class="bi bi-truck fs-3"></i></div>
                <div>
                    <div class="text-muted small">Supplier</div>
                    <div class="fs-4 fw-bold"><?= (int) $stats['total_supplier'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-success bg-opacity-10 text-success rounded-3 p-3 me-3"><i class="bi bi-kanban fs-3"></i></div>
                <div>
                    <div class="text-muted small">Project</div>
                    <div class="fs-4 fw-bold"><?= (int) $stats['total_project'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-info bg-opacity-10 text-info rounded-3 p-3 me-3"><i class="bi bi-box-seam fs-3"></i></div>
                <div>
                    <div class="text-muted small">Barang</div>
                    <div class="fs-4 fw-bold"><?= (int) $stats['total_item'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 p-3 me-3"><i class="bi bi-building fs-3"></i></div>
                <div>
                    <div class="text-muted small">Gudang</div>
                    <div class="fs-4 fw-bold"><?= (int) $stats['total_warehouse'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg">
        <a href="<?= BASE_URL ?>/index.php?module=report&action=inventory" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 <?= $stats['below_min_stock'] > 0 ? 'border-start border-4 border-warning' : '' ?>">
                <div class="card-body d-flex align-items-center">
                    <div class="bg-warning bg-opacity-10 text-warning rounded-3 p-3 me-3"><i class="bi bi-exclamation-triangle fs-3"></i></div>
                    <div>
                        <div class="text-muted small">Barang Stok Minimum</div>
                        <div class="fs-4 fw-bold text-dark"><?= (int) $stats['below_min_stock'] ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
</div>

<h6 class="mb-3">Kelola Master Data</h6>
<div class="row g-3">
    <?php foreach ($subModules as $m): ?>
        <div class="col-md-6 col-lg-4">
            <a href="<?= BASE_URL ?>/index.php?module=<?= e($m['module']) ?>" class="card border-0 shadow-sm text-decoration-none h-100">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="fs-3 text-primary"><i class="bi <?= e($m['icon']) ?>"></i></div>
                    <div>
                        <div class="fw-semibold text-dark"><?= e($m['label']) ?></div>
                        <small class="text-muted"><?= e($m['desc']) ?></small>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
