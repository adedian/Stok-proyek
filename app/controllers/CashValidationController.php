<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/CashTransaction.php';
require_once ROOT_PATH . '/app/models/CashTransactionItem.php';
require_once ROOT_PATH . '/app/models/UserPicAssignment.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Validasi Kas
 * ==========================================================================
 * Persetujuan transaksi Kas oleh atasan/divisi terkait. Routing validator
 * dari `cash_transactions.division` (snapshot role pembuat/PIC):
 *   accounting -> role accounting ; purchase -> role purchase ;
 *   project    -> role project_manager ; umum -> hanya super_admin.
 * super_admin boleh memvalidasi semua.
 *
 * Efek: transaksi 'tervalidasi' terkunci (hanya Super Admin bisa edit/hapus,
 * ditegakkan di CashController). 'ditolak' bisa diedit pembuatnya -> status
 * balik 'menunggu'. Stok TIDAK terpengaruh.
 *
 * Berada di balik gerbang auth Kas yang sama seperti modul Kas (role non-exempt
 * tetap wajib verifikasi PIC + Password Kas).
 */
class CashValidationController extends Controller
{
    private CashTransaction $cashModel;
    private CashTransactionItem $itemModel;
    private UserPicAssignment $picModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('cash_validation', 'view');

        $this->cashModel    = new CashTransaction();
        $this->itemModel    = new CashTransactionItem();
        $this->picModel     = new UserPicAssignment();
        $this->activityLog  = new ActivityLog();

        $this->enforceKasAuth();
    }

    /** Gerbang second-level auth Kas -- salinan alur CashController. */
    private function enforceKasAuth(): void
    {
        if (kasIsExemptRole(currentUserRole())) {
            return;
        }
        if (kasCheckTimeout()) {
            setFlash('error', 'Sesi Kas berakhir karena tidak aktif. Silakan verifikasi PIC Kas kembali.');
        }
        if (!kasAuthenticated()) {
            if (!$this->picModel->hasLoginablePic((int) currentUserId())) {
                $this->redirect('cash', 'kasSetupPic');
            }
            $this->redirect('cash', 'kasLogin');
        }
        kasTouch();
    }

    public function index(): void
    {
        $role = currentUserRole();
        $divisions = kasValidatableDivisions($role);

        $status = $_GET['status'] ?? 'menunggu';
        if (!in_array($status, ['menunggu', 'tervalidasi', 'ditolak', 'semua'], true)) {
            $status = 'menunggu';
        }
        $filters = [
            'keyword'   => trim($_GET['keyword'] ?? ''),
            'date_from' => trim($_GET['date_from'] ?? ''),
            'date_to'   => trim($_GET['date_to'] ?? ''),
        ];

        $rows = $this->cashModel->listForValidation(
            $divisions,
            $status === 'semua' ? '' : $status,
            $filters
        );

        // Lampirkan rincian item tiap transaksi (untuk ditampilkan di modal).
        foreach ($rows as &$row) {
            $row['items'] = $this->itemModel->byTransaction((int) $row['id']);
        }
        unset($row);

        $this->view('cash_validation/list', [
            'pageTitle'   => 'Validasi Kas',
            'rows'        => $rows,
            'status'      => $status,
            'filters'     => $filters,
            'divisions'   => $divisions,
            'canValidate' => can('cash_validation', 'validate'),
        ]);
    }

    public function validate(): void
    {
        Middleware::requirePermission('cash_validation', 'validate');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash_validation', 'index');
        }
        verifyCsrf();

        $id       = (int) ($_POST['id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $note     = trim($_POST['note'] ?? '');

        $row = $this->cashModel->findWithRelations($id);
        if (!$row) {
            setFlash('error', 'Transaksi Kas tidak ditemukan.');
            $this->redirect('cash_validation', 'index');
        }

        // Gerbang utama: role ini boleh memvalidasi divisi transaksi ini?
        if (!kasCanValidateDivision(currentUserRole(), $row['division'])) {
            denyAccess('Anda tidak berwenang memvalidasi transaksi Kas divisi ' . kasDivisionLabel($row['division']));
        }

        if ($row['validation_status'] !== 'menunggu') {
            setFlash('error', 'Transaksi ini sudah ' . $row['validation_status'] . ', tidak bisa divalidasi lagi.');
            $this->redirect('cash_validation', 'index');
        }

        if (!in_array($decision, ['tervalidasi', 'ditolak'], true)) {
            setFlash('error', 'Keputusan validasi tidak valid.');
            $this->redirect('cash_validation', 'index');
        }
        if ($decision === 'ditolak' && $note === '') {
            setFlash('error', 'Alasan penolakan wajib diisi.');
            $this->redirect('cash_validation', 'index');
        }

        $this->cashModel->setValidation($id, $decision, (int) currentUserId(), $note);

        $this->activityLog->log(
            currentUserId(),
            'cash_validation',
            $decision === 'tervalidasi' ? 'approve' : 'reject',
            "Kas '{$row['no_bukti']}' (PIC {$row['pic']}, divisi {$row['division']}) "
                . ($decision === 'tervalidasi' ? 'DIVALIDASI' : 'DITOLAK')
                . ($note !== '' ? " -- {$note}" : '')
        );

        setFlash('success', $decision === 'tervalidasi'
            ? "Transaksi Kas {$row['no_bukti']} tervalidasi."
            : "Transaksi Kas {$row['no_bukti']} ditolak.");
        $this->redirect('cash_validation', 'index');
    }
}
