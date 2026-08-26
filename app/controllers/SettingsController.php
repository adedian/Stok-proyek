<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/BackupHistory.php';
require_once ROOT_PATH . '/app/models/CompanyBankAccount.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class SettingsController extends Controller
{
    private SystemSetting $settingModel;
    private BackupHistory $backupModel;
    private CompanyBankAccount $bankAccountModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('settings', 'view');

        $this->settingModel     = new SystemSetting();
        $this->backupModel      = new BackupHistory();
        $this->bankAccountModel = new CompanyBankAccount();
        $this->activityLog      = new ActivityLog();
    }

    public function index()
    {
        $tab = $_GET['tab'] ?? 'company';

        $this->view('settings/index', [
            'pageTitle'        => 'Pengaturan Sistem',
            'activeTab'        => $tab,
            'company'          => $this->settingModel->getGroup('company'),
            'numbering'        => $this->settingModel->getGroup('numbering'),
            'sessionSettings'  => $this->settingModel->getGroup('session'),
            'notification'     => $this->settingModel->getGroup('notification'),
            'permissionMatrix' => require ROOT_PATH . '/config/permissions.php',
            'backups'          => $this->backupModel->recent(20),
            'bankAccounts'     => $this->bankAccountModel->all(),
        ]);
    }

    public function bankAccountStore()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }
        verifyCsrf();

        $data = $this->collectBankAccountInput();
        $errors = $this->validateBankAccountInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }

        $isFirst = empty($this->bankAccountModel->all());
        $id = $this->bankAccountModel->create(array_merge($data, ['created_by' => currentUserId()]));
        // Rekening pertama otomatis jadi aktif -- supaya Invoice selalu punya
        // rekening tampil begitu 1 rekening ditambahkan, tanpa langkah manual lagi.
        if ($isFirst) {
            $this->bankAccountModel->activate($id);
        }

        $this->activityLog->log(currentUserId(), 'settings', 'create', "Rekening '{$data['bank_name']}' ditambahkan");
        setFlash('success', 'Rekening berhasil ditambahkan.');
        $this->redirect('settings', 'index', ['tab' => 'bank']);
    }

    public function bankAccountUpdate()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        if (!$this->bankAccountModel->find($id)) {
            setFlash('error', 'Rekening tidak ditemukan.');
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }

        $data = $this->collectBankAccountInput();
        $errors = $this->validateBankAccountInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }

        $this->bankAccountModel->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'settings', 'update', "Rekening '{$data['bank_name']}' diperbarui");
        setFlash('success', 'Rekening berhasil diperbarui.');
        $this->redirect('settings', 'index', ['tab' => 'bank']);
    }

    public function bankAccountActivate()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $account = $this->bankAccountModel->find($id);
        if (!$account) {
            setFlash('error', 'Rekening tidak ditemukan.');
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }

        $this->bankAccountModel->activate($id);
        $this->activityLog->log(currentUserId(), 'settings', 'update', "Rekening '{$account['bank_name']}' dijadikan rekening aktif Invoice");
        setFlash('success', 'Rekening aktif berhasil diubah.');
        $this->redirect('settings', 'index', ['tab' => 'bank']);
    }

    public function bankAccountDelete()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index', ['tab' => 'bank']);
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $account = $this->bankAccountModel->find($id);
        if ($account) {
            $this->bankAccountModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'settings', 'delete', "Rekening '{$account['bank_name']}' dihapus");
            setFlash('success', 'Rekening berhasil dihapus.');
        } else {
            setFlash('error', 'Rekening tidak ditemukan.');
        }

        $this->redirect('settings', 'index', ['tab' => 'bank']);
    }

    // ================= Helper privat (rekening) =================

    private function collectBankAccountInput(): array
    {
        return [
            'bank_name'           => trim($_POST['bank_name'] ?? ''),
            'account_number'      => trim($_POST['account_number'] ?? ''),
            'account_holder_name' => trim($_POST['account_holder_name'] ?? ''),
        ];
    }

    private function validateBankAccountInput(array $data): array
    {
        $errors = [];
        if ($data['bank_name'] === '') {
            $errors[] = 'Nama bank wajib diisi.';
        }
        if ($data['account_number'] === '') {
            $errors[] = 'Nomor rekening wajib diisi.';
        }
        if ($data['account_holder_name'] === '') {
            $errors[] = 'Nama pemilik rekening wajib diisi.';
        }
        return $errors;
    }

    public function saveCompanyProfile()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index');
        }
        verifyCsrf();

        $fields = ['company_name', 'company_address', 'company_phone', 'company_email', 'company_npwp'];
        foreach ($fields as $field) {
            $this->settingModel->set($field, trim($_POST[$field] ?? ''), 'company', currentUserId());
        }

        try {
            $logo = handleFileUpload('company_logo', 'company', ['jpg', 'jpeg', 'png'], 2);
            if ($logo !== null) {
                $this->settingModel->set('company_logo', $logo, 'company', currentUserId());
            }
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('settings', 'index', ['tab' => 'company']);
        }

        $this->activityLog->log(currentUserId(), 'settings', 'update', 'Profil perusahaan diperbarui');
        setFlash('success', 'Profil perusahaan berhasil disimpan.');
        $this->redirect('settings', 'index', ['tab' => 'company']);
    }

    public function saveNumbering()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index');
        }
        verifyCsrf();

        foreach (['prefix_po', 'prefix_gr', 'prefix_opn', 'prefix_sto', 'prefix_off', 'prefix_sls', 'prefix_fkt', 'prefix_sj', 'prefix_tt', 'prefix_pay_bk', 'prefix_pay_kk', 'prefix_pay_kkp'] as $key) {
            $value = trim($_POST[$key] ?? '');
            if ($value !== '') {
                $this->settingModel->set($key, $value, 'numbering', currentUserId());
            }
        }

        $this->activityLog->log(currentUserId(), 'settings', 'update', 'Format penomoran dokumen diperbarui');
        setFlash('success', 'Pengaturan penomoran berhasil disimpan.');
        $this->redirect('settings', 'index', ['tab' => 'numbering']);
    }

    public function saveSession()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index');
        }
        verifyCsrf();

        $minutes = (int) ($_POST['session_timeout_minutes'] ?? 30);
        $minutes = max(5, min(480, $minutes));

        $this->settingModel->set('session_timeout_minutes', (string) $minutes, 'session', currentUserId());

        $this->activityLog->log(currentUserId(), 'settings', 'update', "Timeout session diubah menjadi {$minutes} menit");
        setFlash('success', 'Pengaturan session berhasil disimpan.');
        $this->redirect('settings', 'index', ['tab' => 'session']);
    }

    public function saveNotifications()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index');
        }
        verifyCsrf();

        $keys = ['notify_selisih_barang', 'notify_invoice_pending', 'notify_stok_minimum', 'notify_po_belum_diproses'];
        foreach ($keys as $key) {
            $value = !empty($_POST[$key]) ? '1' : '0';
            $this->settingModel->set($key, $value, 'notification', currentUserId());
        }

        $this->activityLog->log(currentUserId(), 'settings', 'update', 'Pengaturan notifikasi diperbarui');
        setFlash('success', 'Pengaturan notifikasi berhasil disimpan.');
        $this->redirect('settings', 'index', ['tab' => 'notification']);
    }

    /**
     * Backup manual: jalankan mysqldump lewat proc_open (bukan shell redirect `>`,
     * supaya portable), simpan file di luar public/ supaya tidak bisa diakses
     * langsung lewat URL -- download HARUS lewat backupDownload() yang terkontrol.
     */
    public function backupCreate()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index');
        }
        verifyCsrf();

        try {
            $filename = $this->runMysqldump();
            $filePath = BACKUP_PATH . '/' . $filename;

            $this->backupModel->create([
                'filename'   => $filename,
                'file_size'  => filesize($filePath) ?: 0,
                'created_by' => currentUserId(),
            ]);

            $this->activityLog->log(currentUserId(), 'settings', 'backup', "Backup database dibuat: {$filename}");
            setFlash('success', 'Backup database berhasil dibuat.');
        } catch (RuntimeException $e) {
            error_log('Backup database gagal: ' . $e->getMessage());
            setFlash('error', 'Gagal membuat backup: ' . $e->getMessage());
        }

        $this->redirect('settings', 'index', ['tab' => 'backup']);
    }

    public function backupDownload()
    {
        Middleware::requirePermission('settings', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $backup = $this->backupModel->find($id);

        if (!$backup) {
            setFlash('error', 'File backup tidak ditemukan.');
            $this->redirect('settings', 'index', ['tab' => 'backup']);
        }

        $filePath = BACKUP_PATH . '/' . $backup['filename'];
        if (!file_exists($filePath)) {
            setFlash('error', 'File backup sudah tidak ada di server.');
            $this->redirect('settings', 'index', ['tab' => 'backup']);
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $backup['filename'] . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    // ================= Helper privat =================

    private function runMysqldump(): string
    {
        if (!is_dir(BACKUP_PATH)) {
            mkdir(BACKUP_PATH, 0755, true);
        }

        $filename = 'backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql';
        $filePath = BACKUP_PATH . '/' . $filename;

        $cmdParts = [MYSQLDUMP_PATH, '-h', DB_HOST, '-u', DB_USER];
        if (DB_PASS !== '') {
            $cmdParts[] = '-p' . DB_PASS;
        }
        $cmdParts[] = DB_NAME;

        $cmdString = implode(' ', array_map('escapeshellarg', $cmdParts));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmdString, $descriptors, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Tidak bisa menjalankan mysqldump.');
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || trim($output) === '') {
            throw new RuntimeException(trim($errorOutput) !== '' ? trim($errorOutput) : 'mysqldump tidak menghasilkan output.');
        }

        file_put_contents($filePath, $output);

        return $filename;
    }
}
