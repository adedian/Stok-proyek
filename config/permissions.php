<?php
/**
 * Matrix RBAC terpusat: module => action => daftar role_slug yang diizinkan.
 * Satu-satunya sumber kebenaran untuk hak akses -- dipakai oleh Middleware
 * (gate server-side), permission_helper (tampilkan/sembunyikan UI), dan
 * sidebar (menu mana yang muncul). Ubah aturan akses cukup di file ini.
 */

return [
    'dashboard' => [
        'view' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG, ROLE_PROJECT_MANAGER],
    ],

    // Pengaturan akun MILIK SENDIRI -- setiap role yang login boleh akses.
    // Beda dengan 'user' (manajemen akun ORANG LAIN, khusus Super Admin) dan
    // 'settings' (pengaturan sistem global, khusus Super Admin).
    'account' => [
        'view' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG, ROLE_PROJECT_MANAGER],
        'edit' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG, ROLE_PROJECT_MANAGER],
    ],

    'purchase_order' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    'payment' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'create' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    'goods_receipt' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'create' => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
    ],

    'validation' => [
        'view'     => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'validate' => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
    ],

    'stock_out' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'create' => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
    ],

    'inventory' => [
        'view'     => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'create'   => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'complete' => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'delete'   => [ROLE_SUPER_ADMIN, ROLE_GUDANG], // hapus stock opname (draft)
        // Hapus baris kartu stok (Stok Barang) -- SENGAJA khusus Super Admin,
        // beda dari 'delete' di atas (itu untuk hapus draft opname, boleh Gudang).
        'delete_stock' => [ROLE_SUPER_ADMIN],
    ],

    // Sengaja tetap terbuka untuk semua role (keputusan produk: pembelian
    // lapangan bisa dicatat siapa saja), hanya hapus yang dibatasi.
    'offline_purchase' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG, ROLE_PROJECT_MANAGER],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG, ROLE_PROJECT_MANAGER],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    'report' => [
        'view' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_PROJECT_MANAGER],
    ],

    // Manajemen user -- khusus Super Admin. Tidak ada 'delete': user
    // dinonaktifkan (status), bukan dihapus (banyak tabel lain FK ke created_by).
    'user' => [
        'view'   => [ROLE_SUPER_ADMIN],
        'create' => [ROLE_SUPER_ADMIN],
        'edit'   => [ROLE_SUPER_ADMIN],
    ],

    // ================= Master Data (Phase: Master Data & Pengaturan) =================
    // Manajemen penuh (list/tambah/edit/hapus di modul Master Data) khusus Super Admin.
    // 'quick_add' LEBIH LONGGAR -- role yang boleh membuat transaksi terkait (PO, stock
    // out, opname, dst) juga boleh nambah master baru langsung dari form transaksi itu,
    // walau bukan Super Admin. Ini yang bikin tombol "+ Tambah" di form PO bisa dipakai Finance.
    'master_data' => [
        'view' => [ROLE_SUPER_ADMIN],
    ],

    // Registry gabungan read/edit kode dari 6 tabel master (item/supplier/client/
    // project/warehouse/storage_location) -- tidak ada create/delete di sini karena
    // penambahan/penghapusan entity tetap lewat modul aslinya masing-masing.
    'master_kode' => [
        'view' => [ROLE_SUPER_ADMIN],
        'edit' => [ROLE_SUPER_ADMIN],
    ],

    'supplier' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    'project' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG],
    ],

    'client' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    // Invoice Keluar (AR/Sales Invoice) -- HME menagih ke client, terpisah dari
    // 'invoice' (AP, supplier menagih ke HME) yang sudah ada. Owner: Finance.
    'sales_invoice' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'create' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    // Surat Jalan -- dibuat dari baris Pengeluaran Barang yang sudah ada, jadi
    // role-nya mengikuti 'stock_out' (Gudang).
    'delivery_note' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'create' => [ROLE_SUPER_ADMIN, ROLE_GUDANG],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    // Tanda Terima (tanda terima penagihan) -- mengemas beberapa Invoice Keluar,
    // owner: Finance.
    'collection_receipt' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'create' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    'item' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG],
    ],

    'item_category' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
    ],

    'unit' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_FINANCE, ROLE_GUDANG],
    ],

    'warehouse' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    'payment_method' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_FINANCE],
    ],

    'signature' => [
        'view'   => [ROLE_SUPER_ADMIN],
        'create' => [ROLE_SUPER_ADMIN],
        'edit'   => [ROLE_SUPER_ADMIN],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    // Master persentase Tagihan DP -- dipakai sebagai pilihan dropdown di
    // Invoice Keluar. Khusus Super Admin, sama seperti master lain (Tanda Tangan dkk).
    'dp_percentage' => [
        'view'   => [ROLE_SUPER_ADMIN],
        'create' => [ROLE_SUPER_ADMIN],
        'edit'   => [ROLE_SUPER_ADMIN],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    'settings' => [
        'view' => [ROLE_SUPER_ADMIN],
        'edit' => [ROLE_SUPER_ADMIN],
    ],
];
