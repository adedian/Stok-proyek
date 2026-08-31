<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Supplier.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/CodeConfig.php';

class SupplierController extends Controller
{
    private Supplier $supplierModel;
    private ActivityLog $activityLog;
    private CodeConfig $codeConfig;

    public function __construct()
    {
        // quickStore() dicek permission-nya sendiri (lebih longgar, lihat method-nya) --
        // jangan diblokir lebih dulu di sini oleh gate 'view' yang Super Admin-only.
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('supplier', 'view');
        }

        $this->supplierModel = new Supplier();
        $this->activityLog   = new ActivityLog();
        $this->codeConfig    = new CodeConfig();
    }

    public function index()
    {
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status'  => $_GET['status'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'supplier_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->supplierModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $suppliers = $this->supplierModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'supplier',
            'sort'   => $sort,
            'dir'    => $dir,
        ])));

        $this->view('supplier/list', [
            'pageTitle'  => 'Supplier',
            'suppliers'  => $suppliers,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    public function exportCsv()
    {
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status'  => $_GET['status'] ?? '',
        ];
        $rows = $this->supplierModel->listPaginated($filters, 'supplier_name', 'asc', 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="supplier_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Kode', 'Nama Supplier', 'PIC', 'Telepon', 'Email', 'NPWP', 'Alamat', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['supplier_code'], $r['supplier_name'], $r['contact_person'],
                $r['phone'], $r['email'], $r['npwp'], $r['address'], $r['status'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function create()
    {
        Middleware::requirePermission('supplier', 'create');

        $this->view('supplier/form', [
            'pageTitle'      => 'Tambah Supplier',
            'mode'           => 'create',
            'supplier'       => null,
            'codeConfig'     => $this->codeConfig->getConfig('supplier'),
            'codePrefixes'   => $this->codeConfig->configsForEntity('supplier'),
            'codeMasterCode' => $this->codeConfig->masterCodeForEntity('supplier'),
            'codeEntityType' => 'supplier',
            'codeEntityLabel' => 'Supplier',
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('supplier', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('supplier', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('supplier', 'create');
        }

        $codePrefix = trim($_POST['code_prefix'] ?? '');
        $supplierCode = $this->codeConfig->nextCode('supplier', $codePrefix !== '' ? $codePrefix : null);
        if ($supplierCode === null) {
            setFlash('error', 'Prefix kode Supplier belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Supplier.');
            $this->redirect('supplier', 'create');
        }

        $this->supplierModel->create(array_merge($data, [
            'supplier_code' => $supplierCode,
            'status'        => 'active',
            'created_by'    => currentUserId(),
        ]));

        $this->activityLog->log(currentUserId(), 'supplier', 'create', "Supplier '{$data['supplier_name']}' dibuat");
        setFlash('success', 'Supplier berhasil ditambahkan.');
        $this->redirect('supplier', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('supplier', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $supplier = $this->supplierModel->find($id);

        if (!$supplier) {
            setFlash('error', 'Supplier tidak ditemukan.');
            $this->redirect('supplier', 'index');
        }

        $this->view('supplier/form', [
            'pageTitle' => 'Edit Supplier',
            'mode'      => 'edit',
            'supplier'  => $supplier,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('supplier', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('supplier', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->supplierModel->find($id);

        if (!$existing) {
            setFlash('error', 'Supplier tidak ditemukan.');
            $this->redirect('supplier', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('supplier', 'edit', ['id' => $id]);
        }

        $postedStatus = $_POST['status'] ?? '';
        $data['status'] = in_array($postedStatus, ['active', 'inactive'], true) ? $postedStatus : $existing['status'];

        $this->supplierModel->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'supplier', 'update', "Supplier '{$data['supplier_name']}' diperbarui");
        setFlash('success', 'Supplier berhasil diperbarui.');
        $this->redirect('supplier', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('supplier', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('supplier', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $supplier = $this->supplierModel->find($id);

        if ($supplier) {
            $this->supplierModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'supplier', 'delete', "Supplier '{$supplier['supplier_name']}' dihapus");
            setFlash('success', 'Supplier berhasil dihapus.');
        } else {
            setFlash('error', 'Supplier tidak ditemukan.');
        }

        $this->redirect('supplier', 'index');
    }

    /**
     * AJAX quick-add dipanggil dari form transaksi (mis. Purchase Order).
     * Role yang boleh akses lebih longgar dari manajemen master data penuh --
     * lihat 'supplier'.'quick_add' di config/permissions.php.
     */
    public function quickStore()
    {
        Middleware::requirePermission('supplier', 'quick_add');

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
        $supplierCode = $this->codeConfig->nextCode('supplier', $codePrefix !== '' ? $codePrefix : null);
        if ($supplierCode === null) {
            $this->json(['errors' => ['Prefix kode Supplier belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Supplier.']], 422);
        }

        $id = $this->supplierModel->create(array_merge($data, [
            'supplier_code' => $supplierCode,
            'status'        => 'active',
            'created_by'    => currentUserId(),
        ]));

        $this->activityLog->log(
            currentUserId(),
            'supplier',
            'quick_add',
            "Supplier '{$data['supplier_name']}' ditambahkan cepat dari form lain"
        );

        $this->json(['id' => $id, 'label' => $data['supplier_name']]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        return [
            'supplier_name'  => trim($_POST['supplier_name'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'npwp'           => trim($_POST['npwp'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
        ];
    }

    private function validateInput(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['supplier_name'] === '') {
            $errors[] = 'Nama supplier wajib diisi.';
        } elseif ($this->supplierModel->nameExists($data['supplier_name'], $excludeId)) {
            $errors[] = "Data dengan nama '{$data['supplier_name']}' sudah tersedia.";
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }

        return $errors;
    }
}
