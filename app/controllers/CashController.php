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
require_once ROOT_PATH . '/app/models/BankTransaction.php';
require_once ROOT_PATH . '/app/models/MasterBank.php';

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
    private BankTransaction $bankModel;
    private MasterBank $masterBankModel;

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
        $this->bankModel           = new BankTransaction();
        $this->masterBankModel     = new MasterBank();

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
            // Sekadar info di layar login (BUKAN pilihan -- revisi 23 Sep 2026:
            // password dulu, Project jadi filter SETELAH masuk Kas).
            'hasAnyProjectAccess' => count($this->projectAccessModel->projectsForUser((int) currentUserId())) > 0,
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

        $uid      = (int) currentUserId();
        $password = (string) ($_POST['password'] ?? '');
        $user     = (new User())->find($uid);

        // Revisi 23 Sep 2026: gerbang HANYA password akun sendiri -- Project
        // TIDAK LAGI dipilih di sini (jadi filter setelah masuk, lihat
        // scopeAccessScope()). Tidak ada lagi validasi ownership project di
        // titik ini karena tidak ada project yang dipilih.
        if (!$user || !password_verify($password, (string) $user['password'])) {
            kasProjectRegisterFailedLogin();
            $this->activityLog->log($uid, 'cash', 'kas_login_failed', 'Verifikasi Kas GAGAL (password salah)');
            setFlash('error', 'Password salah.' . (kasProjectFailsRemaining() > 0 ? ' Sisa percobaan: ' . kasProjectFailsRemaining() . '.' : ''));
            $this->redirect('cash', 'kasProjectLogin');
        }

        kasProjectClearFailedLogin();
        session_regenerate_id(true);
        $_SESSION['kas_project_auth'] = [
            'ok'            => true,
            'account_id'    => $uid,
            'login_time'    => time(),
            'last_activity' => time(),
        ];
        $this->activityLog->log($uid, 'cash', 'kas_login', 'Verifikasi Kas BERHASIL');
        setFlash('success', 'Verifikasi berhasil. Anda masuk ke Kas.');
        $this->redirect('cash', 'index');
    }

    public function kasProjectLogout(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();
        unset($_SESSION['kas_project_auth']);
        $this->activityLog->log((int) currentUserId(), 'cash', 'kas_logout', 'Keluar sesi Kas');
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
        $projScope = $this->scopeAccessScope();
        $kasRows  = $this->cashModel->listFiltered($filters, $scope, $divScope, $projScope);

        // Ringkasan "Total Kas Masuk/Keluar (filter)" HARUS murni Kas -- Revisi
        // lanjutan poin 9-11: uang Kas & Bank tidak pernah digabung jadi satu
        // angka, jadi dihitung SEBELUM baris Bank digabung ke tabel di bawah.
        $summary = ['masuk' => 0.0, 'keluar' => 0.0];
        foreach ($kasRows as $r) {
            $summary[$r['mutasi']] += (float) $r['total_amount'];
        }

        // Gabung Bank (Revisi lanjutan: menu Bank berdiri sendiri dihapus) --
        // no-op kalau user tidak punya akses 'bank.view'. HANYA untuk tampilan
        // tabel gabungan -- TIDAK ikut $summary di atas.
        $rows = $this->combinedListRows($kasRows, $filters);

        // Kartu saldo (cash.view_balance):
        //   Super Admin / Accounting -> SEMUA divisi + Total Saldo.
        //   Purchase / PIC Project / Admin Project / Project Manager -> HANYA
        //   saldo divisi mereka sendiri (purchase -> Saldo Kas Purchase, role
        //   project -> Saldo Kas Project), tanpa Total & tanpa divisi lain.
        //   Role gerbang Project (pic_project/admin_project) juga dibatasi ke
        //   $projScope (project yang diberikan akses) supaya saldo konsisten
        //   dengan daftar transaksi di bawahnya -- tidak akan ada saldo non-nol
        //   tanpa transaksi yang terlihat. Purchase TIDAK ikut dibatasi ke
        //   project_ids karena bucket divisinya sendiri ('purchase') sudah
        //   membuat kondisi akses selalu terpenuhi (lihat kasOwnDivisionBucket()).
        //
        //   Revisi audit RBAC Kas per-project (2026-09-25): $scope (nama PIC,
        //   lihat scopePics()) SEKARANG juga diikutkan. Untuk Purchase $scope
        //   tetap null (saldo "Kas Purchase" company-wide, lintas-PIC, TIDAK
        //   berubah). Untuk pic_project/admin_project $scope kini terisi
        //   (nama sendiri / sendiri+PIC terkait) -- section 20 spek: saldo
        //   Admin = "Kas dirinya + Kas PIC terkait", BUKAN seluruh divisi
        //   'project' company-wide seperti sebelumnya.
        $balances = null;
        $balanceShowTotal = false;
        if (can('cash', 'view_balance')) {
            $role = currentUserRole();
            $balanceShowTotal = in_array($role, [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING], true);
            // Saldo divisi dihitung PENUH (semua PIC divisi itu) HANYA untuk
            // role yang memang tidak dibatasi $scope (mis. Purchase).
            $balDivScope = $balanceShowTotal ? null : [kasDivisionForRole($role)];
            $balScopePics = $balanceShowTotal ? null : $scope;

            // Revisi lanjutan poin 14 (awalnya HANYA Super Admin/Accounting):
            // saldo MENGIKUTI filter Project/Rekening yang sedang dipilih (jadi
            // breakdown per-divisi diganti satu angka sesuai filter).
            // Revisi audit RBAC Kas per-project (2026-09-25, spek poin 9 & 21
            // -- "filter diterapkan pada ... saldo"): diperluas ke SEMUA role
            // gerbang Project juga (Purchase/PIC Project/Admin Project), pakai
            // $balScopePics/$balDivScope yang sudah benar per role di atas,
            // supaya Admin yang mencentang 1 project dari beberapa project yang
            // dia kelola melihat saldo project itu saja, bukan gabungan semua.
            if (!empty($filters['project_ids']) || !empty($filters['rekening_ids'])) {
                $balances = [
                    'filtered' => true,
                    'total'    => $this->cashModel->balanceFiltered($filters, $balScopePics, $balDivScope, $projScope),
                ];
            } else {
                $balances = $this->cashModel->balanceByDivision($balScopePics, $balDivScope, $projScope);
            }
            $stamp = date('Y-m-d');
            if (($_SESSION['kas_balance_logged'] ?? '') !== $stamp) {
                $_SESSION['kas_balance_logged'] = $stamp;
                $this->activityLog->log((int) currentUserId(), 'cash', 'kas_view_balance', 'Melihat kartu saldo Kas');
            }
        }

        // Saldo Bank -- SELALU KARTU TERPISAH dari Saldo Kas (Revisi lanjutan
        // poin 9-12), tidak pernah digabung jadi satu angka. Gerbang backend:
        // can('bank','view') dicek DI SINI (bukan cuma disembunyikan di view)
        // supaya memanipulasi query string (mis. bank_ids/project_id) tidak
        // bisa membocorkan saldo Bank ke role yang tidak berhak (poin 13).
        $bankBalance = null;
        if (can('bank', 'view')) {
            $bankFilterActive = !empty($filters['project_ids']) || !empty($filters['bank_ids']) || !empty($filters['rekening_ids']);
            $bankBalance = $this->bankModel->balanceTotal($bankFilterActive ? $this->bankFiltersFrom($filters) : []);
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
            'bankBalance' => $bankBalance,
            'kasExempt'   => kasIsExemptRole(currentUserRole()),
            'kasPicName'  => kasIsExemptRole(currentUserRole()) ? null : kasPicName(),
            'kasProjectGated' => kasIsProjectGateRole(currentUserRole()),
            'projectOptions'  => $this->reportProjectOptions(),
            'bankOptions'     => $this->bankChecklistOptions(),
            'rekeningOptions' => $this->rekeningChecklistOptions(),
            'canBank'         => can('bank', 'view'),
        ]);
    }

    // ===================== LAPORAN KAS =====================

    /**
     * Project yang tersedia di filter Kas/Laporan Kas & di form Tambah/Edit Kas.
     * Role gerbang Project: HANYA project yang diberikan akses lewat Project >
     * Akses (revisi 23 Sep 2026 -- dulu terkunci ke 1 project sesi, sekarang
     * daftar, jadi user memfilter/memilih sendiri di antara project miliknya).
     * Role lain: semua project yang boleh dilihat (tidak dibatasi lagi --
     * Accounting/Super Admin/PM sudah scoped lewat divisi/permission modul).
     */
    private function reportProjectOptions(): array
    {
        if (kasIsProjectGateRole(currentUserRole())) {
            return $this->projectAccessModel->projectsForUser((int) currentUserId());
        }
        return $this->projectModel->activeList();
    }

    public function report(): void
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $divScope = kasDivisionScope();
        $projScope = $this->scopeAccessScope();
        // Gabung Bank (Revisi lanjutan) -- no-op tanpa akses 'bank.view'.
        $ledger  = $this->combinedLedger($filters, $scope, $divScope, $projScope);

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
            'bankOptions'    => $this->bankChecklistOptions(),
            'rekeningOptions' => $this->rekeningChecklistOptions(),
            'canBank'        => can('bank', 'view'),
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
     * Kelompokkan ledger gabungan (Kas [+Bank]) per PIC + subtotal, dipakai
     * bareng oleh printReportGrouped() (PDF) & exportReportGrouped() (Excel)
     * supaya isi keduanya SELALU sama persis (Revisi lanjutan poin 3).
     * Pakai is_first/parent_id/pic_full/project_name_full (bukan trx_id/pic
     * lama) supaya baris Bank ikut terkelompok benar (1 baris = 1 transaksi,
     * beda dari Kas yang bisa banyak baris/item per transaksi).
     */
    private function buildGroupedByPic(array $ledgerRows): array
    {
        $groups = [];
        $currentPic = null;
        $currentKey = null;
        foreach ($ledgerRows as $row) {
            if (!empty($row['is_first'])) {
                $currentPic = ($row['pic_full'] ?? '') !== '' ? $row['pic_full'] : '(Tanpa PIC)';
                if (!isset($groups[$currentPic])) {
                    $groups[$currentPic] = ['pic' => $currentPic, 'rows' => [], 'masuk' => 0.0, 'keluar' => 0.0];
                }
                $groups[$currentPic]['rows'][] = $row;
                $groups[$currentPic]['masuk']  += $row['masuk'];
                $groups[$currentPic]['keluar'] += $row['keluar'];
                $currentKey = count($groups[$currentPic]['rows']) - 1;
            } elseif ($currentPic !== null && $currentKey !== null) {
                // Baris item ke-2/3/dst Kas dari transaksi yang sama -> gabung ke
                // baris header-nya (Tarik Semua 1 baris per transaksi, bukan per item).
                $groups[$currentPic]['rows'][$currentKey]['uraian'] .= '; ' . $row['uraian'];
            }
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);
        return $groups;
    }

    /**
     * "Tarik Semua" -- semua baris sesuai filter aktif (Kas [+Bank]),
     * dikelompokkan per PIC dengan subtotal masuk/keluar tiap kelompok +
     * Grand Total di akhir. Terpisah dari "Cetak Terpilih" (printVoucher,
     * per-baris manual via checkbox, khusus Kas).
     */
    public function printReportGrouped(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        $this->activityLog->log(currentUserId(), 'cash', 'print', 'Cetak PDF Laporan Kas (Tarik Semua, dikelompokkan per PIC) (' . $meta['period'] . ')');

        $groups = $this->buildGroupedByPic($ledger['rows']);
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
     * "Tarik Semua" versi Excel -- isi & pengelompokan SAMA PERSIS dengan versi
     * PDF di atas (buildGroupedByPic() dipakai bersama), cuma beda format output.
     */
    public function exportReportGrouped(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        $this->activityLog->log(currentUserId(), 'cash', 'export', 'Export Excel Laporan Kas (Tarik Semua, dikelompokkan per PIC) (' . $meta['period'] . ')');

        $groups = $this->buildGroupedByPic($ledger['rows']);
        streamCashReportGroupedExcel(
            $groups,
            $meta['company'],
            $meta['period'],
            'laporan_kas_tarik_semua_' . date('Ymd_His'),
            $meta['title']
        );
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

    /**
     * Daftar Project untuk dropdown form Kas -- role gerbang Project HANYA
     * melihat project yang diberikan akses (revisi 23 Sep 2026, sama daftar
     * dengan reportProjectOptions()).
     */
    private function formProjectOptions(): array
    {
        return $this->reportProjectOptions();
    }

    /**
     * pic_project/admin_project WAJIB pilih Project (satu-satunya cara mereka
     * tetap bisa melihat transaksi yang baru dibuat -- scope mereka HANYA Kas
     * Project, tidak ada bucket lain). Purchase BOLEH kosong -- tanpa Project
     * otomatis masuk bucket "Kas Purchase" (division) yang selalu mereka
     * lihat.
     */
    private function isProjectRequiredForGatedRole(): bool
    {
        return kasIsProjectGateRole(currentUserRole()) && currentUserRole() !== ROLE_PURCHASE;
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
            'kasProjectRequired' => $this->isProjectRequiredForGatedRole(),
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
            'kasProjectRequired' => $this->isProjectRequiredForGatedRole(),
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
        // Batasi ke project yang diberikan akses untuk role gerbang Project --
        // sebelumnya activeList() penuh (bug lama, membocorkan nama project
        // lain lewat dropdown baris item walau tak bisa dipakai).
        $projects = $this->formProjectOptions();
        $itemCatalog = $this->barangModel->activeList();
        ob_start();
        require ROOT_PATH . '/app/views/cash/_item_row.php';
        $html = ob_get_clean();
        $this->json(['html' => $html]);
    }

    // ===================== Helper privat =====================

    private function collectFilters(): array
    {
        // Filter Project (checklist, revisi 23 Sep 2026): role gerbang Project
        // BOLEH memilih di antara project yang diberikan akses -- kosong =
        // semua (batas akses tetap ditegakkan lewat scopeAccessScope(), lihat
        // index()/report()/buildReportData()). ID di luar akses DITOLAK KERAS
        // (bukan cuma disaring diam-diam) supaya percobaan lewat URL/API kena
        // "Access Denied", bukan kebocoran senyap.
        $gated = kasIsProjectGateRole(currentUserRole());
        $allowedProjectIds = $gated ? kasProjectScopeIds() : null;

        $projectIds = [];
        if (isset($_GET['project_ids']) && is_array($_GET['project_ids'])) {
            $projectIds = array_values(array_unique(array_filter(array_map('intval', $_GET['project_ids']), fn($v) => $v > 0)));
        }
        $projectIdSingle = trim((string) ($_GET['project_id'] ?? ''));
        if ($gated) {
            $requested = $projectIds;
            if ($projectIdSingle !== '') {
                $requested[] = (int) $projectIdSingle;
            }
            foreach ($requested as $pid) {
                if (!in_array($pid, $allowedProjectIds, true)) {
                    denyAccess("Percobaan membuka Project di luar akses Kas Anda (project_id={$pid})");
                }
            }
        }
        $bankIds = [];
        if (can('bank', 'view') && isset($_GET['bank_ids']) && is_array($_GET['bank_ids'])) {
            $bankIds = array_values(array_unique(array_filter(array_map('intval', $_GET['bank_ids']), fn($v) => $v > 0)));
        }
        // Filter Rekening (Revisi lanjutan poin 5-8) -- berlaku untuk Kas MAUPUN
        // Bank, tidak digerbang permission Bank (rekening_id juga ada di Kas).
        $rekeningIds = [];
        if (isset($_GET['rekening_ids']) && is_array($_GET['rekening_ids'])) {
            $rekeningIds = array_values(array_unique(array_filter(array_map('intval', $_GET['rekening_ids']), fn($v) => $v > 0)));
        }
        // Filter Jenis (Revisi lanjutan: Cetak Laporan per sumber) -- 'kas' =
        // hanya transaksi Kas (No Bukti Kas, mis. AD-0001), 'bank' = hanya
        // transaksi Bank (No Bukti BK-0001), '' = gabungan (default, tidak
        // berubah dari sebelumnya). Dipakai combinedLedger() -- Laporan Kas
        // (report/PDF/Excel/Tarik Semua) SAJA, tidak menyentuh daftar Kas utama.
        $source = in_array($_GET['source'] ?? '', ['kas', 'bank'], true) ? $_GET['source'] : '';
        return [
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
            'pic'         => trim($_GET['pic'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
            'mutasi'      => $_GET['mutasi'] ?? '',
            'keyword'     => trim($_GET['keyword'] ?? ''),
            'project_id'  => $projectIdSingle, // lama, kompat link lama (divalidasi di atas utk role gerbang)
            'project_ids' => $projectIds, // baru, checklist (Revisi gabung Kas+Bank)
            'bank_ids'    => $bankIds,
            'rekening_ids' => $rekeningIds,
            'source'      => $source,
        ];
    }

    /** Bank checklist di filter Kas/Laporan Kas -- hanya kalau user boleh lihat Bank. */
    private function bankChecklistOptions(): array
    {
        return can('bank', 'view') ? $this->masterBankModel->activeList() : [];
    }

    /**
     * Rekening checklist di filter Kas/Laporan Kas -- sumber Master Rekening
     * (Revisi lanjutan poin 5-6), hanya rekening AKTIF (transaksi lama yang
     * rekeningnya sudah nonaktif tetap tampil di data, cuma tidak muncul lagi
     * sebagai pilihan filter baru).
     */
    private function rekeningChecklistOptions(): array
    {
        return $this->rekeningModel->activeList();
    }

    /**
     * Terjemahkan filter Kas ke filter yang dipahami BankTransaction model.
     * SEMUA filter yang berlaku untuk Bank (termasuk pic & rekening_ids) HARUS
     * diteruskan di sini -- ini satu-satunya titik dipakai bareng oleh tabel
     * (combinedListRows), laporan/PDF/Excel/Tarik Semua (combinedLedger), jadi
     * hasilnya otomatis konsisten di semua tempat (Revisi lanjutan poin 23).
     */
    private function bankFiltersFrom(array $filters): array
    {
        return [
            'date_from'    => $filters['date_from'],
            'date_to'      => $filters['date_to'],
            'pic'          => $filters['pic'],
            'project_ids'  => $filters['project_ids'],
            'bank_ids'     => $filters['bank_ids'],
            'rekening_ids' => $filters['rekening_ids'],
            'mutasi'       => $filters['mutasi'],
            'keyword'      => $filters['keyword'],
        ];
    }

    /**
     * Gabungkan baris Kas + Bank (list, BUKAN buku/ledger) untuk halaman Kas
     * (Revisi lanjutan: menu Bank berdiri sendiri dihapus, digabung ke sini).
     * Tanpa akses 'bank.view' -> hanya baris Kas (tidak berubah dari sebelumnya).
     */
    private function combinedListRows(array $kasRows, array $filters): array
    {
        foreach ($kasRows as &$r) {
            $r['source'] = 'kas';
        }
        unset($r);
        if (!can('bank', 'view')) {
            return $kasRows;
        }
        $bankRows = $this->bankModel->listFiltered($this->bankFiltersFrom($filters));
        foreach ($bankRows as &$b) {
            $b['source']            = 'bank';
            $b['pic']                = $b['pic'] ?? '';
            $b['category_name']      = $b['bank_name'] . ' (' . mb_strtoupper($b['bank_jenis']) . ')';
            $b['total_amount']       = $b['amount'];
            $b['validation_status']  = null;
        }
        unset($b);
        $merged = array_merge($kasRows, $bankRows);
        usort($merged, static function ($a, $b) {
            $c = strcmp((string) $b['trx_date'], (string) $a['trx_date']); // DESC
            if ($c !== 0) {
                return $c;
            }
            return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        });
        return $merged;
    }

    /**
     * Gabungkan buku/ledger Kas + Bank (Laporan Kas) dengan saldo berjalan
     * GABUNGAN -- dipakai report()/PDF/Excel/Tarik Semua. Tanpa akses
     * 'bank.view' hasilnya identik dgn ledger Kas biasa (no-op).
     *
     * $filters['source'] (Revisi lanjutan: Cetak per sumber) -- 'kas' = HANYA
     * transaksi Kas (No Bukti Kas), 'bank' = HANYA transaksi Bank (No Bukti
     * BK-xxxx), '' = gabungan seperti semula. Satu-satunya titik ini dipakai
     * bareng oleh tabel Laporan, PDF, Excel, dan Tarik Semua, jadi hasilnya
     * otomatis konsisten di semua tempat.
     */
    private function combinedLedger(array $filters, ?array $scope, ?array $divScope, ?array $projScope): array
    {
        $source = $filters['source'] ?? '';
        $includeKas = $source !== 'bank';
        $includeBank = $source !== 'kas' && can('bank', 'view');

        $kasSaldoAwal = $includeKas ? $this->cashModel->saldoAwal($filters, $scope, $divScope, $projScope) : 0.0;
        $kasLedger = $includeKas
            ? $this->cashModel->reportLedger($filters, $scope, $kasSaldoAwal, $divScope, $projScope)
            : ['saldo_awal' => 0.0, 'saldo_akhir' => 0.0, 'rows' => []];

        if (!$includeBank) {
            return $kasLedger;
        }

        $bankFilters = $this->bankFiltersFrom($filters);
        $bankSaldoAwal = $this->bankModel->saldoAwal($bankFilters);
        $bankLedger = $this->bankModel->reportLedger($bankFilters, $bankSaldoAwal);

        $bankRows = [];
        foreach ($bankLedger['rows'] as $b) {
            $bankRows[] = [
                'source'            => 'bank',
                'parent_id'         => (int) $b['id'],
                'is_first'          => true,
                'trx_date_full'     => $b['trx_date'],
                'no_bukti_full'     => $b['no_bukti'],
                'pic_full'          => $b['pic'] ?? '',
                'project_name_full' => $b['project_name'] ?? '',
                'kategori'          => $b['bank_name'] . ' (' . mb_strtoupper($b['bank_jenis']) . ')',
                'uraian'            => $b['uraian'],
                'qty'               => 0.0,
                'satuan'            => 0.0,
                'masuk'             => $b['masuk'],
                'keluar'            => $b['keluar'],
                'saldo'             => 0.0, // dihitung ulang gabungan di bawah
            ];
        }

        $merged = array_merge($kasLedger['rows'], $bankRows);
        usort($merged, static function ($a, $b) {
            $c = strcmp((string) $a['trx_date_full'], (string) $b['trx_date_full']); // ASC
            if ($c !== 0) {
                return $c;
            }
            return ($a['parent_id'] ?? 0) <=> ($b['parent_id'] ?? 0);
        });

        $saldo = $kasSaldoAwal + $bankSaldoAwal;
        foreach ($merged as &$row) {
            $saldo += (float) $row['masuk'] - (float) $row['keluar'];
            $row['saldo'] = $saldo;
        }
        unset($row);

        return ['saldo_awal' => $kasSaldoAwal + $bankSaldoAwal, 'saldo_akhir' => $saldo, 'rows' => $merged];
    }

    /**
     * Judul Laporan dinamis mengikuti filter Project -- TIDAK PERNAH hard-code
     * nama project. Tepat 1 project dipilih di checklist -> nama project itu;
     * 0 atau 2+ dipilih -> "SEMUA PROJECT" (revisi 23 Sep 2026: gerbang akses
     * sekarang bisa berisi banyak project + bucket divisi Purchase, jadi tidak
     * ada lagi "1 project sesi" untuk dijadikan patokan otomatis -- murni
     * ikut pilihan filter). Prefix "KAS/BANK" untuk user yang boleh lihat
     * Bank (sesuai brief awal), "KAS" saja untuk role lain (mereka memang
     * tidak pernah melihat data Bank). Filter Jenis (Revisi lanjutan)
     * mempersempit ke "LAPORAN KAS" atau "LAPORAN BANK" saja saat user
     * memilih cetak per sumber.
     */
    private function reportTitle(array $filters): string
    {
        $source = $filters['source'] ?? '';
        if ($source === 'kas' || !can('bank', 'view')) {
            $label = 'LAPORAN KAS';
        } elseif ($source === 'bank') {
            $label = 'LAPORAN BANK';
        } else {
            $label = 'LAPORAN KAS/BANK';
        }

        $ids = !empty($filters['project_ids']) ? $filters['project_ids'] : [];
        $pid = count($ids) === 1 ? (int) $ids[0] : (!empty($filters['project_id']) ? (int) $filters['project_id'] : null);
        if ($pid && count($ids) <= 1) {
            $p = $this->projectModel->find($pid);
            if ($p) {
                return $label . ' ' . mb_strtoupper($p['project_name']);
            }
        }
        return $label . ' — SEMUA PROJECT';
    }

    /** [$ledger, ['company'=>.., 'period'=>.., 'title'=>..]] untuk PDF/Excel laporan (Kas [+Bank] gabungan). */
    private function buildReportData(): array
    {
        $filters = $this->collectFilters();
        $scope   = $this->scopePics();
        $divScope = kasDivisionScope();
        $projScope = $this->scopeAccessScope();
        $ledger  = $this->combinedLedger($filters, $scope, $divScope, $projScope);

        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $period = 'Periode : '
            . (!empty($filters['date_from']) ? formatTanggal($filters['date_from']) : 'Awal')
            . ' - '
            . (!empty($filters['date_to']) ? formatTanggal($filters['date_to']) : 'Sekarang');

        return [$ledger, [
            'company' => $companyName,
            'period'  => $period,
            'title'   => $this->reportTitle($filters),
        ]];
    }

    /**
     * null  = lihat semua PIC dalam cakupan divisi/project yang sudah
     *         dibatasi di tempat lain (super_admin/accounting/project_manager,
     *         DAN Purchase -- "Kas Purchase" company-wide tetap lintas-PIC).
     * array = daftar nama PIC yang boleh dilihat.
     *
     * Revisi audit RBAC Kas per-project (2026-09-25): pic_project SEBELUMNYA
     * ikut null di sini (tidak dibatasi PIC sama sekali, hanya project_id),
     * artinya satu akun PIC Project bisa melihat Kas PIC LAIN pada project
     * yang sama. Sekarang:
     *   - pic_project    -> HANYA nama PIC miliknya sendiri.
     *   - admin_project  -> nama miliknya sendiri + nama semua PIC Project
     *                       yang berbagi project dengannya (dia = pengawas).
     *   - purchase       -> TETAP null (bucket "Kas Purchase" company-wide,
     *                       tidak berubah dari desain semula).
     */
    private function scopePics(): ?array
    {
        $role = currentUserRole();
        if ($role === ROLE_PIC_PROJECT) {
            return $this->picModel->picNamesForUser((int) currentUserId());
        }
        if ($role === ROLE_ADMIN_PROJECT) {
            return $this->picModel->picNamesForAdminProject((int) currentUserId());
        }
        if (kasIsProjectGateRole($role)) {
            return null; // purchase
        }
        return kasScopePicNames();
    }

    /**
     * Batas akses (bukan filter pilihan bebas) untuk role gerbang Project.
     * null   = tidak dibatasi lewat gerbang ini (super_admin/accounting/
     *          project_manager/gerbang PIC lama).
     * array  = ['project_ids' => int[], 'division' => ?string] -- OR-kan:
     *          project yang diberikan akses (Project > Akses) ATAU (khusus
     *          Purchase) seluruh Kas Purchase company-wide. Dipakai
     *          CashTransaction::buildWhere() sebagai pagar server-side;
     *          filter Project opsional ($_GET['project_ids']) tetap AND di
     *          atasnya lewat collectFilters().
     */
    private function scopeAccessScope(): ?array
    {
        $ids = kasProjectScopeIds();
        if ($ids === null) {
            return null;
        }
        return ['project_ids' => $ids, 'division' => kasOwnDivisionBucket()];
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
            if (!$this->rowWithinAccessScope($row)) {
                denyAccess('Percobaan akses transaksi Kas di luar Project/Divisi yang diberikan akses');
            }
            return;
        }
        $scope = $this->scopePics();
        if ($scope !== null && !in_array($row['pic'], $scope, true)) {
            denyAccess('Percobaan akses transaksi Kas milik PIC lain');
        }
    }

    /**
     * true kalau baris ini termasuk project yang diberikan akses ATAU
     * (Purchase) bucket divisinya sendiri -- DAN (revisi audit RBAC Kas
     * per-project, 2026-09-25) lolos juga cakupan divisi (kasDivisionScope())
     * & cakupan nama PIC (scopePics()). SEBELUMNYA fungsi ini HANYA mengecek
     * project_id, sehingga /cash/edit/{id} atau /cash/delete milik divisi lain
     * (mis. Accounting) bisa dibuka langsung lewat URL selama project_id-nya
     * kebetulan sama -- celah IDOR, sudah dikonfirmasi bisa dieksploitasi
     * (akun PIC Project berhasil membuka form Edit transaksi Accounting).
     * Guard ini menyamakan aturan single-row dengan aturan daftar/list supaya
     * tidak ada jalan pintas lewat ID langsung.
     */
    private function rowWithinAccessScope(array $row): bool
    {
        $divScope = kasDivisionScope();
        if ($divScope !== null && !in_array($row['division'] ?? null, $divScope, true)) {
            return false;
        }
        $picScope = $this->scopePics();
        if ($picScope !== null && !in_array($row['pic'] ?? null, $picScope, true)) {
            return false;
        }

        $scope = $this->scopeAccessScope();
        if ($scope === null) {
            return true;
        }
        $pid = $row['project_id'] !== null ? (int) $row['project_id'] : null;
        if ($pid !== null && in_array($pid, $scope['project_ids'] ?? [], true)) {
            return true;
        }
        return !empty($scope['division']) && ($row['division'] ?? null) === $scope['division'];
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
            // Project header: untuk role gerbang Project WAJIB/opsional pilih
            // sendiri di antara project yang diberikan akses (revisi 23 Sep
            // 2026 -- dulu dipaksa dari session 1 project, sekarang user
            // memilih dari beberapa). Ownership divalidasi di validateInput()
            // sebelum dipakai simpan.
            'project_id'  => !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null,
            'rekening_id' => !empty($_POST['rekening_id']) ? (int) $_POST['rekening_id'] : null,
        ];
    }

    /** Project header final yang disimpan -- sudah divalidasi ownership-nya di validateInput(). */
    private function resolveProjectId(?int $posted): ?int
    {
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
        // Role gerbang Project: baris tanpa Project eksplisit ikut Project
        // HEADER yang baru saja dipilih user (saveItems() lalu memaksa SEMUA
        // baris ikut header ini juga -- lihat $forceProject di sana).
        $headerProjectId = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;
        $gatedProjectId = kasIsProjectGateRole(currentUserRole()) ? $headerProjectId : null;
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
        if (kasIsProjectGateRole(currentUserRole())) {
            // Revisi 23 Sep 2026: Project header TIDAK LAGI dipaksa dari sesi --
            // user memilih sendiri, jadi ownership-nya HARUS divalidasi di sini
            // (bukan cuma disembunyikan di dropdown). pic_project/admin_project
            // WAJIB pilih Project (satu-satunya scope Kas mereka); Purchase
            // boleh kosong (otomatis masuk bucket "Kas Purchase").
            $allowedProjectIds = kasProjectScopeIds();
            if (!empty($d['project_id'])) {
                if (!in_array((int) $d['project_id'], $allowedProjectIds, true)) {
                    $errors[] = 'Project yang dipilih di luar akses Kas Anda.';
                }
            } elseif ($this->isProjectRequiredForGatedRole()) {
                $errors[] = 'Project wajib dipilih.';
            }
        } elseif (!empty($d['project_id']) && !$this->projectModel->find((int) $d['project_id'])) {
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
