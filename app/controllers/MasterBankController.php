<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/MasterBank.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Master Bank (Revisi Kas/Bank) -- Master Data > Master Bank. Sumber dropdown
 * Bank (Loan/HR) untuk modul Bank. Pola identik CashCategoryController.
 */
class MasterBankController extends Controller
{
    private MasterBank $model;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('master_bank', 'view');
        $this->model = new MasterBank();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'jenis'   => $_GET['jenis'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'bank_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->model->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $rows = $this->model->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'master_bank', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('master_bank/list', [
            'pageTitle'  => 'Master Bank',
            'rows'       => $rows,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
            'jenisLabels' => $this->model->jenisLabels,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('master_bank', 'create');
        $this->view('master_bank/form', [
            'pageTitle' => 'Tambah Bank',
            'mode'      => 'create',
            'row'       => null,
            'jenisLabels' => $this->model->jenisLabels,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('master_bank', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_bank', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('master_bank', 'create');
        }

        $this->model->create(array_merge($data, ['created_by' => currentUserId()]));
        $this->activityLog->log(currentUserId(), 'master_bank', 'create', "Bank '{$data['bank_name']}' ({$data['jenis']}) dibuat");
        setFlash('success', 'Bank berhasil ditambahkan.');
        $this->redirect('master_bank', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('master_bank', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row) {
            setFlash('error', 'Bank tidak ditemukan.');
            $this->redirect('master_bank', 'index');
        }
        $this->view('master_bank/form', [
            'pageTitle' => 'Edit Bank',
            'mode'      => 'edit',
            'row'       => $row,
            'jenisLabels' => $this->model->jenisLabels,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('master_bank', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_bank', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            setFlash('error', 'Bank tidak ditemukan.');
            $this->redirect('master_bank', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validate($data, $id);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('master_bank', 'edit', ['id' => $id]);
        }

        $this->model->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'master_bank', 'update', "Bank '{$data['bank_name']}' diperbarui");
        setFlash('success', 'Bank berhasil diperbarui.');
        $this->redirect('master_bank', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('master_bank', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_bank', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);
        if ($row) {
            $this->model->deleteById($id);
            $this->activityLog->log(currentUserId(), 'master_bank', 'delete', "Bank '{$row['bank_name']}' dihapus");
            setFlash('success', 'Bank berhasil dihapus.');
        } else {
            setFlash('error', 'Bank tidak ditemukan.');
        }
        $this->redirect('master_bank', 'index');
    }

    private function collectInput(): array
    {
        $jenis = $_POST['jenis'] ?? '';
        return [
            'bank_code'  => trim($_POST['bank_code'] ?? ''),
            'bank_name'  => trim($_POST['bank_name'] ?? ''),
            'jenis'      => in_array($jenis, ['loan', 'hr'], true) ? $jenis : '',
            'is_active'  => !empty($_POST['is_active']) ? 1 : 0,
            'keterangan' => trim($_POST['keterangan'] ?? '') ?: null,
        ];
    }

    private function validate(array $d, ?int $excludeId = null): array
    {
        $errors = [];
        if ($d['bank_code'] === '') {
            $errors[] = 'Kode Bank wajib diisi.';
        } elseif ($this->model->kodeExists($d['bank_code'], $excludeId)) {
            $errors[] = 'Kode Bank sudah dipakai.';
        }
        if ($d['bank_name'] === '') {
            $errors[] = 'Nama Bank wajib diisi.';
        }
        if ($d['jenis'] === '') {
            $errors[] = 'Jenis wajib dipilih (Loan / HR).';
        }
        return $errors;
    }
}
