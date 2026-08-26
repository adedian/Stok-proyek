<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/CodeConfig.php';

class ProjectController extends Controller
{
    private Project $projectModel;
    private ActivityLog $activityLog;
    private CodeConfig $codeConfig;

    public function __construct()
    {
        // quickStore() dicek permission-nya sendiri (lebih longgar, lihat method-nya) --
        // jangan diblokir lebih dulu di sini oleh gate 'view' yang Super Admin-only.
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('project', 'view');
        }

        $this->projectModel = new Project();
        $this->activityLog  = new ActivityLog();
        $this->codeConfig   = new CodeConfig();
    }

    public function index()
    {
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status'  => $_GET['status'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'project_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->projectModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $projects = $this->projectModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'project',
            'sort'   => $sort,
            'dir'    => $dir,
        ])));

        $this->view('project/list', [
            'pageTitle'  => 'Project',
            'projects'   => $projects,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
            'statusLabels'     => $this->projectModel->statusLabels,
            'statusBadgeClass' => $this->projectModel->statusBadgeClass,
        ]);
    }

    public function exportCsv()
    {
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status'  => $_GET['status'] ?? '',
        ];
        $rows = $this->projectModel->listPaginated($filters, 'project_name', 'asc', 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="project_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Kode', 'Nama Project', 'Lokasi', 'Nama', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['project_code'], $r['project_name'], $r['location'],
                $r['pic_name'], $this->projectModel->statusLabels[$r['status']] ?? $r['status'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function create()
    {
        Middleware::requirePermission('project', 'create');

        $this->view('project/form', [
            'pageTitle' => 'Tambah Project',
            'mode'      => 'create',
            'project'   => null,
            'codeConfig' => $this->codeConfig->getConfig('project'),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('project', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('project', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('project', 'create');
        }

        $projectCode = $this->codeConfig->nextCode('project');
        if ($projectCode === null) {
            setFlash('error', 'Prefix kode Project belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Project.');
            $this->redirect('project', 'create');
        }

        $this->projectModel->create(array_merge($data, [
            'project_code' => $projectCode,
            'created_by'   => currentUserId(),
        ]));
        $this->activityLog->log(currentUserId(), 'project', 'create', "Project '{$data['project_name']}' dibuat");
        setFlash('success', 'Project berhasil ditambahkan.');
        $this->redirect('project', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('project', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $project = $this->projectModel->findWithRelations($id);

        if (!$project) {
            setFlash('error', 'Project tidak ditemukan.');
            $this->redirect('project', 'index');
        }

        $this->view('project/form', [
            'pageTitle' => 'Edit Project',
            'mode'      => 'edit',
            'project'   => $project,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('project', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('project', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->projectModel->find($id);

        if (!$existing) {
            setFlash('error', 'Project tidak ditemukan.');
            $this->redirect('project', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('project', 'edit', ['id' => $id]);
        }

        $this->projectModel->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'project', 'update', "Project '{$data['project_name']}' diperbarui");
        setFlash('success', 'Project berhasil diperbarui.');
        $this->redirect('project', 'index');
    }

    /**
     * Toggle status planning/ongoing <-> closed dengan cepat dari list (tombol aktif/nonaktifkan)
     */
    public function toggleStatus()
    {
        Middleware::requirePermission('project', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('project', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $project = $this->projectModel->find($id);

        if (!$project) {
            setFlash('error', 'Project tidak ditemukan.');
            $this->redirect('project', 'index');
        }

        $newStatus = $project['status'] === 'closed' ? 'ongoing' : 'closed';
        $this->projectModel->updateById($id, ['status' => $newStatus]);
        $this->activityLog->log(
            currentUserId(),
            'project',
            'toggle_status',
            "Project '{$project['project_name']}' diubah statusnya menjadi {$newStatus}"
        );

        setFlash('success', 'Status project berhasil diubah.');
        $this->redirect('project', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('project', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('project', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $project = $this->projectModel->find($id);

        if ($project) {
            $this->projectModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'project', 'delete', "Project '{$project['project_name']}' dihapus");
            setFlash('success', 'Project berhasil dihapus.');
        } else {
            setFlash('error', 'Project tidak ditemukan.');
        }

        $this->redirect('project', 'index');
    }

    /**
     * AJAX quick-add dipanggil dari form transaksi (PO, Pembelian Offline,
     * Pengeluaran Barang, Stok Opname). Lihat 'project'.'quick_add' di config/permissions.php.
     */
    public function quickStore()
    {
        Middleware::requirePermission('project', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $projectCode = $this->codeConfig->nextCode('project');
        if ($projectCode === null) {
            $this->json(['errors' => ['Prefix kode Project belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Project.']], 422);
        }

        $id = $this->projectModel->create(array_merge($data, [
            'project_code' => $projectCode,
            'created_by'   => currentUserId(),
        ]));

        $this->activityLog->log(
            currentUserId(),
            'project',
            'quick_add',
            "Project '{$data['project_name']}' ditambahkan cepat dari form lain"
        );

        $this->json(['id' => $id, 'label' => $data['project_name']]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        $status = $_POST['status'] ?? 'planning';
        return [
            'project_name' => trim($_POST['project_name'] ?? ''),
            'location'     => trim($_POST['location'] ?? ''),
            'pic_name'     => trim($_POST['pic_name'] ?? '') ?: null,
            'status'       => in_array($status, ['planning', 'ongoing', 'closed'], true) ? $status : 'planning',
        ];
    }

    private function validateInput(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['project_name'] === '') {
            $errors[] = 'Nama project wajib diisi.';
        } elseif ($this->projectModel->nameExists($data['project_name'], $excludeId)) {
            $errors[] = "Data dengan nama '{$data['project_name']}' sudah tersedia.";
        }

        return $errors;
    }
}
