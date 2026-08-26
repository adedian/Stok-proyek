<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Signature.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class SignatureController extends Controller
{
    private Signature $signatureModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('signature', 'view');

        $this->signatureModel = new Signature();
        $this->activityLog    = new ActivityLog();
    }

    public function index()
    {
        $filters = ['keyword' => trim($_GET['keyword'] ?? '')];
        $sort = $_GET['sort'] ?? 'name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->signatureModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $signatures = $this->signatureModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'signature', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('signature/list', [
            'pageTitle'   => 'Tanda Tangan',
            'signatures'  => $signatures,
            'filters'     => $filters,
            'sort'        => $sort,
            'dir'         => $dir,
            'pagination'  => $pg,
            'baseQuery'   => $baseQuery,
            'statusLabels' => $this->signatureModel->statusLabels,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('signature', 'create');
        $this->view('signature/form', [
            'pageTitle' => 'Tambah Tanda Tangan',
            'mode'      => 'create',
            'signature' => null,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('signature', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('signature', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validate($data);

        try {
            $imagePath = handleFileUpload('signature_image', 'signatures', ['jpg', 'jpeg', 'png'], 2);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
            $imagePath = null;
        }

        if ($imagePath === null) {
            $errors[] = 'Gambar tanda tangan wajib diupload.';
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('signature', 'create');
        }

        $this->signatureModel->create([
            'name'            => $data['name'],
            'position'        => $data['position'],
            'signature_image' => $imagePath,
            'status'          => $data['status'],
            'created_by'      => currentUserId(),
        ]);
        $this->activityLog->log(currentUserId(), 'signature', 'create', "Tanda tangan '{$data['name']}' ditambahkan");
        setFlash('success', 'Tanda tangan berhasil ditambahkan.');
        $this->redirect('signature', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('signature', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $signature = $this->signatureModel->find($id);

        if (!$signature) {
            setFlash('error', 'Tanda tangan tidak ditemukan.');
            $this->redirect('signature', 'index');
        }

        $this->view('signature/form', [
            'pageTitle' => 'Edit Tanda Tangan',
            'mode'      => 'edit',
            'signature' => $signature,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('signature', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('signature', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->signatureModel->find($id);
        if (!$existing) {
            setFlash('error', 'Tanda tangan tidak ditemukan.');
            $this->redirect('signature', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validate($data);

        $imagePath = null;
        try {
            $imagePath = handleFileUpload('signature_image', 'signatures', ['jpg', 'jpeg', 'png'], 2);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('signature', 'edit', ['id' => $id]);
        }

        $updateData = [
            'name'     => $data['name'],
            'position' => $data['position'],
            'status'   => $data['status'],
        ];
        if ($imagePath !== null) {
            $updateData['signature_image'] = $imagePath;
        }

        $this->signatureModel->updateById($id, $updateData);
        $this->activityLog->log(currentUserId(), 'signature', 'update', "Tanda tangan '{$data['name']}' diperbarui");
        setFlash('success', 'Tanda tangan berhasil diperbarui.');
        $this->redirect('signature', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('signature', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('signature', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $signature = $this->signatureModel->find($id);

        if ($signature) {
            $this->signatureModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'signature', 'delete', "Tanda tangan '{$signature['name']}' dihapus");
            setFlash('success', 'Tanda tangan berhasil dihapus.');
        } else {
            setFlash('error', 'Tanda tangan tidak ditemukan.');
        }

        $this->redirect('signature', 'index');
    }

    private function collectInput(): array
    {
        return [
            'name'     => trim($_POST['name'] ?? ''),
            'position' => trim($_POST['position'] ?? ''),
            'status'   => ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
        ];
    }

    private function validate(array $data): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Nama pemilik tanda tangan wajib diisi.';
        }
        if ($data['position'] === '') {
            $errors[] = 'Jabatan wajib diisi.';
        }
        return $errors;
    }
}
