<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/MasterRekening.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Master Rekening (Revisi Kas/Bank) -- Master Data > Master Rekening. Pola
 * identik CashCategoryController. Dipakai sebagai dropdown opsional pada
 * transaksi Kas (rekening_id) -- BEDA dari Master Bank (modul Bank/Accounting).
 */
class MasterRekeningController extends Controller
{
    private MasterRekening $model;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('master_rekening', 'view');
        $this->model = new MasterRekening();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $filters = ['keyword' => trim($_GET['keyword'] ?? '')];
        $sort = $_GET['sort'] ?? 'nama_rekening';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->model->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $rows = $this->model->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'master_rekening', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('master_rekening/list', [
            'pageTitle'  => 'Master Rekening',
            'rows'       => $rows,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('master_rekening', 'create');
        $this->view('master_rekening/form', [
            'pageTitle' => 'Tambah Rekening',
            'mode'      => 'create',
            'row'       => null,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('master_rekening', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_rekening', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('master_rekening', 'create');
        }

        $this->model->create(array_merge($data, ['created_by' => currentUserId()]));
        $this->activityLog->log(currentUserId(), 'master_rekening', 'create', "Rekening '{$data['nama_rekening']}' dibuat");
        setFlash('success', 'Rekening berhasil ditambahkan.');
        $this->redirect('master_rekening', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('master_rekening', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row) {
            setFlash('error', 'Rekening tidak ditemukan.');
            $this->redirect('master_rekening', 'index');
        }
        $this->view('master_rekening/form', [
            'pageTitle' => 'Edit Rekening',
            'mode'      => 'edit',
            'row'       => $row,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('master_rekening', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_rekening', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            setFlash('error', 'Rekening tidak ditemukan.');
            $this->redirect('master_rekening', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validate($data, $id);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('master_rekening', 'edit', ['id' => $id]);
        }

        $this->model->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'master_rekening', 'update', "Rekening '{$data['nama_rekening']}' diperbarui");
        setFlash('success', 'Rekening berhasil diperbarui.');
        $this->redirect('master_rekening', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('master_rekening', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_rekening', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);
        if ($row) {
            $this->model->deleteById($id);
            $this->activityLog->log(currentUserId(), 'master_rekening', 'delete', "Rekening '{$row['nama_rekening']}' dihapus");
            setFlash('success', 'Rekening berhasil dihapus.');
        } else {
            setFlash('error', 'Rekening tidak ditemukan.');
        }
        $this->redirect('master_rekening', 'index');
    }

    /**
     * AJAX quick-add -- dipanggil dari modal quick-add di form Transaksi Bank
     * (Kas > Bank), supaya Rekening baru bisa dibuat tanpa keluar dari alur
     * tambah transaksi. kode_rekening diketik manual (tidak ikut sistem
     * prefix CodeConfig, sama seperti Master Bank).
     */
    public function quickStore()
    {
        Middleware::requirePermission('master_rekening', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $data = $this->collectInput();
        // Modal quick-add tidak punya checkbox "Aktif" -- selalu aktif supaya
        // langsung bisa dipakai di transaksi yang sedang diisi.
        $data['is_active'] = 1;
        $errors = $this->validate($data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $id = $this->model->create(array_merge($data, ['created_by' => currentUserId()]));
        $this->activityLog->log(currentUserId(), 'master_rekening', 'quick_add', "Rekening '{$data['nama_rekening']}' ditambahkan cepat dari form lain");

        $this->json(['id' => $id, 'label' => $data['nama_rekening']]);
    }

    private function collectInput(): array
    {
        return [
            'kode_rekening' => trim($_POST['kode_rekening'] ?? ''),
            'nama_rekening' => trim($_POST['nama_rekening'] ?? ''),
            'jenis'         => trim($_POST['jenis'] ?? '') ?: null,
            'pic_name'      => trim($_POST['pic_name'] ?? '') ?: null,
            'is_active'     => !empty($_POST['is_active']) ? 1 : 0,
            'keterangan'    => trim($_POST['keterangan'] ?? '') ?: null,
        ];
    }

    private function validate(array $d, ?int $excludeId = null): array
    {
        $errors = [];
        if ($d['kode_rekening'] === '') {
            $errors[] = 'Kode Rekening wajib diisi.';
        } elseif ($this->model->kodeExists($d['kode_rekening'], $excludeId)) {
            $errors[] = 'Kode Rekening sudah dipakai.';
        }
        if ($d['nama_rekening'] === '') {
            $errors[] = 'Nama Rekening wajib diisi.';
        }
        return $errors;
    }
}
