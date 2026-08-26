<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Supplier.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/Warehouse.php';

class MasterDataController extends Controller
{
    public function __construct()
    {
        Middleware::requirePermission('master_data', 'view');
    }

    public function index()
    {
        $supplierModel  = new Supplier();
        $projectModel   = new Project();
        $itemModel      = new Item();
        $warehouseModel = new Warehouse();

        $stats = [
            'total_supplier'  => $supplierModel->countFiltered([]),
            'total_project'   => $projectModel->countFiltered([]),
            'total_item'      => $itemModel->countFiltered([]),
            'total_warehouse' => $warehouseModel->countFiltered([]),
            'below_min_stock' => $itemModel->belowMinStockCount(),
        ];

        $this->view('master_data/index', [
            'pageTitle' => 'Master Data',
            'stats'     => $stats,
        ]);
    }
}
