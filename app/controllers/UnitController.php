<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class UnitController extends Controller
{
    private Unit $unitModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        // quickStore() dicek permission-nya sendiri (lebih longgar, lihat method-nya) --
        // jangan diblokir lebih dulu di sini oleh gate 'view' yang Super Admin-only.
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('unit', 'view');
        }

        $this->unitModel   = new Unit();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $filters = ['keyword' => trim($_GET['keyword'] ?? '')];
        $sort = $_GET['sort'] ?? 'unit_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->unitModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $units = $this->unitModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'unit', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('unit/list', [
            'pageTitle'  => 'Satuan',
            'units'      => $units,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('unit', 'create');
        $this->view('unit/form', [
            'pageTitle' => 'Tambah Satuan',
            'mode'      => 'create',
            'unit'      => null,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('unit', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('unit', 'create');
        }
        verifyCsrf();

        $name = trim($_POST['unit_name'] ?? '');
        $errors = $this->validate($name);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('unit', 'create');
        }

        $this->unitModel->create(['unit_name' => $name, 'created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'unit', 'create', "Satuan '{$name}' dibuat");
        setFlash('success', 'Satuan berhasil ditambahkan.');
        $this->redirect('unit', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('unit', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $unit = $this->unitModel->find($id);

        if (!$unit) {
            setFlash('error', 'Satuan tidak ditemukan.');
            $this->redirect('unit', 'index');
        }

        $this->view('unit/form', [
            'pageTitle' => 'Edit Satuan',
            'mode'      => 'edit',
            'unit'      => $unit,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('unit', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('unit', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->unitModel->find($id);
        if (!$existing) {
            setFlash('error', 'Satuan tidak ditemukan.');
            $this->redirect('unit', 'index');
        }

        $name = trim($_POST['unit_name'] ?? '');
        $errors = $this->validate($name, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('unit', 'edit', ['id' => $id]);
        }

        $this->unitModel->updateById($id, ['unit_name' => $name]);
        $this->activityLog->log(currentUserId(), 'unit', 'update', "Satuan '{$name}' diperbarui");
        setFlash('success', 'Satuan berhasil diperbarui.');
        $this->redirect('unit', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('unit', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('unit', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $unit = $this->unitModel->find($id);

        if ($unit) {
            $this->unitModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'unit', 'delete', "Satuan '{$unit['unit_name']}' dihapus");
            setFlash('success', 'Satuan berhasil dihapus.');
        } else {
            setFlash('error', 'Satuan tidak ditemukan.');
        }

        $this->redirect('unit', 'index');
    }

    /**
     * AJAX quick-add -- dipanggil dari modal quick-add Barang (nested), supaya
     * satuan baru bisa dibuat tanpa keluar dari alur tambah barang.
     */
    public function quickStore()
    {
        Middleware::requirePermission('unit', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $name = trim($_POST['unit_name'] ?? '');
        $errors = $this->validate($name);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $id = $this->unitModel->create(['unit_name' => $name, 'created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'unit', 'quick_add', "Satuan '{$name}' ditambahkan cepat dari form lain");

        $this->json(['id' => $id, 'label' => $name]);
    }

    private function validate(string $name, ?int $excludeId = null): array
    {
        $errors = [];
        if ($name === '') {
            $errors[] = 'Nama satuan wajib diisi.';
        } elseif ($this->unitModel->nameExists($name, $excludeId)) {
            $errors[] = 'Nama satuan sudah ada.';
        }
        return $errors;
    }
}
