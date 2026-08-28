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
        'cash_category'      => 'CashCategoryController',
        'user_pic'           => 'UserPicController',
    ];

    public function dispatch(): void
    {
        $module = $_GET['module'] ?? 'dashboard';
        $action = $_GET['action'] ?? 'index';

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

    private function notFound(): void
    {
        http_response_code(404);
        echo '404 - Halaman tidak ditemukan';
    }
}
