<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Client.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/CodeConfig.php';

class ClientController extends Controller
{
    private Client $clientModel;
    private ActivityLog $activityLog;
    private CodeConfig $codeConfig;

    public function __construct()
    {
        // quickStore() dicek permission-nya sendiri (lebih longgar) -- lihat pola
        // yang sama di SupplierController, jangan diblokir dulu oleh gate 'view'.
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('client', 'view');
        }

        $this->clientModel = new Client();
        $this->activityLog = new ActivityLog();
        $this->codeConfig  = new CodeConfig();
    }

    public function index()
    {
        $filters = [
            'keyword' => trim($_GET['keyword'] ?? ''),
            'status'  => $_GET['status'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'client_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->clientModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $clients = $this->clientModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'client',
            'sort'   => $sort,
            'dir'    => $dir,
        ])));

        $this->view('client/list', [
            'pageTitle'  => 'Client',
            'clients'    => $clients,
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
        $rows = $this->clientModel->listPaginated($filters, 'client_name', 'asc', 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="client_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Kode', 'Nama Client', 'PIC', 'Telepon', 'Email', 'Alamat', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['client_code'], $r['client_name'], $r['contact_person'],
                $r['phone'], $r['email'], $r['address'], $r['status'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function create()
    {
        Middleware::requirePermission('client', 'create');

        $this->view('client/form', [
            'pageTitle'  => 'Tambah Client',
            'mode'       => 'create',
            'client'     => null,
            'codeConfig' => $this->codeConfig->getConfig('client'),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('client', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('client', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('client', 'create');
        }

        $clientCode = $this->codeConfig->nextCode('client');
        if ($clientCode === null) {
            setFlash('error', 'Prefix kode Client belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Client.');
            $this->redirect('client', 'create');
        }

        $this->clientModel->create(array_merge($data, [
            'client_code' => $clientCode,
            'status'      => 'active',
            'created_by'  => currentUserId(),
        ]));

        $this->activityLog->log(currentUserId(), 'client', 'create', "Client '{$data['client_name']}' dibuat");
        setFlash('success', 'Client berhasil ditambahkan.');
        $this->redirect('client', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('client', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $client = $this->clientModel->find($id);

        if (!$client) {
            setFlash('error', 'Client tidak ditemukan.');
            $this->redirect('client', 'index');
        }

        $this->view('client/form', [
            'pageTitle' => 'Edit Client',
            'mode'      => 'edit',
            'client'    => $client,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('client', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('client', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->clientModel->find($id);

        if (!$existing) {
            setFlash('error', 'Client tidak ditemukan.');
            $this->redirect('client', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('client', 'edit', ['id' => $id]);
        }

        $postedStatus = $_POST['status'] ?? '';
        $data['status'] = in_array($postedStatus, ['active', 'inactive'], true) ? $postedStatus : $existing['status'];

        $this->clientModel->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'client', 'update', "Client '{$data['client_name']}' diperbarui");
        setFlash('success', 'Client berhasil diperbarui.');
        $this->redirect('client', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('client', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('client', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $client = $this->clientModel->find($id);

        if ($client) {
            $this->clientModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'client', 'delete', "Client '{$client['client_name']}' dihapus");
            setFlash('success', 'Client berhasil dihapus.');
        } else {
            setFlash('error', 'Client tidak ditemukan.');
        }

        $this->redirect('client', 'index');
    }

    /**
     * AJAX quick-add dipanggil dari form transaksi (Invoice Keluar, Surat Jalan).
     * Pola identik SupplierController::quickStore().
     */
    public function quickStore()
    {
        Middleware::requirePermission('client', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $clientCode = $this->codeConfig->nextCode('client');
        if ($clientCode === null) {
            $this->json(['errors' => ['Prefix kode Client belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Client.']], 422);
        }

        $id = $this->clientModel->create(array_merge($data, [
            'client_code' => $clientCode,
            'status'      => 'active',
            'created_by'  => currentUserId(),
        ]));

        $this->activityLog->log(
            currentUserId(),
            'client',
            'quick_add',
            "Client '{$data['client_name']}' ditambahkan cepat dari form lain"
        );

        $this->json(['id' => $id, 'label' => $data['client_name']]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        return [
            'client_name'    => trim($_POST['client_name'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
        ];
    }

    private function validateInput(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['client_name'] === '') {
            $errors[] = 'Nama client wajib diisi.';
        } elseif ($this->clientModel->nameExists($data['client_name'], $excludeId)) {
            $errors[] = "Data dengan nama '{$data['client_name']}' sudah tersedia.";
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }

        return $errors;
    }
}
