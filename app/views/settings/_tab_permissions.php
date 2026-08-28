<?php
// Hanya tampilkan kolom role yang AKTIF dipakai sistem role baru (Revisi 9).
$activeRoleSlugs = [
    ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING,
    ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER,
];
$labelMap = roleLabelMap();
$roleLabels = [];
foreach ($activeRoleSlugs as $slug) {
    $roleLabels[$slug] = $labelMap[$slug] ?? $slug;
}
$moduleLabels = [
    'dashboard' => 'Dashboard', 'account' => 'Akun Saya', 'purchase_order' => 'Purchase Order', 'payment' => 'Pembayaran',
    'goods_receipt' => 'Penerimaan Barang', 'validation' => 'Validasi Barang', 'stock_out' => 'Pengeluaran Barang',
    'inventory' => 'Stok & Opname', 'offline_purchase' => 'Pembelian Offline',
    'sales_invoice' => 'Invoice Keluar', 'delivery_note' => 'Surat Jalan', 'collection_receipt' => 'Tanda Terima',
    'cash' => 'Kas', 'cash_category' => 'Kategori Kas', 'user_pic' => 'PIC Mapping',
    'report' => 'Laporan', 'user' => 'User Management', 'master_data' => 'Master Data', 'master_kode' => 'Master Kode',
    'supplier' => 'Supplier', 'client' => 'Client', 'project' => 'Project', 'item' => 'Barang', 'item_category' => 'Kategori Barang',
    'unit' => 'Satuan', 'warehouse' => 'Gudang', 'payment_method' => 'Metode Pembayaran', 'signature' => 'Tanda Tangan',
    'dp_percentage' => 'Persentase DP', 'settings' => 'Pengaturan Sistem', 'trash' => 'Tempat Sampah',
];
$actionLabels = [
    'view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete',
    'approve' => 'Approve', 'validate' => 'Validate', 'quick_add' => 'Quick Add', 'complete' => 'Complete',
    'restore' => 'Restore', 'force_delete' => 'Hapus Permanen', 'delete_stock' => 'Hapus Kartu Stok',
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
