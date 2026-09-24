<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Information.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Pusat Informasi -- pengumuman/keterangan untuk seluruh pengguna aplikasi.
 * Pola CRUD identik ItemCategoryController/CashCategoryController, ditambah
 * filter kategori/status/tanggal & halaman detail (pola PurchaseOrderController::detail()).
 *
 * View (lihat) terbuka untuk semua role yang login; kelola (create/edit/delete)
 * dibatasi lewat can('information', ...) -- default Super Admin saja
 * (config/permissions.php), tapi bisa dibuka admin lewat Hak Akses.
 */
class InformationController extends Controller
{
    private Information $infoModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('information', 'view');

        $this->infoModel   = new Information();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $canManage = can('information', 'create') || can('information', 'edit') || can('information', 'delete');

        $filters = [
            'keyword'   => trim($_GET['keyword'] ?? ''),
            'category'  => trim($_GET['category'] ?? ''),
            'status'    => trim($_GET['status'] ?? ''),
            'date_from' => trim($_GET['date_from'] ?? ''),
            'date_to'   => trim($_GET['date_to'] ?? ''),
        ];

        // User yang tidak berhak kelola HANYA boleh melihat informasi berstatus
        // Aktif -- filter status dari URL diabaikan supaya tidak bisa dipakai
        // untuk mengintip data Tidak Aktif.
        if (!$canManage) {
            $filters['status'] = 'aktif';
        }

        $sort = $_GET['sort'] ?? 'publish_date';
        $dir  = $_GET['dir'] ?? 'desc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->infoModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $rows = $this->infoModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'information', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('information/list', [
            'pageTitle'  => 'Pusat Informasi',
            'rows'       => $rows,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
            'canManage'  => $canManage,
            'categories' => Information::categoryOptions(),
            'statuses'   => Information::statusOptions(),
        ]);
    }

    public function detail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $info = $this->infoModel->find($id);

        if (!$info || (!can('information', 'create') && $info['status'] !== 'aktif')) {
            setFlash('error', 'Informasi tidak ditemukan.');
            $this->redirect('information', 'index');
        }

        $this->view('information/detail', [
            'pageTitle' => 'Detail Informasi',
            'info'      => $info,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('information', 'create');
        $this->view('information/form', [
            'pageTitle'  => 'Tambah Informasi',
            'mode'       => 'create',
            'info'       => null,
            'categories' => Information::categoryOptions(),
            'statuses'   => Information::statusOptions(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('information', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('information', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('information', 'create');
        }

        $data['created_by'] = currentUserId();
        $id = $this->infoModel->create($data);

        $this->activityLog->log(currentUserId(), 'information', 'create', "Informasi '{$data['title']}' dibuat");
        setFlash('success', 'Informasi berhasil ditambahkan.');
        $this->redirect('information', 'detail', ['id' => $id]);
    }

    public function edit()
    {
        Middleware::requirePermission('information', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $info = $this->infoModel->find($id);

        if (!$info) {
            setFlash('error', 'Informasi tidak ditemukan.');
            $this->redirect('information', 'index');
        }

        $this->view('information/form', [
            'pageTitle'  => 'Edit Informasi',
            'mode'       => 'edit',
            'info'       => $info,
            'categories' => Information::categoryOptions(),
            'statuses'   => Information::statusOptions(),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('information', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('information', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->infoModel->find($id);
        if (!$existing) {
            setFlash('error', 'Informasi tidak ditemukan.');
            $this->redirect('information', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('information', 'edit', ['id' => $id]);
        }

        $this->infoModel->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'information', 'update', "Informasi '{$data['title']}' diperbarui");
        setFlash('success', 'Informasi berhasil diperbarui.');
        $this->redirect('information', 'detail', ['id' => $id]);
    }

    public function delete()
    {
        Middleware::requirePermission('information', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('information', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $info = $this->infoModel->find($id);

        if ($info) {
            $this->infoModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'information', 'delete', "Informasi '{$info['title']}' dihapus");
            setFlash('success', 'Informasi berhasil dihapus.');
        } else {
            setFlash('error', 'Informasi tidak ditemukan.');
        }

        $this->redirect('information', 'index');
    }

    private function collectInput(): array
    {
        $categories = array_keys(Information::categoryOptions());
        $statuses = array_keys(Information::statusOptions());

        $category = $_POST['category'] ?? '';
        $status = $_POST['status'] ?? '';
        $publishDate = trim($_POST['publish_date'] ?? '');

        return [
            'title'        => trim($_POST['title'] ?? ''),
            'category'     => in_array($category, $categories, true) ? $category : 'umum',
            'content'      => trim($_POST['content'] ?? ''),
            'status'       => in_array($status, $statuses, true) ? $status : 'aktif',
            'publish_date' => $publishDate !== '' ? $publishDate : date('Y-m-d'),
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];

        if ($data['title'] === '') {
            $errors[] = 'Judul informasi wajib diisi.';
        } elseif (mb_strlen($data['title']) > 200) {
            $errors[] = 'Judul informasi maksimal 200 karakter.';
        }

        if ($data['content'] === '') {
            $errors[] = 'Isi informasi wajib diisi.';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['publish_date']) || !strtotime($data['publish_date'])) {
            $errors[] = 'Tanggal publikasi tidak valid.';
        }

        return $errors;
    }
}
