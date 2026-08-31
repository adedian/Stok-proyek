<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Warehouse.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/CodeConfig.php';

class WarehouseController extends Controller
{
    private Warehouse $warehouseModel;
    private ActivityLog $activityLog;
    private CodeConfig $codeConfig;

    public function __construct()
    {
        // quickStore() dicek permission-nya sendiri (lebih longgar, lihat method-nya) --
        // jangan diblokir lebih dulu di sini oleh gate 'view' yang Super Admin-only.
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('warehouse', 'view');
        }

        $this->warehouseModel = new Warehouse();
        $this->activityLog    = new ActivityLog();
        $this->codeConfig     = new CodeConfig();
    }

    public function index()
    {
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status'  => $_GET['status'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'warehouse_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->warehouseModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $warehouses = $this->warehouseModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'warehouse', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('warehouse/list', [
            'pageTitle'  => 'Gudang',
            'warehouses' => $warehouses,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('warehouse', 'create');
        $this->view('warehouse/form', [
            'pageTitle' => 'Tambah Gudang',
            'mode'      => 'create',
            'warehouse' => null,
            'codeConfig' => $this->codeConfig->getConfig('warehouse'),
            'codePrefixes'   => $this->codeConfig->configsForEntity('warehouse'),
            'codeMasterCode' => $this->codeConfig->masterCodeForEntity('warehouse'),
            'codeEntityType' => 'warehouse',
            'codeEntityLabel' => 'Gudang',
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('warehouse', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('warehouse', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('warehouse', 'create');
        }

        $codePrefix = trim($_POST['code_prefix'] ?? '');
        $warehouseCode = $this->codeConfig->nextCode('warehouse', $codePrefix !== '' ? $codePrefix : null);
        if ($warehouseCode === null) {
            setFlash('error', 'Prefix kode Gudang belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Gudang.');
            $this->redirect('warehouse', 'create');
        }

        $this->warehouseModel->create(array_merge($data, [
            'warehouse_code' => $warehouseCode,
            'status'         => 'active',
            'created_by'     => currentUserId(),
        ]));

        $this->activityLog->log(currentUserId(), 'warehouse', 'create', "Gudang '{$data['warehouse_name']}' dibuat");
        setFlash('success', 'Gudang berhasil ditambahkan.');
        $this->redirect('warehouse', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('warehouse', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $warehouse = $this->warehouseModel->find($id);

        if (!$warehouse) {
            setFlash('error', 'Gudang tidak ditemukan.');
            $this->redirect('warehouse', 'index');
        }

        $this->view('warehouse/form', [
            'pageTitle' => 'Edit Gudang',
            'mode'      => 'edit',
            'warehouse' => $warehouse,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('warehouse', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('warehouse', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->warehouseModel->find($id);

        if (!$existing) {
            setFlash('error', 'Gudang tidak ditemukan.');
            $this->redirect('warehouse', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('warehouse', 'edit', ['id' => $id]);
        }

        $postedStatus = $_POST['status'] ?? '';
        $data['status'] = in_array($postedStatus, ['active', 'inactive'], true) ? $postedStatus : $existing['status'];

        $this->warehouseModel->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'warehouse', 'update', "Gudang '{$data['warehouse_name']}' diperbarui");
        setFlash('success', 'Gudang berhasil diperbarui.');
        $this->redirect('warehouse', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('warehouse', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('warehouse', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $warehouse = $this->warehouseModel->find($id);

        if ($warehouse) {
            $this->warehouseModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'warehouse', 'delete', "Gudang '{$warehouse['warehouse_name']}' dihapus");
            setFlash('success', 'Gudang berhasil dihapus.');
        } else {
            setFlash('error', 'Gudang tidak ditemukan.');
        }

        $this->redirect('warehouse', 'index');
    }

    /**
     * AJAX quick-add -- dipanggil dari form Purchase Order (field Lokasi Pengiriman)
     * supaya lokasi/gudang baru bisa dibuat tanpa keluar dari alur tambah PO.
     */
    public function quickStore()
    {
        Middleware::requirePermission('warehouse', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $codePrefix = trim($_POST['code_prefix'] ?? '');
        $warehouseCode = $this->codeConfig->nextCode('warehouse', $codePrefix !== '' ? $codePrefix : null);
        if ($warehouseCode === null) {
            $this->json(['errors' => ['Prefix kode Gudang belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Gudang.']], 422);
        }

        $id = $this->warehouseModel->create(array_merge($data, [
            'warehouse_code' => $warehouseCode,
            'status'         => 'active',
            'created_by'     => currentUserId(),
        ]));

        $this->activityLog->log(currentUserId(), 'warehouse', 'quick_add', "Gudang '{$data['warehouse_name']}' ditambahkan cepat dari form lain");

        $this->json(['id' => $id, 'label' => $data['warehouse_name']]);
    }

    private function collectInput(): array
    {
        return [
            'warehouse_name' => trim($_POST['warehouse_name'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
            'pic_name'       => trim($_POST['pic_name'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
        ];
    }

    private function validateInput(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        if ($data['warehouse_name'] === '') {
            $errors[] = 'Nama gudang wajib diisi.';
        } elseif ($this->warehouseModel->nameExists($data['warehouse_name'], $excludeId)) {
            $errors[] = "Data dengan nama '{$data['warehouse_name']}' sudah tersedia.";
        }
        return $errors;
    }
}
