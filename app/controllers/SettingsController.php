<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/BackupHistory.php';
require_once ROOT_PATH . '/app/models/CompanyBankAccount.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/RolePermission.php';

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
            $logo = handleFileUpload('company_logo', 'company', ['jpg', 'jpeg', 'png', 'webp'], 2);
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

        $keys = ['notify_selisih_barang', 'notify_cash_validation', 'notify_invoice_pending', 'notify_stok_minimum', 'notify_po_belum_diproses'];
        foreach ($keys as $key) {
            $value = !empty($_POST[$key]) ? '1' : '0';
            $this->settingModel->set($key, $value, 'notification', currentUserId());
        }

        $this->activityLog->log(currentUserId(), 'settings', 'update', 'Pengaturan notifikasi diperbarui');
        setFlash('success', 'Pengaturan notifikasi berhasil disimpan.');
        $this->redirect('settings', 'index', ['tab' => 'notification']);
    }

    /**
     * Simpan matrix Hak Akses per-role (tab "Hak Akses").
     * POST: perm[<role_slug>][<module>.<action>] = 1  (checkbox tercentang saja).
     * Modul terkunci (settings/user/trash) & role di luar daftar editable
     * diabaikan total -- fail closed.
     */
    public function savePermissions()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index', ['tab' => 'permissions']);
        }
        verifyCsrf();

        $posted = $_POST['perm'] ?? [];
        $editableRoles = permissionEditableRoleSlugs();

        // Katalog TANPA modul terkunci -- batas apa yang boleh ditulis ke DB.
        $catalog = [];
        foreach (permissionActionCatalog() as $module => $actions) {
            if (!permissionIsLockedModule($module)) {
                $catalog[$module] = $actions;
            }
        }

        $grid = [];
        foreach ($editableRoles as $slug) {
            $grid[$slug] = is_array($posted[$slug] ?? null) ? $posted[$slug] : [];
        }

        try {
            (new RolePermission())->replaceForRoles($grid, $catalog, currentUserId());
        } catch (Throwable $e) {
            error_log('savePermissions gagal: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan hak akses. Silakan coba lagi.');
            $this->redirect('settings', 'index', ['tab' => 'permissions']);
        }

        $this->activityLog->log(currentUserId(), 'settings', 'update', 'Matrix Hak Akses per-role diperbarui');
        setFlash('success', 'Hak akses berhasil disimpan. Perubahan berlaku saat user membuka halaman berikutnya.');
        $this->redirect('settings', 'index', ['tab' => 'permissions']);
    }

    /**
     * Backup manual: dump seluruh database jadi 1 file .sql PURE-PHP (lewat PDO),
     * TANPA memanggil mysqldump / shell. Alasan: hosting cPanel meng-disable
     * exec/shell_exec/escapeshellarg dkk, jadi jalur shell selalu gagal.
     * File disimpan di luar public/ -- download HARUS lewat backupDownload().
     */
    public function backupCreate()
    {
        Middleware::requirePermission('settings', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('settings', 'index');
        }
        verifyCsrf();

        try {
            $filename = $this->dumpDatabase();
            $filePath = BACKUP_PATH . '/' . $filename;

            $this->backupModel->create([
                'filename'   => $filename,
                'file_size'  => filesize($filePath) ?: 0,
                'created_by' => currentUserId(),
            ]);

            $this->activityLog->log(currentUserId(), 'settings', 'backup', "Backup database dibuat: {$filename}");
            setFlash('success', 'Backup database berhasil dibuat.');
        } catch (Throwable $e) {
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

    /**
     * Dump seluruh database ke 1 file .sql, murni PHP + PDO (tanpa shell).
     * Hasilnya kompatibel untuk di-import lagi lewat phpMyAdmin / mysql CLI:
     * DROP TABLE IF EXISTS + CREATE TABLE + INSERT batch, FK check dimatikan
     * selama restore. Aman untuk DB ukuran aplikasi ini (puluhan tabel, ribuan
     * baris) -- ditulis streaming ke file, tidak menumpuk di memori.
     *
     * @return string nama file yang dibuat di BACKUP_PATH
     */
    private function dumpDatabase(): string
    {
        if (!is_dir(BACKUP_PATH) && !mkdir(BACKUP_PATH, 0755, true) && !is_dir(BACKUP_PATH)) {
            throw new RuntimeException('Folder backup tidak bisa dibuat: ' . BACKUP_PATH);
        }

        $filename = 'backup_' . DB_NAME . '_' . date('Ymd_His') . '.sql';
        $filePath = BACKUP_PATH . '/' . $filename;

        $pdo = getPDO();
        $fh = fopen($filePath, 'wb');
        if ($fh === false) {
            throw new RuntimeException('Tidak bisa menulis file backup.');
        }

        try {
            fwrite($fh, "-- HEXA STOK -- backup database `" . DB_NAME . "`\n");
            fwrite($fh, "-- Dibuat: " . date('Y-m-d H:i:s') . " (pure-PHP dump)\n");
            fwrite($fh, "SET NAMES utf8mb4;\n");
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($fh, "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

            // Hanya BASE TABLE (lewati VIEW).
            $tables = $pdo->query(
                "SELECT table_name FROM information_schema.tables
                  WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'
                  ORDER BY table_name"
            )->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $qTable = '`' . str_replace('`', '``', $table) . '`';

                fwrite($fh, "-- ----------------------------\n-- Struktur tabel {$qTable}\n-- ----------------------------\n");
                fwrite($fh, "DROP TABLE IF EXISTS {$qTable};\n");
                $create = $pdo->query("SHOW CREATE TABLE {$qTable}")->fetch(PDO::FETCH_ASSOC);
                fwrite($fh, ($create['Create Table'] ?? $create['Create View'] ?? '') . ";\n\n");

                // Data: batch INSERT (maks ~200 baris / statement).
                $stmt = $pdo->query("SELECT * FROM {$qTable}");
                $cols = null;
                $rowBuf = [];
                $written = false;
                $flush = function () use (&$rowBuf, $fh, $qTable, &$cols, &$written) {
                    if (!$rowBuf) {
                        return;
                    }
                    fwrite($fh, "INSERT INTO {$qTable} (" . implode(', ', $cols) . ") VALUES\n");
                    fwrite($fh, implode(",\n", $rowBuf) . ";\n");
                    $rowBuf = [];
                    $written = true;
                };

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($cols === null) {
                        $cols = array_map(static function ($c) {
                            return '`' . str_replace('`', '``', $c) . '`';
                        }, array_keys($row));
                    }
                    $vals = array_map(static function ($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote((string) $v);
                    }, array_values($row));
                    $rowBuf[] = '(' . implode(', ', $vals) . ')';
                    if (count($rowBuf) >= 200) {
                        $flush();
                    }
                }
                $flush();
                if ($written) {
                    fwrite($fh, "\n");
                }
            }

            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($fh);
        }

        if (!filesize($filePath)) {
            @unlink($filePath);
            throw new RuntimeException('File backup kosong.');
        }

        return $filename;
    }
}
