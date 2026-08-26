<?php
$roleLabels = [
    ROLE_SUPER_ADMIN     => 'Super Admin',
    ROLE_FINANCE         => 'Finance',
    ROLE_GUDANG          => 'Gudang',
    ROLE_PROJECT_MANAGER => 'Project Manager',
];
$moduleLabels = [
    'dashboard' => 'Dashboard', 'purchase_order' => 'Purchase Order', 'payment' => 'Pembayaran',
    'goods_receipt' => 'Penerimaan Barang', 'validation' => 'Validasi Barang', 'stock_out' => 'Pengeluaran Barang',
    'inventory' => 'Stok & Opname', 'offline_purchase' => 'Pembelian Offline',
    'report' => 'Laporan', 'user' => 'User Management', 'master_data' => 'Master Data',
    'supplier' => 'Supplier', 'project' => 'Project', 'item' => 'Barang', 'item_category' => 'Kategori Barang',
    'unit' => 'Satuan', 'warehouse' => 'Gudang', 'settings' => 'Pengaturan Sistem',
];
$actionLabels = [
    'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete',
    'approve' => 'Approve', 'validate' => 'Validate', 'quick_add' => 'Quick Add', 'complete' => 'Complete',
];
?>
<div class="alert alert-light border small mb-3">
    <i class="bi bi-info-circle"></i> Matrix ini bersumber langsung dari <code>config/permissions.php</code> --
    tampilan saja (read-only), ubah aturan akses lewat file tersebut.
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Modul</th>
                        <th>Aksi</th>
                        <?php foreach ($roleLabels as $label): ?>
                            <th class="text-center"><?= e($label) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissionMatrix as $module => $actions): ?>
                        <?php foreach ($actions as $action => $roles): ?>
                            <tr>
                                <td><?= e($moduleLabels[$module] ?? $module) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($actionLabels[$action] ?? $action) ?></span></td>
                                <?php foreach (array_keys($roleLabels) as $roleSlug): ?>
                                    <td class="text-center">
                                        <?php if (in_array($roleSlug, $roles, true)): ?>
                                            <i class="bi bi-check-circle-fill text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-dash text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
