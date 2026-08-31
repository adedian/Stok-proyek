<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PeriodLock.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Tutup Bulan (Laporan -> Tutup Bulan). SUPER ADMIN ONLY (permission
 * 'period_lock' terkunci -- lihat PERMISSION_LOCKED_MODULES).
 *
 * Menutup periode per-modul sampai tanggal tertentu -> transaksi modul tsb
 * dengan tanggal <= period_end tidak bisa dibuat/diubah/dihapus (gate
 * server-side lewat assertPeriodOpen() di period_helper.php).
 */
class PeriodLockController extends Controller
{
    private PeriodLock $model;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('period_lock', 'view');
        $this->model       = new PeriodLock();
        $this->activityLog = new ActivityLog();
    }

    public function index(): void
    {
        $this->view('period_lock/index', [
            'pageTitle'            => 'Tutup Bulan',
            'activeModuleOverride' => 'report',
            'modules'             => PeriodLock::MODULES,
            'history'             => $this->model->history(),
            'closedEnds'          => $this->model->allClosedEnds(),
        ]);
    }

    public function close(): void
    {
        Middleware::requirePermission('period_lock', 'close');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('period_lock', 'index');
        }
        verifyCsrf();

        $periodMonth = trim($_POST['period_month'] ?? '');   // 'YYYY-MM'
        $closeDate   = trim($_POST['close_date'] ?? '');      // 'YYYY-MM-DD'
        $modules     = array_values(array_intersect(
            array_keys(PeriodLock::MODULES),
            (array) ($_POST['modules'] ?? [])
        ));

        $errors = [];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $closeDate) || strtotime($closeDate) === false) {
            $errors[] = 'Tanggal tutup tidak valid.';
        }
        if (empty($modules)) {
            $errors[] = 'Pilih minimal 1 modul yang ingin ditutup.';
        }
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('period_lock', 'index');
        }

        // period_start: awal bulan dari period_month kalau ada, else awal bulan close_date.
        $periodStart = preg_match('/^\d{4}-\d{2}$/', $periodMonth)
            ? $periodMonth . '-01'
            : date('Y-m-01', strtotime($closeDate));

        $closed = [];
        $already = [];
        foreach ($modules as $m) {
            $res = $this->model->closePeriod($m, $periodStart, $closeDate, currentUserId());
            if ($res === 'already') {
                $already[] = PeriodLock::MODULES[$m];
                continue;
            }
            $closed[] = PeriodLock::MODULES[$m];
            $this->activityLog->log(
                currentUserId(),
                'period_lock',
                'close',
                "Tutup periode {$m} s/d {$closeDate}"
            );
        }

        $msg = [];
        if ($closed)  { $msg[] = 'Periode ditutup: ' . implode(', ', $closed) . ' (s/d ' . formatTanggal($closeDate) . ').'; }
        if ($already) { $msg[] = 'Sudah tertutup sebelumnya: ' . implode(', ', $already) . '.'; }
        setFlash($closed ? 'success' : 'info', implode(' ', $msg) ?: 'Tidak ada perubahan.');
        $this->redirect('period_lock', 'index');
    }

    public function reopen(): void
    {
        Middleware::requirePermission('period_lock', 'reopen');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('period_lock', 'index');
        }
        verifyCsrf();

        $id  = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row) {
            setFlash('error', 'Data penutupan periode tidak ditemukan.');
            $this->redirect('period_lock', 'index');
        }

        if (!$this->model->reopen($id, currentUserId())) {
            setFlash('error', 'Periode ini tidak dalam status tertutup.');
            $this->redirect('period_lock', 'index');
        }

        $label = PeriodLock::MODULES[$row['module']] ?? $row['module'];
        $this->activityLog->log(
            currentUserId(),
            'period_lock',
            'reopen',
            "Buka kembali periode {$row['module']} s/d {$row['period_end']}"
        );
        setFlash('success', "Periode {$label} s/d " . formatTanggal($row['period_end']) . ' berhasil dibuka kembali.');
        $this->redirect('period_lock', 'index');
    }
}
