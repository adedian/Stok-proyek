<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/CashTransaction.php';
require_once ROOT_PATH . '/app/models/CashTransactionItem.php';
require_once ROOT_PATH . '/app/models/CashCategory.php';
require_once ROOT_PATH . '/app/models/UserPicAssignment.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Inventory.php';

/**
 * Modul Kas (Revisi 9) -- catatan kas masuk & kas keluar.
 *
 * Satu Kas = 1 header (tanggal / PIC / No Bukti / kategori / mutasi) +
 * banyak baris item {uraian, qty, satuan(=harga satuan Rp), jumlah}.
 * `total_amount` header = SUM(jumlah).
 *
 * Visibilitas per-PIC (server-side, tidak dari frontend):
 *   Super Admin / Accounting / Project Manager  -> semua transaksi Kas.
 *   Purchase / PIC Project / Admin Project       -> hanya pic ∈ mapping user
 *   (tabel user_pic_assignments). `assertCanTouch()` cegah IDOR;
 *   validasi PIC saat store cegah selundupan lewat POST.
 */
class CashController extends Controller
{
    private CashTransaction $cashModel;
    private CashTransactionItem $itemModel;
    private CashCategory $categoryModel;
    private UserPicAssignment $picModel;
    private ActivityLog $activityLog;
    private Item $barangModel;
    private ItemCategory $barangCategoryModel;
    private Project $projectModel;
    private Inventory $inventoryModel;

    /** Action yang MEMBANGUN gerbang auth Kas -- boleh diakses tanpa auth Kas. */
    private const KAS_GATE_ACTIONS = ['kasLogin', 'kasAuthenticate', 'kasLogout', 'kasSetupPic', 'kasStorePic'];

    public function __construct()
    {
        Middleware::requirePermission('cash', 'view');

        $this->cashModel     = new CashTransaction();
        $this->itemModel     = new CashTransactionItem();
        $this->categoryModel = new CashCategory();
        $this->picModel      = new UserPicAssignment();
        $this->activityLog   = new ActivityLog();
        $this->barangModel         = new Item();
        $this->barangCategoryModel = new ItemCategory();
        $this->projectModel        = new Project();
        $this->inventoryModel      = new Inventory();

        $this->enforceKasAuth();
    }

    /**
     * SECOND-LEVEL AUTH: login aplikasi TIDAK cukup untuk membuka data Kas.
     * Role exempt (super_admin/accounting/project_manager) lolos langsung.
     * Role lain wajib verifikasi PIC + Password Kas dulu; kalau belum punya
     * PIC ber-kredensial -> diarahkan membuat PIC.
     */
    private function enforceKasAuth(): void
    {
        $action = $_GET['action'] ?? 'index';

        if (kasIsExemptRole(currentUserRole())) {
            return;
        }

        if (kasCheckTimeout()) {
            setFlash('error', 'Sesi Kas berakhir karena tidak aktif. Silakan verifikasi PIC Kas kembali. (Login aplikasi Anda tetap aktif.)');
        }

        if (in_array($action, self::KAS_GATE_ACTIONS, true)) {
            return;
        }

        if (!kasAuthenticated()) {
            if (!$this->picModel->hasLoginablePic((int) currentUserId())) {
                $this->redirect('cash', 'kasSetupPic');
            }
            $this->redirect('cash', 'kasLogin');
        }

        kasTouch();
    }

    // ===================== SECOND-LEVEL AUTH KAS =====================

    public function kasLogin(): void
    {
        if (kasIsExemptRole(currentUserRole()) || kasAuthenticated()) {
            $this->redirect('cash', 'index');
        }
        if (!$this->picModel->hasLoginablePic((int) currentUserId())) {
            $this->redirect('cash', 'kasSetupPic');
        }

        $this->view('cash/kas_login', [
            'pageTitle'   => 'Verifikasi Kas',
            'picNames'    => $this->picModel->loginablePicNames((int) currentUserId()),
            'lockedUntil' => kasLoginLockedUntil(),
            'failsLeft'   => kasFailsRemaining(),
        ]);
    }

    public function kasAuthenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'kasLogin');
        }
        verifyCsrf();
        if (kasIsExemptRole(currentUserRole())) {
            $this->redirect('cash', 'index');
        }
        if (kasLoginLockedUntil() !== null) {
            setFlash('error', 'Terlalu banyak percobaan gagal. Silakan coba lagi nanti.');
            $this->redirect('cash', 'kasLogin');
        }

        $uid      = (int) currentUserId();
        $picInput = trim($_POST['pic_name'] ?? '');
        $password = (string) ($_POST['kas_password'] ?? '');

        $row = $picInput !== '' ? $this->picModel->findLoginCandidate($uid, $picInput) : null;

        if (!$row || !password_verify($password, (string) $row['pic_password'])) {
            kasRegisterFailedLogin();
            $this->activityLog->log($uid, 'cash', 'kas_login_failed', "Verifikasi Kas GAGAL untuk PIC '{$picInput}'");
            setFlash('error', 'Nama PIC atau Password Kas salah.' . (kasFailsRemaining() > 0 ? ' Sisa percobaan: ' . kasFailsRemaining() . '.' : ''));
            $this->redirect('cash', 'kasLogin');
        }

        kasClearFailedLogin();
        session_regenerate_id(true);
        $_SESSION['kas_auth'] = [
            'ok'            => true,
            'pic_id'        => (int) $row['id'],
            'pic_name'      => $row['pic_name'],
            'account_id'    => $uid,
            'login_time'    => time(),
            'last_activity' => time(),
        ];
        $this->activityLog->log($uid, 'cash', 'kas_login', "Verifikasi Kas BERHASIL sebagai PIC '{$row['pic_name']}'");
        setFlash('success', 'Verifikasi Kas berhasil. Anda masuk sebagai PIC ' . $row['pic_name'] . '.');
        $this->redirect('cash', 'index');
    }

    public function kasLogout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();
        $pic = kasPicName() ?? '-';
        unset($_SESSION['kas_auth']);
        $this->activityLog->log((int) currentUserId(), 'cash', 'kas_logout', "Keluar sesi Kas (PIC '{$pic}')");
        setFlash('success', 'Anda keluar dari sesi Kas. Login aplikasi tetap aktif.');
        $this->redirect('cash', 'kasLogin');
    }

    public function kasSetupPic(): void
    {
        if (kasIsExemptRole(currentUserRole())) {
            $this->redirect('cash', 'index');
        }
        if ($this->picModel->hasLoginablePic((int) currentUserId())) {
            $this->redirect('cash', 'kasLogin');
        }
        $this->view('cash/kas_setup', [
            'pageTitle'     => 'PIC Kas Belum Terdaftar',
            'existingNames' => $this->picModel->picNamesForUser((int) currentUserId()),
        ]);
    }

    public function kasStorePic(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'kasSetupPic');
        }
        verifyCsrf();
        if (kasIsExemptRole(currentUserRole())) {
            $this->redirect('cash', 'index');
        }

        $uid      = (int) currentUserId();
        $picName  = trim($_POST['pic_name'] ?? '');
        $picUser  = trim($_POST['pic_username'] ?? '');
        $pass     = (string) ($_POST['kas_password'] ?? '');
        $passConf = (string) ($_POST['kas_password_confirm'] ?? '');

        $errors = [];
        if ($picName === '') {
            $errors[] = 'Nama PIC wajib diisi.';
        }
        if (mb_strlen($pass) < 6) {
            $errors[] = 'Password Kas minimal 6 karakter.';
        }
        if ($pass !== $passConf) {
            $errors[] = 'Konfirmasi Password Kas tidak cocok.';
        }
        if ($picUser !== '' && $this->picModel->picUsernameExists($picUser)) {
            $errors[] = 'Username PIC sudah dipakai. Pilih yang lain.';
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('cash', 'kasSetupPic');
        }

        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $existing = $this->picModel->rowByUserAndName($uid, $picName);

        if ($existing) {
            $this->picModel->setCredential((int) $existing['id'], $picUser ?: null, $hash, true);
        } else {
            $this->picModel->create([
                'user_id'      => $uid,
                'pic_name'     => $picName,
                'pic_username' => $picUser ?: null,
                'pic_password' => $hash,
                'is_active'    => 1,
                'created_by'   => $uid,
            ]);
        }

        $this->activityLog->log($uid, 'user_pic', 'pic_created', "PIC Kas '{$picName}' dibuat/di-set kredensialnya oleh akun sendiri");
        setFlash('success', 'PIC Kas berhasil dibuat. Silakan verifikasi untuk masuk.');
        $this->redirect('cash', 'kasLogin');
    }

    // ===================== LIST =====================

    public function index(): void
    {
        $filters  = $this->collectFilters();
        $scope    = $this->scopePics();
        $divScope = kasDivisionScope();
        $rows     = $this->cashModel->listFiltered($filters, $scope, $divScope);

        $summary = ['masuk' => 0.0, 'keluar' => 0.0];
        foreach ($rows as $r) {
            $summary[$r['mutasi']] += (float) $r['total_amount'];
        }

        // Kartu saldo -- SENGAJA hanya Super Admin / Accounting (cash.view_balance).
        $balances = null;
        if (can('cash', 'view_balance')) {
            $balances = $this->cashModel->balanceByDivision($scope, $divScope);
            $stamp = date('Y-m-d');
            if (($_SESSION['kas_balance_logged'] ?? '') !== $stamp) {
                $_SESSION['kas_balance_logged'] = $stamp;
                $this->activityLog->log((int) currentUserId(), 'cash', 'kas_view_balance', 'Melihat kartu saldo Kas');
            }
        }

        $this->view('cash/list', [
            'pageTitle'   => 'Kas',
            'rows'        => $rows,
            'filters'     => $filters,
            'categories'  => $this->categoryModel->activeList(),
            'picOptions'  => $scope === null ? $this->cashModel->distinctPics(null, $divScope) : $scope,
            'scoped'      => $scope !== null,
            'summary'     => $summary,
            'balances'    => $balances,
            'kasExempt'   => kasIsExemptRole(currentUserRole()),
            'kasPicName'  => kasIsExemptRole(currentUserRole()) ? null : kasPicName(),
        ]);
    }

    // ===================== LAPORAN KAS =====================

    public function report(): void
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $divScope = kasDivisionScope();
        $saldoAwal = $this->cashModel->saldoAwal($filters, $scope, $divScope);
        $ledger  = $this->cashModel->reportLedger($filters, $scope, $saldoAwal, $divScope);

        $this->view('cash/report', [
            'pageTitle'  => 'Laporan Kas',
            // Halaman ini sekarang diakses dari menu "Laporan" -- highlight sidebar
            // & breadcrumb ikut modul report, walau controller-nya tetap CashController
            // (biar scoping per-PIC + view/PDF/Excel Kas tidak perlu digandakan).
            'activeModuleOverride' => 'report',
            'ledger'     => $ledger,
            'filters'    => $filters,
            'categories' => $this->categoryModel->activeList(),
            'picOptions' => $scope === null ? $this->cashModel->distinctPics(null, $divScope) : $scope,
        ]);
    }

    public function printReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        ob_start();
        $company = $meta['company'];
        $periodText = $meta['period'];
        require ROOT_PATH . '/app/views/cash/_report_pdf.php';
        $html = ob_get_clean();
        streamPdf($html, 'laporan_kas_' . date('Ymd_His'));
    }

    public function exportReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        streamCashReportExcel(
            $ledger,
            $meta['company'],
            $meta['period'],
            'laporan_kas_' . date('Ymd_His')
        );
    }

    // ===================== CREATE =====================

    public function create(): void
    {
        Middleware::requirePermission('cash', 'create');

        $this->view('cash/form', [
            'pageTitle'      => 'Tambah Kas',
            'mode'           => 'create',
            'cash'           => null,
            'items'          => [],
            'categories'     => $this->categoryModel->activeList(),
            'picOptions'     => $this->picFieldOptions(),
            'projects'       => $this->projectModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->barangCategoryModel->activeList(),
        ]);
    }

    public function store(): void
    {
        Middleware::requirePermission('cash', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'create');
        }
        verifyCsrf();

        $data  = $this->collectInput();
        $items = $this->collectItems();
        $errors = $this->validateInput($data, $items, null);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('cash', 'create');
        }

        assertPeriodOpen('cash', $data['trx_date'], 'cash', 'create');

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $trxId = $this->cashModel->create([
                'trx_date'      => $data['trx_date'],
                'pic'           => $data['pic'],
                'division'      => $this->resolveDivision($data['pic']),
                'no_bukti'      => $data['no_bukti'],
                'category_id'   => $data['category_id'],
                'mutasi'        => $data['mutasi'],
                'affects_stock' => $data['affects_stock'],
                'project_id'    => $data['affects_stock'] ? ($data['project_id'] ?: null) : null,
                'supplier_name' => $data['affects_stock'] ? ($data['supplier_name'] ?: null) : null,
                'total_amount'  => $this->sumItems($items),
                'created_by'    => currentUserId(),
            ]);
            $this->saveItems($trxId, $items);

            $stockNote = '';
            if ($data['affects_stock']) {
                $n = $this->cashModel->applyStockCredit($trxId);
                $stockNote = " ({$n} baris menambah stok)";
            }

            $this->activityLog->log(
                currentUserId(),
                'cash',
                'create',
                "Kas {$data['mutasi']} '{$data['no_bukti']}' (PIC {$data['pic']}) dibuat, "
                    . count($items) . ' item, total ' . formatRupiah($this->sumItems($items)) . $stockNote
            );

            $pdo->commit();
            setFlash('success', 'Transaksi Kas berhasil disimpan.' . ($data['affects_stock'] ? ' Stok barang otomatis bertambah.' : ''));
            $this->redirect('cash', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Cash store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan transaksi Kas. Silakan coba lagi.');
            $this->redirect('cash', 'create');
        }
    }

    // ===================== EDIT =====================

    public function edit(): void
    {
        Middleware::requirePermission('cash', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $row = $this->cashModel->findWithRelations($id);
        if (!$row) {
            setFlash('error', 'Transaksi Kas tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->assertCanTouch($row);
        $this->activityLog->log(currentUserId(), 'cash', 'view', "Membuka transaksi Kas #{$id} ('{$row['no_bukti']}')");

        $this->view('cash/form', [
            'pageTitle'      => 'Edit Kas',
            'mode'           => 'edit',
            'cash'           => $row,
            'items'          => $this->itemModel->byTransaction($id),
            'categories'     => $this->categoryModel->activeList(),
            'picOptions'     => $this->picFieldOptions($row['pic']),
            'projects'       => $this->projectModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->barangCategoryModel->activeList(),
        ]);
    }

    public function update(): void
    {
        Middleware::requirePermission('cash', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->cashModel->findWithRelations($id);
        if (!$existing) {
            setFlash('error', 'Transaksi Kas tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->assertCanTouch($existing);

        $data  = $this->collectInput();
        $items = $this->collectItems();
        $errors = $this->validateInput($data, $items, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('cash', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('cash', $existing['trx_date'], 'cash', 'edit', ['id' => $id]);
        assertPeriodOpen('cash', $data['trx_date'], 'cash', 'edit', ['id' => $id]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // Balikkan dulu SEMUA kredit stok dari versi lama transaksi ini
            // (item/qty/project bisa saja berubah) -- baris stock_posted_at
            // yang terisi dikembalikan; aman kalau dulu tidak mempengaruhi stok.
            if ((int) $existing['affects_stock'] === 1) {
                $this->cashModel->applyStockReverse($id);
            }

            $this->cashModel->updateById($id, [
                'trx_date'      => $data['trx_date'],
                'pic'           => $data['pic'],
                'division'      => $this->resolveDivision($data['pic']),
                'no_bukti'      => $data['no_bukti'],
                'category_id'   => $data['category_id'],
                'mutasi'        => $data['mutasi'],
                'affects_stock' => $data['affects_stock'],
                'project_id'    => $data['affects_stock'] ? ($data['project_id'] ?: null) : null,
                'supplier_name' => $data['affects_stock'] ? ($data['supplier_name'] ?: null) : null,
                'total_amount'  => $this->sumItems($items),
            ]);
            $this->itemModel->deleteByTransaction($id);
            $this->saveItems($id, $items);

            if ($data['affects_stock']) {
                $this->cashModel->applyStockCredit($id);
            }

            $this->activityLog->log(currentUserId(), 'cash', 'update', "Kas #{$id} ('{$data['no_bukti']}') diperbarui"
                . ($data['affects_stock'] ? ' (stok disesuaikan ulang)' : ''));

            $pdo->commit();
            setFlash('success', 'Transaksi Kas berhasil diperbarui.');
            $this->redirect('cash', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Cash update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui transaksi Kas.');
            $this->redirect('cash', 'edit', ['id' => $id]);
        }
    }

    // ===================== DELETE (soft -> Trash) =====================

    public function delete(): void
    {
        Middleware::requirePermission('cash', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->cashModel->findWithRelations($id);
        if (!$row) {
            setFlash('error', 'Transaksi Kas tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->assertCanTouch($row);
        assertPeriodOpen('cash', $row['trx_date'], 'cash', 'index');

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            // Kas pembelian barang -> balikkan stok yang pernah ditambahkan
            // sebelum transaksi masuk Tempat Sampah. Di-credit lagi kalau di-restore.
            if ((int) $row['affects_stock'] === 1) {
                $this->cashModel->applyStockReverse($id);
            }
            $this->cashModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'cash', 'delete',
                "Kas #{$id} ('{$row['no_bukti']}') dihapus ke Tempat Sampah"
                . ((int) $row['affects_stock'] === 1 ? ' (stok dikembalikan)' : ''));
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Cash delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus transaksi Kas.');
            $this->redirect('cash', 'index');
        }

        setFlash('success', 'Transaksi Kas dipindahkan ke Tempat Sampah.');
        $this->redirect('cash', 'index');
    }

    // ===================== AJAX: baris item baru =====================

    public function ajaxItemRow(): void
    {
        Middleware::requirePermission('cash', 'create');
        $index = (int) ($_GET['index'] ?? 0);
        $item = null;
        $itemCatalog = $this->barangModel->activeList();
        ob_start();
        require ROOT_PATH . '/app/views/cash/_item_row.php';
        $html = ob_get_clean();
        $this->json(['html' => $html]);
    }

    // ===================== Helper privat =====================

    private function collectFilters(): array
    {
        return [
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'pic'         => trim($_GET['pic'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
            'mutasi'      => $_GET['mutasi'] ?? '',
        ];
    }

    /** [$ledger, ['company'=>.., 'period'=>..]] untuk PDF/Excel laporan. */
    private function buildReportData(): array
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $divScope = kasDivisionScope();
        $saldoAwal = $this->cashModel->saldoAwal($filters, $scope, $divScope);
        $ledger  = $this->cashModel->reportLedger($filters, $scope, $saldoAwal, $divScope);

        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $period = 'Periode : '
            . (!empty($filters['date_from']) ? formatTanggal($filters['date_from']) : 'Awal')
            . ' - '
            . (!empty($filters['date_to']) ? formatTanggal($filters['date_to']) : 'Sekarang');

        return [$ledger, ['company' => $companyName, 'period' => $period]];
    }

    /**
     * null = lihat semua PIC (super_admin/accounting/project_manager).
     * array = tepat 1 nama PIC yang barusan diverifikasi lewat login Kas
     * (purchase/pic_project/admin_project). [] = belum verifikasi (gate cegah).
     */
    private function scopePics(): ?array
    {
        return kasScopePicNames();
    }

    /**
     * Divisi transaksi Kas (snapshot) -- diambil dari akun pemilik nama PIC
     * yang dipilih; fallback ke divisi role pembuat.
     */
    private function resolveDivision(string $picName): string
    {
        $slug = $this->picModel->ownerRoleSlugForPic($picName);
        return kasDivisionForRole($slug ?? currentUserRole());
    }

    /** Pilihan dropdown PIC di form. Role ber-PIC: dikunci ke PIC sesi Kas.
     *  Role lihat-semua: semua PIC di master mapping. $current dipertahankan
     *  supaya data lama tetap terpilih walau tidak lagi di daftar. */
    private function picFieldOptions(?string $current = null): array
    {
        if (!kasIsExemptRole(currentUserRole())) {
            $name = kasPicName();
            $opts = $name ? [$name] : [];
            if ($current !== null && $current !== '' && !in_array($current, $opts, true)) {
                $opts[] = $current;
            }
            return $opts;
        }
        $opts = $this->picModel->allPicNames();
        if ($current !== null && $current !== '' && !in_array($current, $opts, true)) {
            $opts[] = $current;
        }
        sort($opts);
        return $opts;
    }

    private function assertCanTouch(array $row): void
    {
        $scope = $this->scopePics();
        if ($scope !== null && !in_array($row['pic'], $scope, true)) {
            denyAccess('Percobaan akses transaksi Kas milik PIC lain');
        }
    }

    private function collectInput(): array
    {
        $affectsStock = !empty($_POST['affects_stock']) ? 1 : 0;
        return [
            'trx_date'      => trim($_POST['trx_date'] ?? ''),
            'pic'           => trim($_POST['pic'] ?? ''),
            'no_bukti'      => trim($_POST['no_bukti'] ?? ''),
            'category_id'   => (int) ($_POST['category_id'] ?? 0),
            'mutasi'        => ($_POST['mutasi'] ?? '') === 'masuk' ? 'masuk'
                : (($_POST['mutasi'] ?? '') === 'keluar' ? 'keluar' : ''),
            'affects_stock' => $affectsStock,
            'project_id'    => $affectsStock ? (int) ($_POST['project_id'] ?? 0) : 0,
            'supplier_name' => $affectsStock ? trim($_POST['supplier_name'] ?? '') : '',
        ];
    }

    /**
     * Array baris item bersih dari POST. Selalu: uraian/qty/satuan(=harga)/jumlah.
     * Mode "Pembelian Barang" menambah: item_id/category_id/unit per baris
     * (dari dropdown master Barang). Baris tanpa item_id = uraian bebas biasa.
     */
    private function collectItems(): array
    {
        $uraian   = $_POST['item_uraian'] ?? [];
        $qty      = $_POST['item_qty'] ?? [];
        $satuan   = $_POST['item_satuan'] ?? [];
        $itemIds  = $_POST['item_id'] ?? [];
        $catIds   = $_POST['item_category_id'] ?? [];
        $units    = $_POST['item_unit'] ?? [];
        $out = [];
        for ($i = 0; $i < count($uraian); $i++) {
            $u = trim((string) ($uraian[$i] ?? ''));
            $q = (float) ($qty[$i] ?? 0);
            $s = parseCurrencyInput($satuan[$i] ?? 0);
            $iid = !empty($itemIds[$i]) ? (int) $itemIds[$i] : null;
            if ($u === '' && $q <= 0 && $s <= 0 && $iid === null) {
                continue; // baris kosong -> abaikan
            }
            $out[] = [
                'uraian'      => $u,
                'qty'         => $q,
                'satuan'      => $s,
                'jumlah'      => round($q * $s, 2),
                'item_id'     => $iid,
                'category_id' => !empty($catIds[$i]) ? (int) $catIds[$i] : null,
                'unit'        => trim((string) ($units[$i] ?? '')) ?: null,
            ];
        }
        return $out;
    }

    /** Scope inventory ('proyek'|'kantor') dari stock_type Barang. */
    private function kasStockScope(?string $stockType): string
    {
        return $stockType === 'inventory_kantor' ? 'kantor' : 'proyek';
    }

    private function sumItems(array $items): float
    {
        return round(array_sum(array_column($items, 'jumlah')), 2);
    }

    private function saveItems(int $trxId, array $items): void
    {
        foreach ($items as $it) {
            $this->itemModel->create([
                'cash_transaction_id' => $trxId,
                'item_id'     => $it['item_id'] ?? null,
                'category_id' => $it['category_id'] ?? null,
                'uraian'      => $it['uraian'],
                'unit'        => $it['unit'] ?? null,
                'qty'         => $it['qty'],
                'satuan'      => $it['satuan'],
                'jumlah'      => $it['jumlah'],
            ]);
        }
    }

    private function validateInput(array $d, array $items, ?int $excludeId): array
    {
        $errors = [];

        if ($d['trx_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['trx_date'])) {
            $errors[] = 'Tanggal wajib diisi.';
        }

        $scope = $this->scopePics();
        if ($d['pic'] === '') {
            $errors[] = 'PIC wajib diisi.';
        } elseif ($scope !== null && !in_array($d['pic'], $scope, true)) {
            $errors[] = 'PIC tidak valid untuk akun Anda.';
        }

        if ($d['no_bukti'] === '') {
            $errors[] = 'No Bukti wajib diisi.';
        } elseif ($this->cashModel->noBuktiExists($d['no_bukti'], $excludeId)) {
            $errors[] = 'No Bukti sudah dipakai transaksi lain.';
        }

        if ($d['category_id'] <= 0 || !$this->categoryModel->find($d['category_id'])) {
            $errors[] = 'Kategori Kas wajib dipilih.';
        }

        if ($d['mutasi'] === '') {
            $errors[] = 'Mutasi wajib dipilih (Masuk / Keluar).';
        }

        if (empty($items)) {
            $errors[] = 'Minimal 1 baris rincian (Uraian, Qty, Satuan) wajib diisi.';
        } else {
            foreach ($items as $i => $it) {
                $n = $i + 1;
                if ($it['uraian'] === '') {
                    $errors[] = "Baris {$n}: Uraian wajib diisi.";
                }
                if ($it['qty'] <= 0) {
                    $errors[] = "Baris {$n}: Qty harus lebih dari 0.";
                }
                if ($it['satuan'] < 0) {
                    $errors[] = "Baris {$n}: Satuan tidak valid.";
                }
            }
        }

        // ===== Mode "Pembelian Barang (masuk stok)" =====
        if (!empty($d['affects_stock'])) {
            $barangRows = array_values(array_filter($items, static fn($it) => !empty($it['item_id'])));
            if (empty($barangRows)) {
                $errors[] = 'Pembelian Barang: minimal 1 baris harus dipilih dari master Barang.';
            }

            $needProject = false;
            foreach ($items as $i => $it) {
                if (empty($it['item_id'])) {
                    continue;
                }
                $n = $i + 1;
                $barang = $this->barangModel->find((int) $it['item_id']);
                if (!$barang) {
                    $errors[] = "Baris {$n}: Barang tidak valid.";
                    continue;
                }
                if (empty($it['category_id']) || !$this->barangCategoryModel->find((int) $it['category_id'])) {
                    $errors[] = "Baris {$n}: Kategori barang wajib.";
                }
                if (empty($it['unit'])) {
                    $errors[] = "Baris {$n}: Satuan barang wajib.";
                }
                if ($this->kasStockScope($barang['stock_type'] ?? null) === 'proyek') {
                    $needProject = true;
                }
            }

            if ($needProject && ((int) ($d['project_id'] ?? 0) <= 0 || !$this->projectModel->find((int) $d['project_id']))) {
                $errors[] = 'Project wajib dipilih untuk pembelian barang stok proyek/lampu.';
            }
        }

        return $errors;
    }
}
