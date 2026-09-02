<?php

/**
 * Router sederhana
 * URL format: index.php?module=purchase_order&action=create
 * Default module: dashboard (kalau user sudah login) / auth (kalau belum)
 */
class Router
{
    // Whitelist module -> nama file controller & class
    private array $moduleMap = [
        'auth'            => 'AuthController',
        'account'         => 'AccountController',
        'dashboard'       => 'DashboardController',
        'purchase_order'  => 'PurchaseOrderController',
        'payment'         => 'PaymentController',
        'goods_receipt'   => 'GoodsReceiptController',
        'validation'      => 'ValidationController',
        'stock_out'       => 'StockOutController',
        'inventory'       => 'InventoryController',
        'offline_purchase' => 'OfflinePurchaseController',
        'report'          => 'ReportController',
        'user'            => 'UserController',
        'master_data'       => 'MasterDataController',
        'master_kode'       => 'MasterKodeController',
        'supplier'          => 'SupplierController',
        'client'            => 'ClientController',
        'project'           => 'ProjectController',
        'item'              => 'ItemController',
        'item_category'     => 'ItemCategoryController',
        'unit'              => 'UnitController',
        'warehouse'         => 'WarehouseController',
        'payment_method'    => 'PaymentMethodController',
        'signature'         => 'SignatureController',
        'settings'          => 'SettingsController',
        'sales_invoice'      => 'SalesInvoiceController',
        'delivery_note'      => 'DeliveryNoteController',
        'collection_receipt' => 'CollectionReceiptController',
        'dp_percentage'      => 'DpPercentageController',
        'trash'              => 'TrashController',
        'cash'               => 'CashController',
        'cash_validation'    => 'CashValidationController',
        'cash_category'      => 'CashCategoryController',
        'user_pic'           => 'UserPicController',
        'period_lock'        => 'PeriodLockController',
        'file'               => 'FileController',
    ];

    public function dispatch(): void
    {
        [$module, $action] = $this->resolveRoute();

        // Supaya kode lama yang membaca $_GET['module']/['action']/['id']
        // (sidebar highlight, breadcrumb, denyAccess, controller) tetap jalan
        // baik lewat URL bersih maupun format lama.
        $_GET['module'] = $module;
        $_GET['action'] = $action;
        $_REQUEST['module'] = $module;
        $_REQUEST['action'] = $action;

        // Validasi module ada di whitelist -> cegah Local File Inclusion
        if (!array_key_exists($module, $this->moduleMap)) {
            $this->notFound();
            return;
        }

        $controllerClass = $this->moduleMap[$module];
        $controllerFile = ROOT_PATH . "/app/controllers/{$controllerClass}.php";

        if (!file_exists($controllerFile)) {
            $this->notFound();
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controllerClass)) {
            $this->notFound();
            return;
        }

        $controller = new $controllerClass();

        // Hanya boleh memanggil method PUBLIC milik controller (termasuk hasil AJAX handler).
        // method_exists() sudah cukup karena semua action controller dideklarasikan public,
        // sementara helper di base Controller (view/redirect/json) dideklarasikan protected
        // sehingga otomatis tidak lolos reflection publik ini.
        if (!method_exists($controller, $action)) {
            $this->notFound();
            return;
        }

        $reflection = new ReflectionMethod($controller, $action);
        if (!$reflection->isPublic()) {
            $this->notFound();
            return;
        }

        $controller->$action();
    }

    /**
     * Tentukan [module, action] dari:
     *   1. URL bersih  -> /module/action[/id]   (path setelah APP_BASE_PATH)
     *   2. Fallback     -> index.php?module=..&action=..  (format lama)
     * Segmen ke-3 yang berupa angka diperlakukan sebagai ?id=.
     */
    private function resolveRoute(): array
    {
        $rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $rawPath = rawurldecode($rawPath);

        $base = defined('APP_BASE_PATH') ? APP_BASE_PATH : '';
        if ($base !== '' && strncmp($rawPath, $base, strlen($base)) === 0) {
            $rawPath = substr($rawPath, strlen($base));
        }

        $rawPath = trim($rawPath, '/');
        if ($rawPath === 'index.php') {
            $rawPath = '';
        }

        if ($rawPath !== '') {
            $segments = explode('/', $rawPath);
            $module = $segments[0];

            if (isset($segments[1]) && $segments[1] !== '') {
                // Action eksplisit di path -> /module/action
                $action = $segments[1];
            } else {
                // Cuma /module -> hormati ?action= kalau ada (mis. form filter
                // Laporan yang mengirim action lewat query string), default index.
                $action = $_GET['action'] ?? 'index';
            }

            if (isset($segments[2]) && ctype_digit($segments[2]) && !isset($_GET['id'])) {
                $_GET['id'] = $segments[2];
            }

            return [$module, $action];
        }

        return [$_GET['module'] ?? 'dashboard', $_GET['action'] ?? 'index'];
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo '404 - Halaman tidak ditemukan';
    }
}
