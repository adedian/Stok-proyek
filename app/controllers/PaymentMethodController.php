<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PaymentMethod.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class PaymentMethodController extends Controller
{
    private PaymentMethod $methodModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        // quickStore() dicek permission-nya sendiri (lebih longgar, lihat method-nya) --
        // jangan diblokir lebih dulu di sini oleh gate 'view' yang Super Admin-only.
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('payment_method', 'view');
        }

        $this->methodModel = new PaymentMethod();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $filters = ['keyword' => trim($_GET['keyword'] ?? '')];
        $sort = $_GET['sort'] ?? 'method_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->methodModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $methods = $this->methodModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'payment_method', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('payment_method/list', [
            'pageTitle'  => 'Metode Pembayaran',
            'methods'    => $methods,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('payment_method', 'create');
        $this->view('payment_method/form', [
            'pageTitle' => 'Tambah Metode Pembayaran',
            'mode'      => 'create',
            'method'    => null,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('payment_method', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment_method', 'create');
        }
        verifyCsrf();

        $name = trim($_POST['method_name'] ?? '');
        $errors = $this->validate($name);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('payment_method', 'create');
        }

        $this->methodModel->create(['method_name' => $name, 'created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'payment_method', 'create', "Metode pembayaran '{$name}' dibuat");
        setFlash('success', 'Metode pembayaran berhasil ditambahkan.');
        $this->redirect('payment_method', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('payment_method', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $method = $this->methodModel->find($id);

        if (!$method) {
            setFlash('error', 'Metode pembayaran tidak ditemukan.');
            $this->redirect('payment_method', 'index');
        }

        $this->view('payment_method/form', [
            'pageTitle' => 'Edit Metode Pembayaran',
            'mode'      => 'edit',
            'method'    => $method,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('payment_method', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment_method', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->methodModel->find($id);
        if (!$existing) {
            setFlash('error', 'Metode pembayaran tidak ditemukan.');
            $this->redirect('payment_method', 'index');
        }

        $name = trim($_POST['method_name'] ?? '');
        $errors = $this->validate($name, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('payment_method', 'edit', ['id' => $id]);
        }

        $this->methodModel->updateById($id, ['method_name' => $name]);
        $this->activityLog->log(currentUserId(), 'payment_method', 'update', "Metode pembayaran '{$name}' diperbarui");
        setFlash('success', 'Metode pembayaran berhasil diperbarui.');
        $this->redirect('payment_method', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('payment_method', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('payment_method', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $method = $this->methodModel->find($id);

        if ($method) {
            $this->methodModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'payment_method', 'delete', "Metode pembayaran '{$method['method_name']}' dihapus");
            setFlash('success', 'Metode pembayaran berhasil dihapus.');
        } else {
            setFlash('error', 'Metode pembayaran tidak ditemukan.');
        }

        $this->redirect('payment_method', 'index');
    }

    /**
     * AJAX quick-add -- dipanggil dari modal quick-add di form Pembayaran, supaya
     * metode baru bisa dibuat tanpa keluar dari alur tambah pembayaran.
     */
    public function quickStore()
    {
        Middleware::requirePermission('payment_method', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $name = trim($_POST['method_name'] ?? '');
        $errors = $this->validate($name);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $id = $this->methodModel->create(['method_name' => $name, 'created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'payment_method', 'quick_add', "Metode pembayaran '{$name}' ditambahkan cepat dari form lain");

        $this->json(['id' => $id, 'label' => $name]);
    }

    private function validate(string $name, ?int $excludeId = null): array
    {
        $errors = [];
        if ($name === '') {
            $errors[] = 'Nama metode pembayaran wajib diisi.';
        } elseif ($this->methodModel->nameExists($name, $excludeId)) {
            $errors[] = 'Nama metode pembayaran sudah ada.';
        }
        return $errors;
    }
}
