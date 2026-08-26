<?php

/**
 * Struktur menu sidebar terpusat -- satu sumber kebenaran dipakai oleh
 * sidebar.php (render menu + grup) DAN header.php (breadcrumb "Grup / Halaman").
 * Role tiap menu tetap ditarik dari config/permissions.php lewat permissionRoles(),
 * sama seperti sebelumnya -- cuma sekarang didefinisikan sekali di sini.
 */
function appMenus(): array
{
    return [
        ['label' => 'Dashboard', 'module' => 'dashboard', 'icon' => 'bi-speedometer2', 'roles' => permissionRoles('dashboard', 'view'), 'active' => true, 'group' => null],
        ['label' => 'Purchase Order', 'module' => 'purchase_order', 'icon' => 'bi-cart-check', 'roles' => permissionRoles('purchase_order', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Pembayaran', 'module' => 'payment', 'icon' => 'bi-credit-card', 'roles' => permissionRoles('payment', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Penerimaan Barang', 'module' => 'goods_receipt', 'icon' => 'bi-box-seam', 'roles' => permissionRoles('goods_receipt', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Validasi Barang', 'module' => 'validation', 'icon' => 'bi-check2-square', 'roles' => permissionRoles('validation', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Pengeluaran Barang', 'module' => 'stock_out', 'icon' => 'bi-box-arrow-up', 'roles' => permissionRoles('stock_out', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Stok & Opname', 'module' => 'inventory', 'icon' => 'bi-clipboard-data', 'roles' => permissionRoles('inventory', 'view'), 'active' => true, 'group' => 'Transaksi'],
        // Modul "Invoice" (AP, invoice masuk dari supplier -- InvoiceController,
        // InvoiceValidation, tabel invoices/invoice_validations) sempat cuma
        // disembunyikan dari sidebar (2026-08-24), lalu DIHAPUS TOTAL (2026-08-26,
        // sudah 0 data aktif sejak digantikan "Invoice Keluar" di bawah).
        ['label' => 'Invoice Keluar', 'module' => 'sales_invoice', 'icon' => 'bi-cash-stack', 'roles' => permissionRoles('sales_invoice', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Tanda Terima', 'module' => 'collection_receipt', 'icon' => 'bi-journal-check', 'roles' => permissionRoles('collection_receipt', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Pembelian Offline', 'module' => 'offline_purchase', 'icon' => 'bi-shop', 'roles' => permissionRoles('offline_purchase', 'view'), 'active' => true, 'group' => 'Transaksi'],
        ['label' => 'Laporan', 'module' => 'report', 'icon' => 'bi-bar-chart-line', 'roles' => permissionRoles('report', 'view'), 'active' => true, 'group' => 'Laporan & Administrasi'],
        ['label' => 'User Management', 'module' => 'user', 'icon' => 'bi-people', 'roles' => permissionRoles('user', 'view'), 'active' => true, 'group' => 'Laporan & Administrasi'],
        ['label' => 'Master Data', 'module' => 'master_data', 'icon' => 'bi-database', 'roles' => permissionRoles('master_data', 'view'), 'active' => true, 'group' => 'Laporan & Administrasi'],
        ['label' => 'Tempat Sampah', 'module' => 'trash', 'icon' => 'bi-trash3', 'roles' => permissionRoles('trash', 'view'), 'active' => true, 'group' => 'Laporan & Administrasi'],
        ['label' => 'Pengaturan Sistem', 'module' => 'settings', 'icon' => 'bi-gear', 'roles' => permissionRoles('settings', 'view'), 'active' => true, 'group' => 'Laporan & Administrasi'],
    ];
}

function menuGroupForModule(string $module): ?string
{
    foreach (appMenus() as $menu) {
        if ($menu['module'] === $module) {
            return $menu['group'];
        }
    }
    return null;
}

function menuLabelForModule(string $module): ?string
{
    foreach (appMenus() as $menu) {
        if ($menu['module'] === $module) {
            return $menu['label'];
        }
    }
    return null;
}

/**
 * Subtitle role untuk topbar (mis. "Administrator" untuk Super Admin, "Warehouse"
 * untuk Gudang). Key-nya role_slug dari session (currentUserRole()) -- BUKAN
 * hardcode username, supaya benar-benar dinamis mengikuti siapa yang login.
 */
function roleSubtitle(?string $roleSlug): string
{
    $map = [
        ROLE_SUPER_ADMIN     => 'Administrator',
        ROLE_FINANCE         => 'Finance',
        ROLE_GUDANG          => 'Warehouse',
        ROLE_PROJECT_MANAGER => 'Project Manager',
    ];
    return $map[$roleSlug] ?? '';
}
