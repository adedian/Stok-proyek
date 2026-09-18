<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/BankTransaction.php';
require_once ROOT_PATH . '/app/models/MasterBank.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/CashNumber.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Modul Bank (Revisi Kas/Bank) -- KHUSUS Super Admin & Accounting (lihat
 * config/permissions.php modul 'bank'). Analog Kas tapi lebih flat (tanpa
 * baris rincian/kategori/integrasi stok -- Bank tidak menyentuh stok).
 * Tidak ada second-level auth (Accounting sudah exempt dari gerbang Kas).
 *
 * No Bukti pakai mekanisme atomic yang sama dengan Kas (CashNumber), prefix
 * tetap "BANK" untuk semua transaksi Bank -- menghindari membuat infrastruktur
 * counter baru untuk kebutuhan yang sama persis.
 */
class BankController extends Controller
{
    private BankTransaction $model;
    private MasterBank $bankModel;
    private Project $projectModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('bank', 'view');
        $this->model = new BankTransaction();
        $this->bankModel = new MasterBank();
        $this->projectModel = new Project();
        $this->activityLog = new ActivityLog();
    }

    private function collectFilters(): array
    {
        return [
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
            'project_id' => $_GET['project_id'] ?? '',
            'bank_ids'   => isset($_GET['bank_ids']) && is_array($_GET['bank_ids']) ? array_map('intval', $_GET['bank_ids']) : [],
            'mutasi'     => $_GET['mutasi'] ?? '',
            'keyword'    => trim($_GET['keyword'] ?? ''),
        ];
    }

    /**
     * List Bank berdiri sendiri DIHAPUS dari navigasi (revisi lanjutan) --
     * transaksi Bank sekarang digabung tampil di menu Kas (CashController::index(),
     * hanya untuk can('bank','view')). Redirect supaya bookmark/link lama tidak mati.
     */
    public function index(): void
    {
        $this->redirect('cash', 'index');
    }

    public function create(): void
    {
        Middleware::requirePermission('bank', 'create');
        $this->view('bank/form', [
            'pageTitle' => 'Tambah Transaksi Bank',
            'mode'      => 'create',
            'row'       => null,
            'banks'     => $this->bankModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
        ]);
    }

    public function store(): void
    {
        Middleware::requirePermission('bank', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('bank', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('bank', 'create');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $noBukti = (new CashNumber())->next('BANK');
            $guard = 0;
            while ($this->model->noBuktiExists($noBukti) && $guard++ < 50) {
                $noBukti = (new CashNumber())->next('BANK');
            }
            $this->model->create(array_merge($data, [
                'no_bukti'   => $noBukti,
                'created_by' => currentUserId(),
            ]));
            $this->activityLog->log(currentUserId(), 'bank', 'create', "Transaksi Bank {$data['mutasi']} '{$noBukti}' dibuat, " . formatRupiah($data['amount']));
            $pdo->commit();
            setFlash('success', 'Transaksi Bank berhasil disimpan.');
            $this->redirect('cash', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Bank store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan transaksi Bank.');
            $this->redirect('bank', 'create');
        }
    }

    public function edit(): void
    {
        Middleware::requirePermission('bank', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row) {
            setFlash('error', 'Transaksi Bank tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->view('bank/form', [
            'pageTitle' => 'Edit Transaksi Bank',
            'mode'      => 'edit',
            'row'       => $row,
            'banks'     => $this->bankModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
        ]);
    }

    public function update(): void
    {
        Middleware::requirePermission('bank', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            setFlash('error', 'Transaksi Bank tidak ditemukan.');
            $this->redirect('cash', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validate($data);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('bank', 'edit', ['id' => $id]);
        }

        $this->model->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'bank', 'update', "Transaksi Bank #{$id} ('{$existing['no_bukti']}') diperbarui");
        setFlash('success', 'Transaksi Bank berhasil diperbarui.');
        $this->redirect('cash', 'index');
    }

    public function delete(): void
    {
        Middleware::requirePermission('bank', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);
        if ($row) {
            $this->model->deleteById($id);
            $this->activityLog->log(currentUserId(), 'bank', 'delete', "Transaksi Bank #{$id} ('{$row['no_bukti']}') dihapus");
            setFlash('success', 'Transaksi Bank dipindahkan ke Tempat Sampah.');
        } else {
            setFlash('error', 'Transaksi Bank tidak ditemukan.');
        }
        $this->redirect('cash', 'index');
    }

    /**
     * Laporan Bank berdiri sendiri DIHAPUS dari navigasi (revisi lanjutan) --
     * digabung ke Laporan Kas (centang Bank di filter). Redirect saja.
     */
    public function report(): void
    {
        $this->redirect('cash', 'report');
    }

    public function printReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        $this->activityLog->log(currentUserId(), 'bank', 'print', 'Cetak PDF Laporan Bank (' . $meta['period'] . ')');
        ob_start();
        $company = $meta['company'];
        $periodText = $meta['period'];
        $reportTitle = $meta['title'];
        require ROOT_PATH . '/app/views/bank/_report_pdf.php';
        $html = ob_get_clean();
        streamPdf($html, 'laporan_bank_' . date('Ymd_His'));
    }

    private function buildReportData(): array
    {
        $filters = $this->collectFilters();
        $ledger = $this->model->reportLedger($filters);

        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $period = 'Periode : '
            . (!empty($filters['date_from']) ? formatTanggal($filters['date_from']) : 'Awal')
            . ' - '
            . (!empty($filters['date_to']) ? formatTanggal($filters['date_to']) : 'Sekarang');

        $title = 'LAPORAN BANK — SEMUA PROJECT';
        if (!empty($filters['project_id'])) {
            $p = $this->projectModel->find((int) $filters['project_id']);
            if ($p) {
                $title = 'LAPORAN BANK ' . mb_strtoupper($p['project_name']);
            }
        }

        return [$ledger, ['company' => $companyName, 'period' => $period, 'title' => $title]];
    }

    private function collectInput(): array
    {
        return [
            'trx_date'   => trim($_POST['trx_date'] ?? ''),
            'bank_id'    => (int) ($_POST['bank_id'] ?? 0),
            'project_id' => !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null,
            'pic'        => trim($_POST['pic'] ?? '') ?: null,
            'uraian'     => trim($_POST['uraian'] ?? ''),
            'mutasi'     => ($_POST['mutasi'] ?? '') === 'masuk' ? 'masuk'
                : (($_POST['mutasi'] ?? '') === 'keluar' ? 'keluar' : ''),
            'amount'     => parseCurrencyInput($_POST['amount'] ?? 0),
        ];
    }

    private function validate(array $d): array
    {
        $errors = [];
        if ($d['trx_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['trx_date'])) {
            $errors[] = 'Tanggal wajib diisi.';
        }
        if ($d['bank_id'] <= 0 || !$this->bankModel->find($d['bank_id'])) {
            $errors[] = 'Bank wajib dipilih.';
        }
        if (!empty($d['project_id']) && !$this->projectModel->find((int) $d['project_id'])) {
            $errors[] = 'Project yang dipilih tidak valid.';
        }
        if ($d['uraian'] === '') {
            $errors[] = 'Uraian wajib diisi.';
        }
        if ($d['mutasi'] === '') {
            $errors[] = 'Mutasi wajib dipilih (Masuk / Keluar).';
        }
        if (abs((float) $d['amount']) < 0.005) {
            $errors[] = 'Nominal wajib diisi.';
        }
        return $errors;
    }
}
