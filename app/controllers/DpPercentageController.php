<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/DpPercentage.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * DpPercentageController -- Master Data > Persentase Tagihan DP.
 * Dipakai sebagai pilihan dropdown "Tagihan DP" di Invoice Keluar
 * (SalesInvoiceController). Lihat migration 2026_08_24_invoice_dp_percentage.sql.
 */
class DpPercentageController extends Controller
{
    private DpPercentage $model;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('dp_percentage', 'view');

        $this->model       = new DpPercentage();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $this->view('dp_percentage/list', [
            'pageTitle' => 'Persentase Tagihan DP',
            'rows'      => $this->model->listAll(),
            'statusLabels' => $this->model->statusLabels,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('dp_percentage', 'create');
        $this->view('dp_percentage/form', [
            'pageTitle' => 'Tambah Persentase DP',
            'mode'      => 'create',
            'row'       => null,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('dp_percentage', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dp_percentage', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validate($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('dp_percentage', 'create');
        }

        $this->model->create(array_merge($data, ['created_by' => currentUserId()]));
        $this->activityLog->log(currentUserId(), 'dp_percentage', 'create', "Persentase DP '{$data['name']}' ({$data['percentage']}%) ditambahkan");
        setFlash('success', 'Persentase DP berhasil ditambahkan.');
        $this->redirect('dp_percentage', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('dp_percentage', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $row = $this->model->find($id);

        if (!$row) {
            setFlash('error', 'Persentase DP tidak ditemukan.');
            $this->redirect('dp_percentage', 'index');
        }

        $this->view('dp_percentage/form', [
            'pageTitle' => 'Edit Persentase DP',
            'mode'      => 'edit',
            'row'       => $row,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('dp_percentage', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dp_percentage', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            setFlash('error', 'Persentase DP tidak ditemukan.');
            $this->redirect('dp_percentage', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validate($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('dp_percentage', 'edit', ['id' => $id]);
        }

        // Status dikirim terpisah (checkbox toggle di list, bukan form ini) --
        // form create/edit fokus ke nama+persentase, status default tetap aktif
        // supaya persentase baru langsung bisa dipilih tanpa langkah tambahan.
        $this->model->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'dp_percentage', 'update', "Persentase DP '{$data['name']}' diperbarui menjadi {$data['percentage']}%");
        setFlash('success', 'Persentase DP berhasil diperbarui.');
        $this->redirect('dp_percentage', 'index');
    }

    /**
     * Toggle aktif/nonaktif langsung dari list (tanpa halaman edit terpisah).
     */
    public function toggleStatus()
    {
        Middleware::requirePermission('dp_percentage', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dp_percentage', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row) {
            setFlash('error', 'Persentase DP tidak ditemukan.');
            $this->redirect('dp_percentage', 'index');
        }

        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $this->model->updateById($id, ['status' => $newStatus]);
        $this->activityLog->log(currentUserId(), 'dp_percentage', 'update', "Persentase DP '{$row['name']}' diubah jadi " . ($newStatus === 'active' ? 'Aktif' : 'Nonaktif'));
        setFlash('success', 'Status persentase DP berhasil diubah.');
        $this->redirect('dp_percentage', 'index');
    }

    /**
     * Hapus (soft delete). Aman kapan saja -- invoice yang sudah terbit
     * menyimpan SNAPSHOT persentase sendiri (sales_invoices.dp_percentage),
     * tidak bergantung pada baris master ini setelah dibuat.
     */
    public function delete()
    {
        Middleware::requirePermission('dp_percentage', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('dp_percentage', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);

        if ($row) {
            $this->model->deleteById($id);
            $this->activityLog->log(currentUserId(), 'dp_percentage', 'delete', "Persentase DP '{$row['name']}' dihapus");
            setFlash('success', 'Persentase DP berhasil dihapus.');
        } else {
            setFlash('error', 'Persentase DP tidak ditemukan.');
        }

        $this->redirect('dp_percentage', 'index');
    }

    private function collectInput(): array
    {
        return [
            'name'       => trim($_POST['name'] ?? ''),
            'percentage' => (float) ($_POST['percentage'] ?? 0),
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'Nama persentase DP wajib diisi.';
        } elseif ($this->model->nameExists($data['name'], $excludeId)) {
            $errors[] = "Persentase DP dengan nama '{$data['name']}' sudah ada.";
        }
        if ($data['percentage'] <= 0 || $data['percentage'] > 100) {
            $errors[] = 'Persentase harus lebih dari 0 dan maksimal 100.';
        }
        return $errors;
    }
}
