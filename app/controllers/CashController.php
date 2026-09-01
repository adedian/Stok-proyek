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
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/GoodsReceipt.php';

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
    private Unit $unitModel;
    private Project $projectModel;
    private Item $barangModel;
    private ItemCategory $barangCategoryModel;
    private GoodsReceipt $grModel;

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
        $this->unitModel     = new Unit();
        $this->projectModel  = new Project();
        $this->barangModel         = new Item();
        $this->barangCategoryModel = new ItemCategory();
        $this->grModel             = new GoodsReceipt();

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
            'units'          => $this->unitModel->activeList(),
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
                'mutasi'        => $data['mutasi'],
                'total_amount'  => $this->sumItems($items),
                'created_by'    => currentUserId(),
            ]);
            $this->saveItems($trxId, $items);

            $n = $this->cashModel->applyStockCredit($trxId);
            $stockNote = $n > 0 ? " ({$n} baris menambah stok)" : '';

            $this->activityLog->log(
                currentUserId(),
                'cash',
                'create',
                "Kas {$data['mutasi']} '{$data['no_bukti']}' (PIC {$data['pic']}) dibuat, "
                    . count($items) . ' item, total ' . formatRupiah($this->sumItems($items)) . $stockNote
            );

            $pdo->commit();
            setFlash('success', 'Transaksi Kas berhasil disimpan.' . ($n > 0 ? ' Stok barang otomatis bertambah.' : ''));
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
            'units'          => $this->unitModel->activeList(),
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

        if ($gr = $this->grModel->firstActiveByCashTransaction($id)) {
            setFlash('error', "Transaksi Kas ini sudah diterima di Penerimaan Barang {$gr['receipt_number']}. "
                . 'Hapus/batalkan penerimaan barang itu dulu sebelum mengubah transaksi Kas ini.');
            $this->redirect('cash', 'edit', ['id' => $id]);
        }

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
            // (baris/qty/project/kategori bisa saja berubah) -- baris ber-
            // stock_posted_at dikembalikan. No-op kalau versi lama tidak
            // menyentuh stok.
            $this->cashModel->applyStockReverse($id);

            $this->cashModel->updateById($id, [
                'trx_date'      => $data['trx_date'],
                'pic'           => $data['pic'],
                'division'      => $this->resolveDivision($data['pic']),
                'no_bukti'      => $data['no_bukti'],
                'mutasi'        => $data['mutasi'],
                'total_amount'  => $this->sumItems($items),
            ]);
            $this->itemModel->deleteByTransaction($id);
            $this->saveItems($id, $items);

            $n = $this->cashModel->applyStockCredit($id);

            $this->activityLog->log(currentUserId(), 'cash', 'update', "Kas #{$id} ('{$data['no_bukti']}') diperbarui"
                . ($n > 0 ? ' (stok disesuaikan ulang)' : ''));

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

        $res = $this->deleteOneRecord($id);
        if ($res === true) {
            setFlash('success', 'Transaksi Kas dipindahkan ke Tempat Sampah.');
        } elseif (is_string($res) && $res !== 'gagal') {
            setFlash('error', "Transaksi Kas tidak bisa dihapus: {$res}.");
        } else {
            setFlash('error', 'Gagal menghapus transaksi Kas.');
        }
        $this->redirect('cash', 'index');
    }

    /**
     * Hapus 1 transaksi Kas ke Tempat Sampah + balikkan stok yang pernah
     * ditambahkan. true = sukses, string = alasan skip. Dipakai delete() &
     * rangeDelete() (rangeDelete KHUSUS Super Admin -> assertCanTouch no-op).
     */
    private function deleteOneRecord(int $id)
    {
        $row = $this->cashModel->findWithRelations($id);
        if (!$row) {
            return 'gagal';
        }
        // Barang dari transaksi Kas ini sudah diterima (GR aktif) -> stok sudah
        // dikoreksi lewat delta di GR. Menghapus Kas di sini akan membuat delta
        // itu menggantung. Wajib hapus penerimaannya dulu (berlaku juga untuk
        // Hapus per Rentang Tanggal -> dihitung sebagai dilewati).
        if ($gr = $this->grModel->firstActiveByCashTransaction($id)) {
            return "dipakai Penerimaan Barang {$gr['receipt_number']}";
        }
        // Soft-delete ke Tempat Sampah aman walau periode terkunci (stok dibalik
        // dengan transaksi tanggal-sekarang) -- gerbang Tutup Bulan tetap berlaku
        // untuk hapus per-baris lewat delete().

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            // Baris ber-kategori stok -> balikkan stok yang pernah ditambahkan.
            // No-op kalau transaksi ini tidak menyentuh stok.
            $this->cashModel->applyStockReverse($id);
            $this->cashModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'cash', 'delete',
                "Kas #{$id} ('{$row['no_bukti']}') dihapus ke Tempat Sampah");
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Cash deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua transaksi Kas dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete(): void
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('cash', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->cashModel->idsByDateRange('trx_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('cash', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('cash', 'index');
    }

    // ===================== AJAX: baris item baru =====================

    public function ajaxItemRow(): void
    {
        Middleware::requirePermission('cash', 'create');
        $index = (int) ($_GET['index'] ?? 0);
        $item = null;
        $cashCategories = $this->categoryModel->activeList();
        $units = $this->unitModel->activeList();
        $projects = $this->projectModel->activeList();
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
        return [
            'trx_date' => trim($_POST['trx_date'] ?? ''),
            'pic'      => trim($_POST['pic'] ?? ''),
            'no_bukti' => trim($_POST['no_bukti'] ?? ''),
            'mutasi'   => ($_POST['mutasi'] ?? '') === 'masuk' ? 'masuk'
                : (($_POST['mutasi'] ?? '') === 'keluar' ? 'keluar' : ''),
        ];
    }

    /**
     * Array baris rincian bersih dari POST: {uraian, cash_category_id, item_id,
     * project_id, supplier_name, unit, qty, satuan(=harga satuan Rp), jumlah}.
     * Kategori tiap baris dari Master Kategori Kas; kategori ber-affects_stock
     * membuat baris masuk stok -- WAJIB pilih Barang (item_id), Satuan wajib,
     * Project wajib bila scope 'proyek'. "Biaya Operasional" tidak.
     */
    private function collectItems(): array
    {
        $uraian    = $_POST['item_uraian'] ?? [];
        $qty       = $_POST['item_qty'] ?? [];
        $satuan    = $_POST['item_satuan'] ?? [];
        $catIds    = $_POST['item_cash_category_id'] ?? [];
        $units     = $_POST['item_unit'] ?? [];
        $projects  = $_POST['item_project_id'] ?? [];
        $suppliers = $_POST['item_supplier_name'] ?? [];
        $barangIds = $_POST['item_barang_id'] ?? [];
        $out = [];
        for ($i = 0; $i < count($uraian); $i++) {
            $u   = trim((string) ($uraian[$i] ?? ''));
            $q   = (float) ($qty[$i] ?? 0);
            $s   = parseCurrencyInput($satuan[$i] ?? 0);
            $cid = !empty($catIds[$i]) ? (int) $catIds[$i] : null;
            if ($u === '' && $q <= 0 && $s <= 0 && $cid === null) {
                continue; // baris kosong -> abaikan
            }
            $out[] = [
                'uraian'           => $u,
                'qty'              => $q,
                'satuan'           => $s,
                'jumlah'           => round($q * $s, 2),
                'cash_category_id' => $cid,
                'item_id'          => !empty($barangIds[$i]) ? (int) $barangIds[$i] : null,
                'project_id'       => !empty($projects[$i]) ? (int) $projects[$i] : null,
                'supplier_name'    => trim((string) ($suppliers[$i] ?? '')) ?: null,
                'unit'             => trim((string) ($units[$i] ?? '')) ?: null,
            ];
        }
        return $out;
    }

    private function sumItems(array $items): float
    {
        return round(array_sum(array_column($items, 'jumlah')), 2);
    }

    private function saveItems(int $trxId, array $items): void
    {
        foreach ($items as $it) {
            // Kolom stok (satuan / project / supplier) hanya relevan untuk baris
            // ber-kategori stok. Baris non-stok (mis. Biaya Operasional) disimpan
            // bersih tanpa kolom-kolom itu.
            $cat     = $it['cash_category_id'] ? ($this->categoryModel->find((int) $it['cash_category_id']) ?: null) : null;
            $isStock = $cat && (int) $cat['affects_stock'] === 1;
            $isProyek = $isStock && ($cat['stock_scope'] ?? null) === 'proyek';
            $this->itemModel->create([
                'cash_transaction_id' => $trxId,
                'cash_category_id'    => $it['cash_category_id'] ?? null,
                'item_id'            => $isStock ? ($it['item_id'] ?? null) : null,
                'project_id'          => $isProyek ? ($it['project_id'] ?? null) : null,
                'supplier_name'       => $isStock ? ($it['supplier_name'] ?? null) : null,
                'uraian'              => $it['uraian'],
                'unit'               => $isStock ? ($it['unit'] ?? null) : null,
                'qty'                => $it['qty'],
                'satuan'             => $it['satuan'],
                'jumlah'             => $it['jumlah'],
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

        if ($d['mutasi'] === '') {
            $errors[] = 'Mutasi wajib dipilih (Masuk / Keluar).';
        }

        $catMap = $this->categoryModel->mapById();

        if (empty($items)) {
            $errors[] = 'Minimal 1 baris rincian (Uraian, Kategori, Qty, Harga) wajib diisi.';
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
                    $errors[] = "Baris {$n}: Harga satuan tidak valid.";
                }

                $cid = (int) ($it['cash_category_id'] ?? 0);
                if ($cid <= 0 || !isset($catMap[$cid])) {
                    $errors[] = "Baris {$n}: Kategori wajib dipilih.";
                    continue;
                }
                $cat = $catMap[$cid];
                if ((int) $cat['affects_stock'] === 1) {
                    if (empty($it['item_id']) || !$this->barangModel->find((int) $it['item_id'])) {
                        $errors[] = "Baris {$n}: Barang wajib dipilih dari master untuk kategori '{$cat['category_name']}'.";
                    }
                    if (empty($it['unit'])) {
                        $errors[] = "Baris {$n}: Satuan wajib untuk kategori '{$cat['category_name']}'.";
                    }
                    if (($cat['stock_scope'] ?? null) === 'proyek') {
                        if ((int) ($it['project_id'] ?? 0) <= 0 || !$this->projectModel->find((int) $it['project_id'])) {
                            $errors[] = "Baris {$n}: Project wajib dipilih untuk kategori '{$cat['category_name']}'.";
                        }
                    }
                }
            }
        }

        return $errors;
    }
}
