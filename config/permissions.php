<?php
/**
 * Matrix RBAC terpusat: module => action => daftar role_slug yang diizinkan.
 * Satu-satunya sumber kebenaran untuk hak akses -- dipakai oleh Middleware
 * (gate server-side), permission_helper (tampilkan/sembunyikan UI), dan
 * sidebar (menu mana yang muncul). Ubah aturan akses cukup di file ini.
 *
 * ================= REVISI 9 (2026-08-28) =================
 * Role baru menggantikan Finance/Gudang:
 *   super_admin     (SA) -- akses tertinggi, semua modul & aksi.
 *   purchase        (PU) -- PO, Penerimaan, Pengeluaran, Invoice Keluar, Kas (PIC terkait).
 *   accounting      (AC) -- SEMUA menu, View + Create + Edit. TIDAK PERNAH Delete.
 *   pic_project     (PP) -- Penerimaan, Pengeluaran, Kas (PIC terkait), Validasi, Laporan Kartu Stok.
 *   admin_project   (AP) -- Penerimaan, Pengeluaran, Kas (PIC terkait).
 *   project_manager (PM) -- Penerimaan, Pengeluaran, Kas (semua project), Validasi,
 *                            Laporan Kartu Stok. VIEW ONLY (tidak ada create/edit/delete).
 *
 * Dua aturan menyeluruh yang dijaga lewat matrix ini:
 *   - 'accounting' TIDAK PERNAH muncul di action delete/force_delete/delete_stock.
 *   - 'project_manager' TIDAK PERNAH muncul di action create/edit/delete/validate/restore.
 *
 * Visibilitas Kas per-PIC (Purchase/PIC Project/Admin Project hanya lihat Kas
 * milik PIC terkaitnya) BUKAN di file ini -- ditegakkan server-side di
 * CashController lewat mapping user_pic_assignments. File ini hanya menentukan
 * "boleh buka modul Kas / boleh membuat baris Kas" pada level modul.
 */

return [
    'dashboard' => [
        'view' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER],
    ],

    // Pengaturan akun MILIK SENDIRI -- setiap role yang login boleh akses.
    // AccountController hanya mengecek 'view' (halaman "Akun Saya" -- ubah
    // profil/password sendiri). Tidak ada action 'edit' terpisah.
    'account' => [
        'view' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER],
    ],

    'purchase_order' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE],
    ],

    'payment' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'create' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    'goods_receipt' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE],
    ],

    'validation' => [
        'view'     => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_PROJECT_MANAGER],
        'validate' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PIC_PROJECT],
    ],

    'stock_out' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE],
    ],

    'inventory' => [
        'view'     => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'create'   => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'complete' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'delete'   => [ROLE_SUPER_ADMIN], // hapus stock opname (draft)
        // Hapus baris kartu stok (Stok Barang) -- SENGAJA khusus Super Admin.
        'delete_stock' => [ROLE_SUPER_ADMIN],
    ],

    // Pembelian lapangan: Purchase & Accounting boleh catat, hanya hapus dibatasi.
    'offline_purchase' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE],
    ],

    // 'view' = boleh buka modul Laporan sama sekali. Pembatasan granular
    // (PIC Project & Project Manager HANYA boleh Laporan Kartu Stok) ada di
    // ReportController::guardReportScope().
    'report' => [
        'view' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_PROJECT_MANAGER],
        // Boleh melihat kolom HARGA di Laporan Stok Barang (Cetak/Export) +
        // memilih toggle Tampilkan/Tanpa harga. Role lain: output selalu tanpa harga.
        'stock_price' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
    ],

    // Tutup Bulan (Laporan -> Tutup Bulan) -- SUPER ADMIN ONLY, terkunci
    // (lihat PERMISSION_LOCKED_MODULES). Role lain tidak boleh menutup/membuka periode.
    'period_lock' => [
        'view'   => [ROLE_SUPER_ADMIN],
        'close'  => [ROLE_SUPER_ADMIN],
        'reopen' => [ROLE_SUPER_ADMIN],
    ],

    // Manajemen user -- khusus Super Admin. Tidak ada 'delete'.
    'user' => [
        'view'   => [ROLE_SUPER_ADMIN],
        'create' => [ROLE_SUPER_ADMIN],
        'edit'   => [ROLE_SUPER_ADMIN],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    // ================= Master Data =================
    'master_data' => [
        'view' => [ROLE_SUPER_ADMIN],
    ],

    'master_kode' => [
        'view' => [ROLE_SUPER_ADMIN],
        'edit' => [ROLE_SUPER_ADMIN],
    ],

    'supplier' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
    ],

    'project' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
    ],

    'client' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
    ],

    // Invoice Keluar (AR/Sales Invoice) -- HME menagih ke client.
    'sales_invoice' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE],
    ],

    // Surat Jalan -- dibuat dari baris Pengeluaran Barang, ikut role 'stock_out'.
    'delivery_note' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    // Tanda Terima (tanda terima penagihan) -- mengemas beberapa Invoice Keluar.
    'collection_receipt' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    // ================= KAS (Revisi 9) =================
    // 'view'   -> boleh buka modul Kas (isi list dibatasi per-PIC di controller).
    // 'delete' -> Accounting & Project Manager SENGAJA tidak ada. Purchase/PIC
    //             Project/Admin Project hanya bisa hapus baris PIC terkaitnya
    //             (dicek assertCanTouch() di CashController). Hapus = soft-delete
    //             (masuk Trash), restore/permanent tetap di modul Trash (Super Admin).
    'cash' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER],
        'create' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        // Edit & hapus transaksi Kas: Super Admin, Accounting, tim Purchase, dan
        // tim Project (PIC Project & Admin Project) -- ter-scope PIC-nya sendiri.
        // Project Manager: lihat saja. Transaksi/edit/hapus di BULAN YANG SUDAH
        // DITUTUP tetap ditolak untuk semua (assertPeriodOpen di CashController).
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        'delete' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
        // Kartu saldo Kas di halaman utama Kas. Super Admin & Accounting melihat
        // SEMUA divisi + Total Saldo. Purchase / PIC Project / Admin Project /
        // Project Manager melihat HANYA saldo divisi mereka sendiri (mis.
        // Purchase -> "Saldo Kas Purchase", role project -> "Saldo Kas Project").
        'view_balance' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT, ROLE_PROJECT_MANAGER],
    ],

    // Master Kategori Kas -- Accounting boleh kelola (view/create/edit) tapi
    // TIDAK hapus (aturan global: Accounting no delete).
    'cash_category' => [
        'view'   => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'create' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'edit'   => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

    // Validasi Kas -- persetujuan transaksi Kas. Routing per-divisi ditegakkan
    // di CashValidationController (kasCanValidateDivision): accounting utk divisi
    // accounting, purchase utk purchase, project_manager utk project, super_admin
    // semua. pic_project & admin_project = PEMBUAT, bukan validator.
    'cash_validation' => [
        'view'     => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE, ROLE_PROJECT_MANAGER],
        'validate' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE, ROLE_PROJECT_MANAGER],
    ],

    // Mapping User -> PIC (menentukan siapa lihat Kas siapa). Sensitif =
    // khusus Super Admin.
    'user_pic' => [
        'view'   => [ROLE_SUPER_ADMIN],
        'create' => [ROLE_SUPER_ADMIN],
        'edit'   => [ROLE_SUPER_ADMIN],
        'delete' => [ROLE_SUPER_ADMIN],
        // quick-add PIC dari form Kas -- siapa pun yang boleh membuat Kas.
        // Non-Super Admin hanya bisa mengaitkan PIC ke akunnya sendiri
        // (dipaksa di UserPicController::quickStore()).
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
    ],

    'item' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
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
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT],
    ],

    'warehouse' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING],
    ],

    'payment_method' => [
        'view'      => [ROLE_SUPER_ADMIN],
        'create'    => [ROLE_SUPER_ADMIN],
        'edit'      => [ROLE_SUPER_ADMIN],
        'delete'    => [ROLE_SUPER_ADMIN],
        'quick_add' => [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING],
    ],

    'signature' => [
        'view'   => [ROLE_SUPER_ADMIN],
        'create' => [ROLE_SUPER_ADMIN],
        'edit'   => [ROLE_SUPER_ADMIN],
        'delete' => [ROLE_SUPER_ADMIN],
    ],

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

    // Tempat Sampah -- administratif lintas modul, khusus Super Admin.
    'trash' => [
        'view'         => [ROLE_SUPER_ADMIN],
        'restore'      => [ROLE_SUPER_ADMIN],
        'force_delete' => [ROLE_SUPER_ADMIN],
    ],
];
