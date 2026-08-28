<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/DashboardStat.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/Payment.php';

class DashboardController extends Controller
{
    /**
     * Ikon per modul untuk feed "Aktivitas Terbaru" -- fallback ke ikon generik
     * kalau modul belum terdaftar di sini (mis. modul baru di masa depan).
     */
    private const ACTIVITY_ICONS = [
        'auth'             => 'bi-box-arrow-in-right',
        'purchase_order'   => 'bi-cart-check',
        'payment'          => 'bi-credit-card',
        'goods_receipt'    => 'bi-box-seam',
        'validation'       => 'bi-check2-square',
        'stock_out'        => 'bi-box-arrow-up',
        'inventory'        => 'bi-clipboard-data',
        'offline_purchase' => 'bi-shop',
        'user'             => 'bi-people',
        'supplier'         => 'bi-truck',
        'project'          => 'bi-diagram-3',
        'item'             => 'bi-box',
        'item_category'    => 'bi-tags',
        'unit'             => 'bi-rulers',
        'warehouse'        => 'bi-building',
        'settings'         => 'bi-gear',
    ];

    public function __construct()
    {
        Middleware::requirePermission('dashboard', 'view');
    }

    public function index()
    {
        $statModel = new DashboardStat();
        $stats = $statModel->getAllStats();
        $stats['po_belum_diproses'] = $statModel->poBelumDiproses();
        $stats['diterima_kemarin'] = $statModel->barangDiterimaKemarin();
        $stats['keluar_kemarin'] = $statModel->barangKeluarKemarin();

        $settingModel = new SystemSetting();
        $notifFlags = [
            'selisih_barang'    => $settingModel->getBool('notify_selisih_barang', true),
            'invoice_pending'   => $settingModel->getBool('notify_invoice_pending', true),
            'stok_minimum'      => $settingModel->getBool('notify_stok_minimum', true),
            'po_belum_diproses' => $settingModel->getBool('notify_po_belum_diproses', true),
        ];

        $belowMinStockItems = [];
        if ($notifFlags['stok_minimum']) {
            $belowMinStockItems = (new Item())->belowMinStockList();
        }

        $recentActivities = array_slice((new ActivityLog())->listWithFilters(), 0, 8);
        foreach ($recentActivities as &$activity) {
            $activity['icon'] = self::ACTIVITY_ICONS[$activity['module']] ?? 'bi-info-circle';
        }
        unset($activity);

        $paymentProgress = null;
        if (hasRole([ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE])) {
            $paymentProgress = (new Payment())->overallProgress();
        }

        $this->view('dashboard/index', [
            'stats'              => $stats,
            'notifFlags'         => $notifFlags,
            'belowMinStockItems' => $belowMinStockItems,
            'recentActivities'   => $recentActivities,
            'paymentProgress'    => $paymentProgress,
        ]);
    }

    /**
     * Endpoint AJAX untuk filter periode grafik "Aktivitas Stok" (dipanggil dari
     * dashboard/index.php via fetch()). Tidak mengubah query dashboard utama --
     * dipisah supaya ganti periode tidak perlu reload seluruh halaman.
     */
    public function chartData()
    {
        $period = $_GET['period'] ?? '7d';
        if (!in_array($period, ['7d', '30d', 'month', 'year'], true)) {
            $period = '7d';
        }

        $this->json((new DashboardStat())->chartData($period));
    }
}
