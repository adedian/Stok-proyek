<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/StockOut.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Validasi Pengeluaran Barang.
 * ==========================================================================
 * Lapisan approval di atas stock_out yang sudah ada (pola identik
 * CashValidationController). Stok TIDAK disentuh di sini -- stock_out sudah
 * mendebit stok SAAT DIBUAT (StockOutController::store()), jadi validasi ini
 * murni approval/audit layer supaya tidak ada risiko stok terpotong dua kali.
 *
 * Hanya Super Admin yang otomatis berwenang (config/permissions.php). Untuk
 * user lain (mis. pemilik/"Gusti") diberi akses lewat override per-user di
 * User Management > Hak Akses -- BUKAN hardcode nama di controller ini.
 */
class StockOutValidationController extends Controller
{
    private StockOut $stockOutModel;
    private Project $projectModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('stock_out_validation', 'view');

        $this->stockOutModel = new StockOut();
        $this->projectModel  = new Project();
        $this->activityLog   = new ActivityLog();
    }

    public function index(): void
    {
        $status = $_GET['status'] ?? 'menunggu';
        if (!in_array($status, ['menunggu', 'tervalidasi', 'ditolak', 'semua'], true)) {
            $status = 'menunggu';
        }
        $filters = [
            'project_id' => $_GET['project_id'] ?? '',
            'keyword'    => trim($_GET['keyword'] ?? ''),
            'date_from'  => trim($_GET['date_from'] ?? ''),
            'date_to'    => trim($_GET['date_to'] ?? ''),
        ];

        $rows = $this->stockOutModel->listForValidation($status === 'semua' ? '' : $status, $filters);

        $this->view('stock_out_validation/list', [
            'pageTitle'   => 'Validasi Pengeluaran Barang',
            'rows'        => $rows,
            'status'      => $status,
            'filters'     => $filters,
            'projects'    => $this->projectModel->activeList(),
            'canValidate' => can('stock_out_validation', 'validate'),
        ]);
    }

    public function validate(): void
    {
        Middleware::requirePermission('stock_out_validation', 'validate');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out_validation', 'index');
        }
        verifyCsrf();

        $id       = (int) ($_POST['id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $note     = trim($_POST['note'] ?? '');

        $row = $this->stockOutModel->findWithRelations($id);
        if (!$row) {
            setFlash('error', 'Data pengeluaran barang tidak ditemukan.');
            $this->redirect('stock_out_validation', 'index');
        }

        // Hanya Pengeluaran Barang PROJECT yang boleh divalidasi -- dicek dari
        // data transaksi di DB (project_id), tidak mempercayai apa pun dari request.
        if (!StockOut::requiresValidation($row['project_id'] ?? null)) {
            setFlash('error', 'Pengeluaran barang ini bukan transaksi Project, tidak memerlukan validasi.');
            $this->redirect('stock_out_validation', 'index');
        }

        if (($row['validation_status'] ?? 'menunggu') !== 'menunggu') {
            setFlash('error', 'Transaksi ini sudah ' . $row['validation_status'] . ', tidak bisa divalidasi lagi.');
            $this->redirect('stock_out_validation', 'index');
        }

        if (!in_array($decision, ['tervalidasi', 'ditolak'], true)) {
            setFlash('error', 'Keputusan validasi tidak valid.');
            $this->redirect('stock_out_validation', 'index');
        }
        if ($decision === 'ditolak' && $note === '') {
            setFlash('error', 'Alasan penolakan wajib diisi.');
            $this->redirect('stock_out_validation', 'index');
        }

        // UPDATE bersyarat atomik (masih 'menunggu' + project). Request ganda/
        // bersamaan: hanya satu yang berhasil, sisanya berhenti di sini. Stok
        // tidak disentuh sama sekali (sudah didebit saat pengeluaran dibuat).
        if (!$this->stockOutModel->setValidation($id, $decision, (int) currentUserId(), $note)) {
            setFlash('error', 'Transaksi ini sudah diproses oleh user lain, tidak bisa divalidasi lagi.');
            $this->redirect('stock_out_validation', 'index');
        }

        $this->activityLog->log(
            currentUserId(),
            'stock_out_validation',
            $decision === 'tervalidasi' ? 'approve' : 'reject',
            "Pengeluaran barang '{$row['stock_out_number']}' (PIC {$row['pic_name']}) "
                . ($decision === 'tervalidasi' ? 'DIVALIDASI' : 'DITOLAK')
                . ($note !== '' ? " -- {$note}" : '')
        );

        setFlash('success', $decision === 'tervalidasi'
            ? "Pengeluaran barang {$row['stock_out_number']} tervalidasi."
            : "Pengeluaran barang {$row['stock_out_number']} ditolak.");
        $this->redirect('stock_out_validation', 'index');
    }
}
