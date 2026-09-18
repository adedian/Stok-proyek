<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/CashTransaction.php';
require_once ROOT_PATH . '/app/models/CashTransactionItem.php';
require_once ROOT_PATH . '/app/models/CashCategory.php';
require_once ROOT_PATH . '/app/models/CashNumber.php';
require_once ROOT_PATH . '/app/models/UserPicAssignment.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/ProjectUserAccess.php';
require_once ROOT_PATH . '/app/models/MasterRekening.php';

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
    private ProjectUserAccess $projectAccessModel;
    private MasterRekening $rekeningModel;

    /** Action yang MEMBANGUN gerbang auth Kas -- boleh diakses tanpa auth Kas. */
    private const KAS_GATE_ACTIONS = [
        'kasLogin', 'kasAuthenticate', 'kasLogout', 'kasSetupPic', 'kasStorePic',
        'kasProjectLogin', 'kasProjectAuthenticate', 'kasProjectLogout',
    ];

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
        $this->projectAccessModel  = new ProjectUserAccess();
        $this->rekeningModel       = new MasterRekening();

        $this->enforceKasAuth();
    }

    /**
     * SECOND-LEVEL AUTH: login aplikasi TIDAK cukup untuk membuka data Kas.
     * Role exempt (super_admin/accounting/project_manager) lolos langsung.
     *
     * Role purchase/pic_project/admin_project (kasProjectGateRoles()) wajib
     * verifikasi Project + password akun sendiri (gerbang BARU, lihat
     * kasProjectLogin/kasProjectAuthenticate di bawah).
     *
     * Role lain (tidak ada saat ini, dipertahankan untuk kompatibilitas)
     * tetap memakai gerbang PIC + Password Kas LAMA (kasLogin/kasSetupPic).
     */
    private function enforceKasAuth(): void
    {
        $action = $_GET['action'] ?? 'index';
        $role = currentUserRole();

        if (kasIsExemptRole($role)) {
            return;
        }

        if (in_array($action, self::KAS_GATE_ACTIONS, true)) {
            return;
        }

        if (kasIsProjectGateRole($role)) {
            if (kasProjectCheckTimeout()) {
                setFlash('error', 'Sesi Kas berakhir karena tidak aktif. Silakan verifikasi Project & Password kembali. (Login aplikasi Anda tetap aktif.)');
            }
            if (!kasProjectAuthenticated()) {
                $this->redirect('cash', 'kasProjectLogin');
            }
            kasProjectTouch();
            return;
        }

        // Fallback gerbang lama -- tidak ada role yang mencapai baris ini saat
        // ini (semua role ber-PIC sudah masuk kasProjectGateRoles()), tapi
        // kode dipertahankan utuh untuk kompatibilitas.
        if (kasCheckTimeout()) {
            setFlash('error', 'Sesi Kas berakhir karena tidak aktif. Silakan verifikasi PIC Kas kembali. (Login aplikasi Anda tetap aktif.)');
        }
        if (!kasAuthenticated()) {
            if (!$this->picModel->hasLoginablePic((int) currentUserId())) {
                $this->redirect('cash', 'kasSetupPic');
            }
            $this->redirect('cash', 'kasLogin');
        }
        kasTouch();
    }

    // ===================== GERBANG PROJECT + PASSWORD (baru) =====================

    public function kasProjectLogin(): void
    {
        if (!kasIsProjectGateRole(currentUserRole()) || kasProjectAuthenticated()) {
            $this->redirect('cash', 'index');
        }
        $this->view('cash/kas_project_login', [
            'pageTitle'   => 'Verifikasi Kas',
            'projects'    => $this->projectAccessModel->projectsForUser((int) currentUserId()),
            'lockedUntil' => kasProjectLoginLockedUntil(),
            'failsLeft'   => kasProjectFailsRemaining(),
        ]);
    }

    public function kasProjectAuthenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'kasProjectLogin');
        }
        verifyCsrf();
        if (!kasIsProjectGateRole(currentUserRole())) {
            $this->redirect('cash', 'index');
        }
        if (kasProjectLoginLockedUntil() !== null) {
            setFlash('error', 'Terlalu banyak percobaan gagal. Silakan coba lagi nanti.');
            $this->redirect('cash', 'kasProjectLogin');
        }

        $uid       = (int) currentUserId();
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $password  = (string) ($_POST['password'] ?? '');

        // Ownership project WAJIB dicek server-side lewat project_user_access --
        // bukan cuma dari isi dropdown. Manipulasi project_id via POST untuk
        // project milik user lain akan ditolak di sini.
        $hasAccess = $projectId > 0 && $this->projectAccessModel->userHasAccess($uid, $projectId);
        $user      = $hasAccess ? (new User())->find($uid) : null;

        if (!$hasAccess || !$user || !password_verify($password, (string) $user['password'])) {
            kasProjectRegisterFailedLogin();
            $this->activityLog->log($uid, 'cash', 'kas_login_failed', "Verifikasi Kas-Project GAGAL (project_id={$projectId})");
            setFlash('error', 'Project atau Password salah.' . (kasProjectFailsRemaining() > 0 ? ' Sisa percobaan: ' . kasProjectFailsRemaining() . '.' : ''));
            $this->redirect('cash', 'kasProjectLogin');
        }

        $project = $this->projectModel->find($projectId);

        kasProjectClearFailedLogin();
        session_regenerate_id(true);
        $_SESSION['kas_project_auth'] = [
            'ok'            => true,
            'project_id'    => $projectId,
            'project_name'  => $project['project_name'] ?? ('Project #' . $projectId),
            'account_id'    => $uid,
            'login_time'    => time(),
            'last_activity' => time(),
        ];
        $this->activityLog->log($uid, 'cash', 'kas_login', "Verifikasi Kas-Project BERHASIL untuk '{$_SESSION['kas_project_auth']['project_name']}'");
        setFlash('success', 'Verifikasi berhasil. Anda masuk Kas untuk project ' . $_SESSION['kas_project_auth']['project_name'] . '.');
        $this->redirect('cash', 'index');
    }

    public function kasProjectLogout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();
        $pname = kasProjectName() ?? '-';
        unset($_SESSION['kas_project_auth']);
        $this->activityLog->log((int) currentUserId(), 'cash', 'kas_logout', "Keluar sesi Kas-Project (project '{$pname}')");
        setFlash('success', 'Anda keluar dari sesi Kas. Login aplikasi tetap aktif.');
        $this->redirect('cash', 'kasProjectLogin');
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
            'pageTitle'     => 'Verifikasi Kas',
            'picNames'      => $this->picModel->loginablePicNames((int) currentUserId()),
            'existingNames' => $this->picModel->passwordlessPicNamesForUser((int) currentUserId()),
            'lockedUntil'   => kasLoginLockedUntil(),
            'failsLeft'     => kasFailsRemaining(),
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
            'existingNames' => $this->picModel->passwordlessPicNamesForUser((int) currentUserId()),
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
        $prefixRaw = trim($_POST['kas_prefix'] ?? '');
        $prefix    = CashNumber::normalizePrefix($prefixRaw);
        $existing  = $this->picModel->rowByUserAndName($uid, $picName);

        $errors = [];
        if ($picName === '') {
            $errors[] = 'Nama PIC wajib diisi.';
        }
        // Prefix Kas wajib -- KECUALI kalau menautkan password ke PIC lama yang
        // SUDAH punya prefix (biarkan kosong = pakai yang lama).
        $needPrefix = !($existing && !empty($existing['kas_prefix']) && $prefixRaw === '');
        if ($needPrefix) {
            if ($prefix === '') {
                $errors[] = 'Prefix Kas wajib diisi.';
            } elseif (!CashNumber::isValidPrefix($prefix)) {
                $errors[] = 'Format Prefix Kas tidak valid (2-6 huruf/angka, harus diawali huruf).';
            } elseif ($this->picModel->prefixExists($prefix, $existing ? (int) $existing['id'] : null)) {
                $errors[] = 'Prefix sudah digunakan oleh akun lain.';
            }
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

        if ($existing) {
            $this->picModel->setCredential((int) $existing['id'], $picUser ?: null, $hash, true);
            if ($needPrefix && $prefix !== '') {
                $this->picModel->setPrefix((int) $existing['id'], $prefix);
            }
        } else {
            $newId = $this->picModel->create([
                'user_id'      => $uid,
                'pic_name'     => $picName,
                'pic_username' => $picUser ?: null,
                'pic_password' => $hash,
                'is_active'    => 1,
                'created_by'   => $uid,
            ]);
            $this->picModel->setPrefix((int) $newId, $prefix);
        }

        $this->activityLog->log($uid, 'user_pic', 'pic_created', "PIC Kas '{$picName}' (prefix '{$prefix}') dibuat/di-set kredensialnya oleh akun sendiri");
        setFlash('success', 'PIC Kas berhasil dibuat. Silakan verifikasi untuk masuk.');
        $this->redirect('cash', 'kasLogin');
    }

    // ===================== LIST =====================

    public function index(): void
    {
        $filters  = $this->collectFilters();
        $scope    = $this->scopePics();
        $divScope = kasDivisionScope();
        $projScope = $this->scopeProjectId();
        $rows     = $this->cashModel->listFiltered($filters, $scope, $divScope, $projScope);

        $summary = ['masuk' => 0.0, 'keluar' => 0.0];
        foreach ($rows as $r) {
            $summary[$r['mutasi']] += (float) $r['total_amount'];
        }

        // Kartu saldo (cash.view_balance):
        //   Super Admin / Accounting -> SEMUA divisi + Total Saldo.
        //   Purchase / PIC Project / Admin Project / Project Manager -> HANYA
        //   saldo divisi mereka sendiri (purchase -> Saldo Kas Purchase, role
        //   project -> Saldo Kas Project), tanpa Total & tanpa divisi lain.
        $balances = null;
        $balanceShowTotal = false;
        if (can('cash', 'view_balance')) {
            $role = currentUserRole();
            $balanceShowTotal = in_array($role, [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING], true);
            // Saldo divisi dihitung PENUH (semua PIC divisi itu), bukan hanya PIC user.
            $balDivScope = $balanceShowTotal ? null : [kasDivisionForRole($role)];
            $balances = $this->cashModel->balanceByDivision(null, $balDivScope);
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
            // Dropdown PIC: gabungan PIC yang sudah punya transaksi + SEMUA PIC
            // dari master mapping yang divisinya boleh dilihat user ini (jadi
            // PM lihat Tio/dian/Rizal walau belum ada transaksinya). Role
            // ber-scope PIC sendiri: tetap hanya PIC-nya.
            'picOptions'  => $scope === null
                ? $this->mergedPicOptions($divScope)
                : $scope,
            'scoped'      => $scope !== null,
            'summary'     => $summary,
            'balances'    => $balances,
            'balanceShowTotal' => $balanceShowTotal,
            'kasExempt'   => kasIsExemptRole(currentUserRole()),
            'kasPicName'  => kasIsExemptRole(currentUserRole()) ? null : kasPicName(),
            'kasProjectGated' => kasIsProjectGateRole(currentUserRole()),
            'kasProjectName'  => kasIsProjectGateRole(currentUserRole()) ? kasProjectName() : null,
        ]);
    }

    // ===================== LAPORAN KAS =====================

    /**
     * Project yang tersedia di filter Laporan Kas/Bank. Role gerbang Project:
     * hanya project yang sedang dibuka (1 opsi, terkunci). Role lain: semua
     * project yang boleh dilihat (saat ini tidak dibatasi lagi -- Accounting/
     * Super Admin/PM sudah scoped lewat divisi/permission modul).
     */
    private function reportProjectOptions(): array
    {
        if (kasIsProjectGateRole(currentUserRole())) {
            $pid = $this->scopeProjectId();
            $p = $pid ? $this->projectModel->find($pid) : null;
            return $p ? [$p] : [];
        }
        return $this->projectModel->activeList();
    }

    public function report(): void
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $divScope = kasDivisionScope();
        $projScope = $this->scopeProjectId();
        $saldoAwal = $this->cashModel->saldoAwal($filters, $scope, $divScope, $projScope);
        $ledger  = $this->cashModel->reportLedger($filters, $scope, $saldoAwal, $divScope, $projScope);

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
            'projectOptions' => $this->reportProjectOptions(),
            'projectGated'   => kasIsProjectGateRole(currentUserRole()),
        ]);
    }

    public function printReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        $this->activityLog->log(currentUserId(), 'cash', 'print', 'Cetak PDF Laporan Kas (' . $meta['period'] . ')');
        ob_start();
        $company = $meta['company'];
        $periodText = $meta['period'];
        $reportTitle = $meta['title'];
        require ROOT_PATH . '/app/views/cash/_report_pdf.php';
        $html = ob_get_clean();
        streamPdf($html, 'laporan_kas_' . date('Ymd_His'));
    }

    public function exportReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        $this->activityLog->log(currentUserId(), 'cash', 'export', 'Export Excel Laporan Kas (' . $meta['period'] . ')');
        streamCashReportExcel(
            $ledger,
            $meta['company'],
            $meta['period'],
            'laporan_kas_' . date('Ymd_His'),
            $meta['title']
        );
    }

    /**
     * "Tarik Semua" -- semua baris sesuai filter aktif, dikelompokkan per PIC
     * dengan subtotal masuk/keluar tiap kelompok + Grand Total di akhir.
     * Fitur BARU (belum ada sebelumnya) -- terpisah dari "Cetak Terpilih"
     * (printVoucher, per-baris manual via checkbox).
     */
    public function printReportGrouped(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        $this->activityLog->log(currentUserId(), 'cash', 'print', 'Cetak PDF Laporan Kas (Tarik Semua, dikelompokkan per PIC) (' . $meta['period'] . ')');

        // reportLedger() mengembalikan 1 baris per ITEM (trx_id/pic hanya terisi
        // di baris pertama tiap transaksi). Tarik Semua tampilkan 1 baris per
        // TRANSAKSI (gabung uraian antar item), dikelompokkan per PIC.
        $groups = [];
        $currentPic = null;
        $currentKey = null;
        foreach ($ledger['rows'] as $row) {
            if ((int) $row['trx_id'] > 0) {
                $currentPic = $row['pic'] ?: '(Tanpa PIC)';
                if (!isset($groups[$currentPic])) {
                    $groups[$currentPic] = ['pic' => $currentPic, 'rows' => [], 'masuk' => 0.0, 'keluar' => 0.0];
                }
                $groups[$currentPic]['rows'][] = $row;
                $groups[$currentPic]['masuk']  += $row['masuk'];
                $groups[$currentPic]['keluar'] += $row['keluar'];
                $currentKey = count($groups[$currentPic]['rows']) - 1;
            } elseif ($currentPic !== null && $currentKey !== null) {
                // baris item ke-2/3/dst dari transaksi yang sama -> gabungkan ke
                // baris header-nya (Tarik Semua 1 baris per transaksi, bukan per item).
                $groups[$currentPic]['rows'][$currentKey]['uraian'] .= '; ' . $row['uraian'];
            }
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        $grandMasuk = array_sum(array_column($groups, 'masuk'));
        $grandKeluar = array_sum(array_column($groups, 'keluar'));

        ob_start();
        $company = $meta['company'];
        $periodText = $meta['period'];
        $reportTitle = $meta['title'];
        require ROOT_PATH . '/app/views/cash/_report_grouped_pdf.php';
        $html = ob_get_clean();
        streamPdf($html, 'laporan_kas_tarik_semua_' . date('Ymd_His'));
    }

    /**
     * CETAK TERPILIH. Terima daftar id transaksi Kas dari halaman Kas /
     * Laporan Kas, tampilkan halaman PRATINJAU (SATU voucher BUKTI KAS
     * KELUAR/MASUK per No Bukti, mengikuti Gambar 1) di dalam layout aplikasi
     * -- persis pola "Cetak Purchase Order": pengguna melihat dulu, lalu
     * menekan tombol "Cetak" (window.print()).
     *
     * Scoping tetap dihormati: transaksi di luar cakupan PIC / divisi user
     * di-skip diam-diam (bukan 403 seluruh cetakan).
     */
    public function printVoucher(): void
    {
        // Cetak voucher Kas terpilih -- KHUSUS Super Admin & Accounting
        // (config/permissions.php: cash.print_voucher). Ditegakkan backend
        // di sini, bukan cuma disembunyikan di view.
        Middleware::requirePermission('cash', 'print_voucher');

        // Baca id: ?ids=1,2,3  atau  ?ids[]=1&ids[]=2
        $raw = $_GET['ids'] ?? '';
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

        $scope    = $this->scopePics();
        $divScope = kasDivisionScope();
        $divOk    = $divScope === null ? null : array_flip($divScope);

        $vouchers = [];
        foreach ($ids as $id) {
            $header = $this->cashModel->findWithRelations($id);
            if (!$header) {
                continue;
            }
            if ($scope !== null && !in_array($header['pic'], $scope, true)) {
                continue;
            }
            if ($divOk !== null && !isset($divOk[$header['division']])) {
                continue;
            }
            $vouchers[] = [
                'header' => $header,
                'items'  => $this->itemModel->byTransaction($id),
            ];
        }

        if (empty($vouchers)) {
            setFlash('error', 'Silakan pilih minimal satu transaksi Kas yang valid untuk dicetak.');
            $this->redirect('cash', 'index');
        }

        $this->activityLog->log(
            currentUserId(),
            'cash',
            'print',
            'Pratinjau/cetak voucher Kas terpilih: ' . implode(', ', array_map(fn($v) => $v['header']['no_bukti'], $vouchers))
        );

        // "Kembali" mengikuti asal (Laporan Kas vs daftar Kas).
        $from    = ($_GET['from'] ?? '') === 'report' ? 'report' : 'index';
        $backUrl = $from === 'report'
            ? BASE_URL . '/index.php?module=cash&action=report'
            : BASE_URL . '/cash';

        $this->view('cash/voucher_preview', [
            'pageTitle' => 'Cetak Voucher Kas',
            'vouchers'  => $vouchers,
            'vCompany'  => ((new SystemSetting())->getGroup('company')['company_name']) ?: 'Perusahaan',
            'backUrl'   => $backUrl,
        ]);
    }

    // ===================== CREATE =====================

    /** Daftar Project untuk dropdown form Kas -- role gerbang Project dikunci ke project sesi. */
    private function formProjectOptions(): array
    {
        if (kasIsProjectGateRole(currentUserRole())) {
            $p = $this->scopeProjectId() ? $this->projectModel->find($this->scopeProjectId()) : null;
            return $p ? [$p] : [];
        }
        return $this->projectModel->activeList();
    }

    private function gatedProjectInfo(): ?array
    {
        if (!kasIsProjectGateRole(currentUserRole())) {
            return null;
        }
        $pid = $this->scopeProjectId();
        return $pid ? $this->projectModel->find($pid) : null;
    }

    public function create(): void
    {
        Middleware::requirePermission('cash', 'create');

        $picOptions = $this->picFieldOptions();
        // Untuk role ber-PIC tunggal (sesi Kas) nomor bisa dipratinjau langsung.
        // Role lihat-semua: kosong dulu, diisi via AJAX saat PIC dipilih.
        $previewPic = count($picOptions) === 1 ? $picOptions[0] : '';

        $this->view('cash/form', [
            'pageTitle'      => 'Tambah Kas',
            'mode'           => 'create',
            'cash'           => null,
            'items'          => [],
            'categories'     => $this->categoryModel->activeList(),
            'picOptions'     => $picOptions,
            'projects'       => $this->formProjectOptions(),
            'units'          => $this->unitModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->barangCategoryModel->activeList(),
            'rekeningOptions' => $this->rekeningModel->activeList(),
            'kasProjectGated' => kasIsProjectGateRole(currentUserRole()),
            'kasGatedProject' => $this->gatedProjectInfo(),
            'noBuktiPreview' => $previewPic !== '' ? $this->previewNoBuktiFor($previewPic) : '',
        ]);
    }

    /**
     * Pratinjau No Bukti untuk sebuah nama PIC. String kosong kalau PIC
     * belum punya Prefix Kas (form akan menampilkan peringatan).
     */
    private function previewNoBuktiFor(string $picName): string
    {
        $prefix = $this->picModel->prefixForPicName($picName);
        if (!$prefix || !CashNumber::isValidPrefix($prefix)) {
            return '';
        }
        return (new CashNumber())->preview($prefix);
    }

    /** AJAX: pratinjau No Bukti saat PIC dipilih di form Tambah Kas. */
    public function previewNoBukti(): void
    {
        Middleware::requirePermission('cash', 'create');
        $picName = trim($_GET['pic'] ?? '');
        $scope = $this->scopePics();
        if ($picName === '' || ($scope !== null && !in_array($picName, $scope, true))) {
            $this->json(['preview' => '', 'error' => 'PIC tidak valid.']);
        }
        $prefix = $this->picModel->prefixForPicName($picName);
        if (!$prefix) {
            $this->json(['preview' => '', 'error' => "PIC '{$picName}' belum memiliki Prefix Kas. Hubungi Super Admin (Master Data → PIC Kas)."]);
        }
        $this->json(['preview' => (new CashNumber())->preview($prefix), 'prefix' => $prefix]);
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

        // Prefix Kas WAJIB ada di PIC terpilih (server yang menentukan, bukan
        // input user). validateInput() sudah mengecek ini, tapi ambil ulang di
        // sini sebagai sumber kebenaran untuk generate nomor.
        $prefix = $this->picModel->prefixForPicName($data['pic']);
        if (!$prefix) {
            setFlash('error', "PIC '{$data['pic']}' belum memiliki Prefix Kas. Minta Super Admin mengaturnya di Master Data → PIC Kas.");
            $this->redirect('cash', 'create');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // No Bukti dibuat SERVER-SIDE & ATOMIC di dalam transaction ini
            // (CashNumber::next ikut transaction lewat SELECT ... FOR UPDATE).
            // Nilai apa pun yang dikirim client diabaikan.
            $noBukti = (new CashNumber())->next($prefix);
            // Sabuk pengaman ekstra: kalau entah bagaimana nomor sudah terpakai
            // (mis. data lama free-text kebetulan sama), naikkan sampai bebas.
            $guard = 0;
            while ($this->cashModel->noBuktiExists($noBukti) && $guard++ < 50) {
                $noBukti = (new CashNumber())->next($prefix);
            }

            $resolvedProjectId = $this->resolveProjectId($data['project_id']);

            $trxId = $this->cashModel->create([
                'trx_date'      => $data['trx_date'],
                'pic'           => $data['pic'],
                'division'      => $this->resolveDivision($data['pic']),
                'project_id'    => $resolvedProjectId,
                'rekening_id'   => $data['rekening_id'],
                'no_bukti'      => $noBukti,
                'mutasi'        => $data['mutasi'],
                'total_amount'  => $this->sumItems($items),
                'created_by'    => currentUserId(),
            ]);
            $this->saveItems($trxId, $items, $resolvedProjectId);

            $n = $this->cashModel->applyStockCredit($trxId);
            $stockNote = $n > 0 ? " ({$n} baris menambah stok)" : '';

            $this->activityLog->log(
                currentUserId(),
                'cash',
                'create',
                "Kas {$data['mutasi']} '{$noBukti}' (PIC {$data['pic']}) dibuat otomatis [prefix {$prefix}], "
                    . count($items) . ' item, total ' . formatRupiah($this->sumItems($items)) . $stockNote
            );

            $pdo->commit();

            // Push notification (best-effort -- kegagalan kirim TIDAK BOLEH menggagalkan
            // transaksi Kas yang sudah sukses tersimpan, makanya di luar & sesudah commit).
            try {
                if ((new SystemSetting())->getBool('notify_cash_validation', true)) {
                    sendPushToKasValidators(
                        $this->resolveDivision($data['pic']),
                        'Validasi Kas Menunggu',
                        "Kas {$data['mutasi']} '{$noBukti}' (PIC {$data['pic']}) menunggu validasi Anda.",
                        route('cash_validation')
                    );
                }
            } catch (Throwable $e) {
                error_log('Push cash_validation gagal: ' . $e->getMessage());
            }

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
        $this->assertValidationAllowsChange($row, 'diedit');
        $this->activityLog->log(currentUserId(), 'cash', 'view', "Membuka transaksi Kas #{$id} ('{$row['no_bukti']}')");

        $this->view('cash/form', [
            'pageTitle'      => 'Edit Kas',
            'mode'           => 'edit',
            'cash'           => $row,
            'items'          => $this->itemModel->byTransaction($id),
            'categories'     => $this->categoryModel->activeList(),
            'picOptions'     => $this->picFieldOptions($row['pic']),
            'projects'       => $this->formProjectOptions(),
            'units'          => $this->unitModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->barangCategoryModel->activeList(),
            'rekeningOptions' => $this->rekeningModel->activeList(),
            'kasProjectGated' => kasIsProjectGateRole(currentUserRole()),
            'kasGatedProject' => $this->gatedProjectInfo(),
            'noBuktiPreview' => $row['no_bukti'],
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
        $this->assertValidationAllowsChange($existing, 'diedit');

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

            $resolvedProjectId = $this->resolveProjectId($data['project_id']);

            // No Bukti TIDAK pernah dibuat ulang saat edit -- nomor resmi hanya
            // lahir sekali di store(). Pertahankan nilai lama apa adanya.
            $this->cashModel->updateById($id, [
                'trx_date'      => $data['trx_date'],
                'pic'           => $data['pic'],
                'division'      => $this->resolveDivision($data['pic']),
                'project_id'    => $resolvedProjectId,
                'rekening_id'   => $data['rekening_id'],
                'no_bukti'      => $existing['no_bukti'],
                'mutasi'        => $data['mutasi'],
                'total_amount'  => $this->sumItems($items),
            ]);
            $this->itemModel->deleteByTransaction($id);
            $this->saveItems($id, $items, $resolvedProjectId);

            $n = $this->cashModel->applyStockCredit($id);

            // Transaksi yang tadinya DITOLAK, setelah diperbaiki masuk antrean validasi lagi.
            if (($existing['validation_status'] ?? '') === 'ditolak') {
                $this->cashModel->resetValidationToPending($id);
            }

            $this->activityLog->log(currentUserId(), 'cash', 'update', "Kas #{$id} ('{$existing['no_bukti']}') diperbarui"
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
        $this->assertValidationAllowsChange($row, 'dihapus');
        assertPeriodOpen('cash', $row['trx_date'], 'cash', 'index');

        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true ? 'Transaksi Kas dipindahkan ke Tempat Sampah.' : 'Gagal menghapus transaksi Kas.');
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
        // Filter Project: role gerbang Project TIDAK boleh mengganti lewat query
        // string (dipaksa ke project sesi-nya di buildReportData()/report()/index()
        // lewat $projScope terpisah) -- di sini sekadar dibaca untuk role lain.
        return [
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'pic'         => trim($_GET['pic'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
            'mutasi'      => $_GET['mutasi'] ?? '',
            'keyword'     => trim($_GET['keyword'] ?? ''),
            'project_id'  => !kasIsProjectGateRole(currentUserRole()) ? ($_GET['project_id'] ?? '') : '',
        ];
    }

    /**
     * Judul Laporan Kas dinamis mengikuti filter Project -- TIDAK PERNAH
     * hard-code nama project. "Semua Project" kalau filter kosong/gerbang
     * Project tidak aktif untuk 1 project spesifik.
     */
    private function reportTitle(array $filters, ?int $projectScope): string
    {
        $pid = $projectScope ?? (!empty($filters['project_id']) ? (int) $filters['project_id'] : null);
        if ($pid) {
            $p = $this->projectModel->find($pid);
            if ($p) {
                return 'LAPORAN KAS ' . mb_strtoupper($p['project_name']);
            }
        }
        return 'LAPORAN KAS — SEMUA PROJECT';
    }

    /** [$ledger, ['company'=>.., 'period'=>.., 'title'=>.., 'projectId'=>.., 'projectName'=>..]] untuk PDF/Excel laporan. */
    private function buildReportData(): array
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $divScope = kasDivisionScope();
        $projScope = $this->scopeProjectId();
        $saldoAwal = $this->cashModel->saldoAwal($filters, $scope, $divScope, $projScope);
        $ledger  = $this->cashModel->reportLedger($filters, $scope, $saldoAwal, $divScope, $projScope);

        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $period = 'Periode : '
            . (!empty($filters['date_from']) ? formatTanggal($filters['date_from']) : 'Awal')
            . ' - '
            . (!empty($filters['date_to']) ? formatTanggal($filters['date_to']) : 'Sekarang');

        return [$ledger, [
            'company' => $companyName,
            'period'  => $period,
            'title'   => $this->reportTitle($filters, $projScope),
        ]];
    }

    /**
     * null = lihat semua PIC (super_admin/accounting/project_manager, DAN
     * role gerbang Project -- role itu di-scope lewat scopeProjectId() di
     * bawah, bukan lagi lewat nama PIC).
     * array = tepat 1 nama PIC (gerbang PIC lama, dipertahankan untuk
     * kompatibilitas -- tidak ada role yang mencapai baris ini saat ini).
     */
    private function scopePics(): ?array
    {
        if (kasIsProjectGateRole(currentUserRole())) {
            return null;
        }
        return kasScopePicNames();
    }

    /**
     * null = tidak dibatasi lewat gerbang Project (super_admin/accounting/
     * project_manager/gerbang PIC lama).
     * int  = HANYA transaksi Kas project ini (purchase/pic_project/admin_project,
     * project yang barusan diverifikasi lewat kasProjectAuthenticate()).
     */
    private function scopeProjectId(): ?int
    {
        return kasProjectScopeId();
    }

    /**
     * Opsi dropdown filter PIC untuk role "lihat semua" -- gabungan PIC yang
     * sudah punya transaksi Kas (dalam cakupan divisi) + semua PIC master yang
     * divisinya boleh dilihat user ini. Unik & terurut.
     */
    private function mergedPicOptions(?array $divScope): array
    {
        $opts = array_merge(
            $this->cashModel->distinctPics(null, $divScope),
            $this->picModel->picNamesForDivisions($divScope)
        );
        $opts = array_values(array_unique(array_filter($opts, fn($v) => $v !== null && $v !== '')));
        sort($opts);
        return $opts;
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

    /** Pilihan dropdown PIC di form. Role gerbang Project: semua nama PIC
     *  terdaftar milik akun ini (dari user_pic_assignments -- dipakai untuk
     *  atribusi & prefix No Bukti, independen dari gerbang Project di atas).
     *  Role gerbang PIC lama: dikunci ke PIC sesi Kas. Role lihat-semua:
     *  semua PIC di master mapping. $current dipertahankan supaya data lama
     *  tetap terpilih walau tidak lagi di daftar. */
    private function picFieldOptions(?string $current = null): array
    {
        if (kasIsProjectGateRole(currentUserRole())) {
            $opts = $this->picModel->picNamesForUser((int) currentUserId());
            if ($current !== null && $current !== '' && !in_array($current, $opts, true)) {
                $opts[] = $current;
            }
            sort($opts);
            return $opts;
        }
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
        if (kasIsProjectGateRole(currentUserRole())) {
            $pid = $this->scopeProjectId();
            if ($pid === null || (int) ($row['project_id'] ?? 0) !== $pid) {
                denyAccess('Percobaan akses transaksi Kas di luar Project yang sedang dibuka');
            }
            return;
        }
        $scope = $this->scopePics();
        if ($scope !== null && !in_array($row['pic'], $scope, true)) {
            denyAccess('Percobaan akses transaksi Kas milik PIC lain');
        }
    }

    /**
     * Transaksi Kas yang SUDAH 'tervalidasi' terkunci -- hanya Super Admin yang
     * boleh mengubah/menghapus. 'menunggu' & 'ditolak' bebas diedit pembuatnya
     * (mengedit yang 'ditolak' akan mengembalikan statusnya ke 'menunggu').
     */
    private function assertValidationAllowsChange(array $row, string $verb): void
    {
        if (($row['validation_status'] ?? 'menunggu') === 'tervalidasi'
            && currentUserRole() !== ROLE_SUPER_ADMIN) {
            denyAccess("Transaksi Kas '{$row['no_bukti']}' sudah divalidasi -- tidak bisa {$verb}. Hubungi Super Admin bila perlu koreksi.");
        }
    }

    private function collectInput(): array
    {
        // No Bukti SENGAJA tidak dibaca dari POST -- server yang menentukan
        // (CashNumber::next di store(); nilai lama dipertahankan di update()).
        return [
            'trx_date' => trim($_POST['trx_date'] ?? ''),
            'pic'      => trim($_POST['pic'] ?? ''),
            'mutasi'   => ($_POST['mutasi'] ?? '') === 'masuk' ? 'masuk'
                : (($_POST['mutasi'] ?? '') === 'keluar' ? 'keluar' : ''),
            // Project header: untuk role gerbang Project NILAI INI DIABAIKAN saat
            // simpan (dipaksa dari session -- lihat resolveProjectId()), dibaca di
            // sini hanya supaya lolos ke form validasi kalau perlu ditampilkan ulang.
            'project_id'  => !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null,
            'rekening_id' => !empty($_POST['rekening_id']) ? (int) $_POST['rekening_id'] : null,
        ];
    }

    /** Project header final yang disimpan -- dipaksa dari sesi gerbang Project, atau input bebas untuk role lain. */
    private function resolveProjectId(?int $posted): ?int
    {
        if (kasIsProjectGateRole(currentUserRole())) {
            return $this->scopeProjectId();
        }
        return $posted;
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
        // Role gerbang Project: baris tanpa Project eksplisit ikut project sesi
        // Kas (form mengunci/menyembunyikan pilihan ini -- lihat cash/form.php).
        $gatedProjectId = kasIsProjectGateRole(currentUserRole()) ? $this->scopeProjectId() : null;
        $out = [];
        for ($i = 0; $i < count($uraian); $i++) {
            $u   = trim((string) ($uraian[$i] ?? ''));
            $q   = (float) ($qty[$i] ?? 0);
            $s   = parseCurrencyInput($satuan[$i] ?? 0);
            $cid = !empty($catIds[$i]) ? (int) $catIds[$i] : null;
            // Harga satuan BOLEH negatif (koreksi/refund Kas). Baris dianggap
            // kosong hanya bila benar-benar tidak ada isi -- harga satuan yang
            // negatif (mis. -5000) tidak lagi dianggap "kosong" & tidak dibuang
            // diam-diam; kalau uraian/kategori belum diisi, validasi yang menegur.
            if ($u === '' && $q <= 0 && abs($s) < 0.005 && $cid === null) {
                continue; // baris kosong -> abaikan
            }
            $out[] = [
                'uraian'           => $u,
                'qty'              => $q,
                'satuan'           => $s,
                'jumlah'           => round($q * $s, 2),
                'cash_category_id' => $cid,
                'item_id'          => !empty($barangIds[$i]) ? (int) $barangIds[$i] : null,
                'project_id'       => !empty($projects[$i]) ? (int) $projects[$i] : $gatedProjectId,
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

    /**
     * $headerProjectId: project header transaksi ini (hasil resolveProjectId()).
     * Untuk role gerbang Project, project TIAP BARIS dipaksa ikut header --
     * jaminan server-side supaya baris tidak bisa "menyelundup" ke project
     * lain walau field per-baris dimanipulasi lewat request.
     */
    private function saveItems(int $trxId, array $items, ?int $headerProjectId = null): void
    {
        $forceProject = kasIsProjectGateRole(currentUserRole());
        foreach ($items as $it) {
            // Kolom stok (satuan / project / supplier) hanya relevan untuk baris
            // ber-kategori stok. Baris non-stok (mis. Biaya Operasional) disimpan
            // bersih tanpa kolom-kolom itu.
            $cat     = $it['cash_category_id'] ? ($this->categoryModel->find((int) $it['cash_category_id']) ?: null) : null;
            $isStock = $cat && (int) $cat['affects_stock'] === 1;
            $isProyek = $isStock && ($cat['stock_scope'] ?? null) === 'proyek';
            // Project disimpan untuk baris ber-scope 'proyek' (wajib) DAN untuk
            // baris kategori non-stok / "Biaya Operasional" (opsional) -- biaya
            // bisa dibebankan ke project. Baris stok non-proyek (Inventory
            // Kantor) tetap tanpa project.
            $keepProject = $isProyek || ($cat && (int) $cat['affects_stock'] === 0);
            $itemProjectId = $forceProject ? $headerProjectId : ($it['project_id'] ?? null);
            $this->itemModel->create([
                'cash_transaction_id' => $trxId,
                'cash_category_id'    => $it['cash_category_id'] ?? null,
                'item_id'            => $isStock ? ($it['item_id'] ?? null) : null,
                'project_id'          => $keepProject ? $itemProjectId : null,
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
        $picOwnershipOk = true;
        if ($d['pic'] === '') {
            $errors[] = 'PIC wajib diisi.';
            $picOwnershipOk = false;
        } elseif (kasIsProjectGateRole(currentUserRole())) {
            // Gerbang Project: ownership PIC dicek lewat user_pic_assignments
            // (bukan session PIC -- role ini tidak lagi login per-PIC).
            if (!in_array($d['pic'], $this->picModel->picNamesForUser((int) currentUserId()), true)) {
                $errors[] = 'PIC tidak valid untuk akun Anda.';
                $picOwnershipOk = false;
            }
        } elseif ($scope !== null && !in_array($d['pic'], $scope, true)) {
            $errors[] = 'PIC tidak valid untuk akun Anda.';
            $picOwnershipOk = false;
        }
        if ($picOwnershipOk && $excludeId === null && !$this->picModel->prefixForPicName($d['pic'])) {
            // Hanya relevan saat BUAT baru -- No Bukti otomatis butuh Prefix Kas
            // di PIC. Saat edit, no_bukti lama dipertahankan (tidak butuh prefix).
            $errors[] = "PIC '{$d['pic']}' belum memiliki Prefix Kas. Minta Super Admin mengaturnya di Master Data → PIC Kas sebelum membuat transaksi.";
        }

        if ($d['mutasi'] === '') {
            $errors[] = 'Mutasi wajib dipilih (Masuk / Keluar).';
        }

        if (!empty($d['rekening_id']) && !$this->rekeningModel->find((int) $d['rekening_id'])) {
            $errors[] = 'Rekening yang dipilih tidak valid.';
        }
        if (!kasIsProjectGateRole(currentUserRole()) && !empty($d['project_id']) && !$this->projectModel->find((int) $d['project_id'])) {
            $errors[] = 'Project yang dipilih tidak valid.';
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
                // Harga satuan boleh negatif (koreksi/refund) -- tidak ada batas
                // bawah. Hanya nilai yang benar-benar tak masuk akal ditolak.
                if (abs((float) $it['satuan']) > 1.0e13) {
                    $errors[] = "Baris {$n}: Harga satuan di luar batas wajar.";
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
