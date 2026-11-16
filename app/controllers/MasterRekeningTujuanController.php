<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/MasterRekeningTujuan.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Master Rekening Tujuan -- Master Data > Master Rekening Tujuan. Daftar penerima pembayaran
 * (nama, bank, no. rekening) yang dipilih di kolom "Rekening Tujuan" Pengajuan Budget.
 * Akses: izin modul 'master_rekening_tujuan' (Purwati & Nissa lewat izin per akun + Super Admin).
 */
class MasterRekeningTujuanController extends Controller
{
    private MasterRekeningTujuan $model;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('master_rekening_tujuan', 'view');
        $this->model = new MasterRekeningTujuan();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $filters = ['keyword' => is_string($_GET['keyword'] ?? null) ? trim($_GET['keyword']) : ''];
        $sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'nama_penerima';
        $dir = is_string($_GET['dir'] ?? null) ? $_GET['dir'] : 'asc';
        $totalRows = $this->model->countFiltered($filters);
        $pg = paginationInfo($totalRows, (int) ($_GET['page'] ?? 1));
        $rows = $this->model->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $this->view('master_rekening_tujuan/list', [
            'pageTitle'  => 'Master Rekening Tujuan',
            'rows'       => $rows,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter(array_merge($filters, ['module' => 'master_rekening_tujuan', 'sort' => $sort, 'dir' => $dir]))),
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('master_rekening_tujuan', 'create');
        $this->view('master_rekening_tujuan/form', ['pageTitle' => 'Tambah Rekening Tujuan', 'mode' => 'create', 'row' => null]);
    }

    public function store()
    {
        Middleware::requirePermission('master_rekening_tujuan', 'create');
        $this->requirePost('create');

        $data = $this->collectInput();
        $errors = $this->validate($data);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('master_rekening_tujuan', 'create');
        }
        $this->model->create($data + ['created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'master_rekening_tujuan', 'create', "Rekening Tujuan '" . MasterRekeningTujuan::label($data) . "' dibuat");
        setFlash('success', 'Rekening Tujuan berhasil ditambahkan.');
        $this->redirect('master_rekening_tujuan', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('master_rekening_tujuan', 'edit');
        $row = $this->model->find((int) ($_GET['id'] ?? 0));
        if (!$row) {
            setFlash('error', 'Rekening Tujuan tidak ditemukan.');
            $this->redirect('master_rekening_tujuan', 'index');
        }
        $this->view('master_rekening_tujuan/form', ['pageTitle' => 'Edit Rekening Tujuan', 'mode' => 'edit', 'row' => $row]);
    }

    public function update()
    {
        Middleware::requirePermission('master_rekening_tujuan', 'edit');
        $this->requirePost('index');

        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->model->find($id)) {
            setFlash('error', 'Rekening Tujuan tidak ditemukan.');
            $this->redirect('master_rekening_tujuan', 'index');
        }
        $data = $this->collectInput();
        $errors = $this->validate($data, $id);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('master_rekening_tujuan', 'edit', ['id' => $id]);
        }
        $this->model->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'master_rekening_tujuan', 'update', "Rekening Tujuan '" . MasterRekeningTujuan::label($data) . "' diperbarui");
        setFlash('success', 'Rekening Tujuan berhasil diperbarui. (Pengajuan lama tetap memakai teks yang tersimpan saat itu.)');
        $this->redirect('master_rekening_tujuan', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('master_rekening_tujuan', 'delete');
        $this->requirePost('index');
        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);
        if ($row) {
            $this->model->deleteById($id);
            $this->activityLog->log(currentUserId(), 'master_rekening_tujuan', 'delete', "Rekening Tujuan '" . MasterRekeningTujuan::label($row) . "' dihapus");
            setFlash('success', 'Rekening Tujuan berhasil dihapus.');
        } else {
            setFlash('error', 'Rekening Tujuan tidak ditemukan.');
        }
        $this->redirect('master_rekening_tujuan', 'index');
    }

    /** AJAX quick-add dari form Pengajuan Budget (tanpa keluar dari form). */
    public function quickStore()
    {
        Middleware::requirePermission('master_rekening_tujuan', 'quick_add');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();
        $data = $this->collectInput();
        $data['is_active'] = 1;
        $errors = $this->validate($data);
        if ($errors) {
            $this->json(['errors' => $errors], 422);
        }
        $id = $this->model->create($data + ['created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'master_rekening_tujuan', 'quick_add', "Rekening Tujuan '" . MasterRekeningTujuan::label($data) . "' ditambahkan cepat dari form Pengajuan Budget");
        $this->json(['id' => $id, 'label' => MasterRekeningTujuan::label($data)]);
    }

    private function requirePost(string $fallbackAction): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_rekening_tujuan', $fallbackAction);
        }
        verifyCsrf();
    }

    private function collectInput(): array
    {
        $t = fn($k, $max) => mb_substr(is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '', 0, $max);
        return [
            'nama_penerima' => $t('nama_penerima', 150),
            'bank_tujuan'   => $t('bank_tujuan', 100) ?: null,
            'no_rekening'   => $t('no_rekening', 60) ?: null,
            'keterangan'    => $t('keterangan', 255) ?: null,
            'is_active'     => !empty($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function validate(array $d, ?int $excludeId = null): array
    {
        $errors = [];
        if ($d['nama_penerima'] === '') {
            $errors[] = 'Nama Penerima wajib diisi.';
        }
        if ($this->model->duplicateExists((string) ($d['bank_tujuan'] ?? ''), (string) ($d['no_rekening'] ?? ''), $excludeId)) {
            $errors[] = 'Rekening dengan bank dan nomor yang sama sudah ada.';
        }
        return $errors;
    }
}
