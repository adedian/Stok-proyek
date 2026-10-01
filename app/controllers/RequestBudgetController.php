<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/RequestBudget.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Request Budget -- pengajuan budget project (modul MANDIRI).
 *
 * Tidak membuat PO / Pembayaran / Kas / Invoice / Stok secara otomatis. Semua
 * aksi di sini hanya mengubah catatan request_budgets + history + activity log
 * (+ notifikasi push best-effort).
 *
 * Keamanan: tiap aksi (1) cek permission, (2) cek scope baris (IDOR) -- baca
 * RequestBudget::canView(), (3) cek kepemilikan untuk aksi pengaju, (4) validasi
 * status ATOMIK lewat RequestBudget::transition(). Total dihitung ulang di
 * backend (RequestBudget::computeItems()).
 */
class RequestBudgetController extends Controller
{
    private RequestBudget $rbModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('request_budget', 'view');
        $this->rbModel = new RequestBudget();
        $this->activityLog = new ActivityLog();
    }

    // =================== Daftar ===================

    public function index()
    {
        $scope = $this->rbModel->scopeForCurrentUser();
        $filters = [
            'keyword'      => trim($_GET['keyword'] ?? ''),
            'project_id'   => (int) ($_GET['project_id'] ?? 0),
            'requester_id' => (int) ($_GET['requester_id'] ?? 0),
            'status'       => trim($_GET['status'] ?? ''),
            'date_from'    => trim($_GET['date_from'] ?? ''),
            'date_to'      => trim($_GET['date_to'] ?? ''),
        ];

        $total = $this->rbModel->countFiltered($filters, $scope);
        $pg = paginationInfo($total, (int) ($_GET['page'] ?? 1));
        $rows = $this->rbModel->listPaginated($filters, $scope, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(['module' => 'request_budget'] + $filters));

        $this->view('request_budget/list', [
            'pageTitle'  => 'Request Budget',
            'rows'       => $rows,
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
            'projects'   => $this->projectFilterOptions($scope),
            'requesters' => $this->rbModel->requesterOptions($scope),
            'statuses'   => RequestBudget::STATUS_LABELS,
            'scopeMode'  => $scope['mode'],
            'rbController' => $this,
        ]);
    }

    private function projectFilterOptions(array $scope): array
    {
        if ($scope['mode'] === 'project') {
            return array_map(
                fn($p) => ['id' => $p['id'], 'project_name' => $p['project_name']],
                (new ProjectUserAccess())->projectsForUser((int) $scope['user_id'])
            );
        }
        return (new Project())->activeList();
    }

    // =================== Tambah ===================

    public function create()
    {
        Middleware::requirePermission('request_budget', 'create');
        $projects = $this->rbModel->selectableProjects();

        $this->view('request_budget/form', [
            'pageTitle' => 'Tambah Request Budget',
            'mode'      => 'create',
            'rb'        => null,
            'items'     => [],
            'number'    => $this->rbModel->previewNumber(),
            'projects'  => $projects,
            'units'     => (new Unit())->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('request_budget', 'create');
        $this->requirePost();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);
        // Project WAJIB termasuk yang di-assign ke user (bukan cuma disembunyikan di dropdown).
        if ($data['project_id'] > 0 && !$this->rbModel->projectAllowedForCreate($data['project_id'])) {
            $errors[] = 'Anda tidak punya akses ke project yang dipilih.';
            $this->activityLog->log(currentUserId(), 'request_budget', 'access_denied',
                "Request Budget: mencoba membuat untuk project #{$data['project_id']} di luar akses");
        }
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('request_budget', 'create');
        }

        $submitNow = !empty($_POST['submit_after']) && can('request_budget', 'submit');
        [$items, $total] = RequestBudget::computeItems($data['items']);
        $status = $submitNow ? RequestBudget::PENDING_APPROVAL : RequestBudget::DRAFT;

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $number = $this->rbModel->nextNumber();
            $id = $this->rbModel->create([
                'request_number'    => $number,
                'request_date'      => $data['request_date'],
                'project_id'        => $data['project_id'],
                'requester_user_id' => (int) currentUserId(),
                'purpose'           => $data['purpose'],
                'period_label'      => $data['period_label'] ?: null,
                'description'       => $data['description'] ?: null,
                'status'            => $status,
                'total_amount'      => $total,
                'submitted_at'      => $submitNow ? date('Y-m-d H:i:s') : null,
                'created_by'        => currentUserId(),
            ]);
            $this->rbModel->replaceItems($id, $items);
            $this->rbModel->addHistory($id, currentUserId(), 'create', null, RequestBudget::DRAFT, "Request Budget {$number} dibuat");
            if ($submitNow) {
                $this->rbModel->addHistory($id, currentUserId(), 'submit', RequestBudget::DRAFT, $status, 'Diajukan untuk approval');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestBudget store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Request Budget. Silakan coba lagi.');
            $this->redirect('request_budget', 'create');
        }

        $this->logAct('create', $id, $number, 'dibuat (' . RequestBudget::statusLabel($status) . ')');
        if ($submitNow) {
            $this->logAct('submit', $id, $number, 'diajukan untuk approval');
            $this->notify('approve', "Request Budget {$number} menunggu approval",
                'Diajukan oleh ' . currentUserName() . ' -- Rp ' . number_format($total, 0, ',', '.'), $id);
        }
        setFlash('success', "Request Budget {$number} berhasil disimpan" . ($submitNow ? ' dan diajukan untuk approval.' : ' sebagai Draft.'));
        $this->redirect('request_budget', 'detail', ['id' => $id]);
    }

    // =================== Detail ===================

    public function detail()
    {
        $rb = $this->loadViewable((int) ($_GET['id'] ?? 0));
        $this->view('request_budget/detail', [
            'pageTitle' => 'Detail Request Budget',
            'rb'        => $rb,
            'items'     => $this->rbModel->items((int) $rb['id']),
            'history'   => $this->rbModel->history((int) $rb['id']),
            'actions'   => $this->availableActions($rb),
        ]);
    }

    // =================== Edit (hanya DRAFT) ===================

    public function edit()
    {
        Middleware::requirePermission('request_budget', 'edit');
        $rb = $this->loadOwned((int) ($_GET['id'] ?? 0));
        $this->assertStatus($rb, [RequestBudget::DRAFT], 'Request Budget hanya bisa diedit saat berstatus Draft.', 'detail');

        $this->view('request_budget/form', [
            'pageTitle' => 'Edit Request Budget',
            'mode'      => 'edit',
            'rb'        => $rb,
            'items'     => $this->rbModel->items((int) $rb['id']),
            'number'    => $rb['request_number'],
            'projects'  => $this->rbModel->selectableProjects(),
            'units'     => (new Unit())->activeList(),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('request_budget', 'edit');
        $this->requirePost();

        $rb = $this->loadOwned((int) ($_POST['id'] ?? 0));
        $id = (int) $rb['id'];
        $this->assertStatus($rb, [RequestBudget::DRAFT], 'Request Budget hanya bisa diedit saat berstatus Draft.', 'detail');

        $data = $this->collectInput();
        $errors = $this->validateInput($data);
        // Project hanya divalidasi ulang kalau DIGANTI.
        if ($data['project_id'] > 0 && $data['project_id'] !== (int) $rb['project_id']
            && !$this->rbModel->projectAllowedForCreate($data['project_id'])) {
            $errors[] = 'Anda tidak punya akses ke project yang dipilih.';
        }
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('request_budget', 'edit', ['id' => $id]);
        }

        $submitNow = !empty($_POST['submit_after']) && can('request_budget', 'submit');
        [$items, $total] = RequestBudget::computeItems($data['items']);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $this->rbModel->updateById($id, [
                'request_date' => $data['request_date'],
                'project_id'   => $data['project_id'],
                'purpose'      => $data['purpose'],
                'period_label' => $data['period_label'] ?: null,
                'description'  => $data['description'] ?: null,
                'total_amount' => $total,
            ]);
            $this->rbModel->replaceItems($id, $items);
            $this->rbModel->addHistory($id, currentUserId(), 'edit', RequestBudget::DRAFT, RequestBudget::DRAFT, 'Data Request Budget diperbarui');
            $ok = true;
            if ($submitNow) {
                $ok = $this->rbModel->transition($id, [RequestBudget::DRAFT], RequestBudget::PENDING_APPROVAL, ['submitted_at' => date('Y-m-d H:i:s')]);
                if ($ok) {
                    $this->rbModel->addHistory($id, currentUserId(), 'submit', RequestBudget::DRAFT, RequestBudget::PENDING_APPROVAL, 'Diajukan untuk approval');
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestBudget update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Request Budget.');
            $this->redirect('request_budget', 'edit', ['id' => $id]);
        }

        $this->logAct('update', $id, $rb['request_number'], 'diedit');
        if ($submitNow && $ok) {
            $this->logAct('submit', $id, $rb['request_number'], 'diajukan untuk approval');
            $this->notify('approve', "Request Budget {$rb['request_number']} menunggu approval",
                'Diajukan oleh ' . currentUserName() . ' -- Rp ' . number_format($total, 0, ',', '.'), $id);
        }
        setFlash('success', 'Request Budget berhasil diperbarui' . ($submitNow && $ok ? ' dan diajukan untuk approval.' : '.'));
        $this->redirect('request_budget', 'detail', ['id' => $id]);
    }

    // =================== Transisi status ===================

    /** Pengaju: DRAFT -> PENDING_APPROVAL */
    public function submit()
    {
        Middleware::requirePermission('request_budget', 'submit');
        $rb = $this->startAction(true);
        $this->assertStatus($rb, [RequestBudget::DRAFT], 'Hanya Request Budget berstatus Draft yang bisa diajukan.');
        if (count($this->rbModel->items((int) $rb['id'])) === 0) {
            setFlash('error', 'Request Budget belum punya item kebutuhan.');
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        $this->doTransition($rb, [RequestBudget::DRAFT], RequestBudget::PENDING_APPROVAL, 'submit',
            ['submitted_at' => date('Y-m-d H:i:s')], 'Diajukan untuk approval', 'diajukan untuk approval');
        $this->notify('approve', "Request Budget {$rb['request_number']} menunggu approval",
            'Diajukan oleh ' . currentUserName() . ' -- Rp ' . number_format((float) $rb['total_amount'], 0, ',', '.'), (int) $rb['id']);
        setFlash('success', 'Request Budget diajukan dan menunggu approval.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /**
     * Andy (Purchase) ATAU Vicky (PM) -- siapa pun yang punya izin approve.
     * PENDING_APPROVAL -> APPROVED. Pelaku + role + waktu dicatat.
     */
    public function approve()
    {
        Middleware::requirePermission('request_budget', 'approve');
        $rb = $this->startAction(false);
        $this->assertNotSelfApproval($rb);
        $this->assertStatus($rb, [RequestBudget::PENDING_APPROVAL], "Hanya Request Budget 'Menunggu Approval' yang bisa disetujui.");
        $role = $this->rbModel->roleNameOf(currentUserId());
        $this->doTransition($rb, [RequestBudget::PENDING_APPROVAL], RequestBudget::APPROVED, 'approve',
            ['approved_by' => currentUserId(), 'approved_at' => date('Y-m-d H:i:s'), 'approved_by_role' => $role],
            'Disetujui oleh ' . currentUserName() . ' (' . $role . ')', 'disetujui oleh ' . currentUserName() . ' (' . $role . ')');
        $this->notifyUsers([(int) $rb['requester_user_id']], "Request Budget {$rb['request_number']} telah disetujui",
            'Disetujui oleh ' . currentUserName() . ' (' . $role . ')', (int) $rb['id']);
        // Siapa pun yang approve, yang berhak meneruskan (izin 'forward') diberi tahu.
        $this->notify('forward', "Request Budget {$rb['request_number']} disetujui -- menunggu pengajuan Purchase",
            'Disetujui oleh ' . currentUserName() . ' (' . $role . ')', (int) $rb['id'], [(int) $rb['requester_user_id']]);
        setFlash('success', 'Request Budget disetujui.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /** Pemilik izin reject: PENDING_APPROVAL -> REJECTED (alasan wajib) */
    public function reject()
    {
        Middleware::requirePermission('request_budget', 'reject');
        $rb = $this->startAction(false);
        $this->assertNotSelfApproval($rb);
        $this->assertStatus($rb, [RequestBudget::PENDING_APPROVAL], "Hanya Request Budget 'Menunggu Approval' yang bisa ditolak.");
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            setFlash('error', 'Alasan penolakan wajib diisi.');
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        $this->doTransition($rb, [RequestBudget::PENDING_APPROVAL], RequestBudget::REJECTED, 'reject',
            ['rejected_by' => currentUserId(), 'rejected_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $reason],
            'Ditolak oleh ' . currentUserName() . ': ' . $reason, 'ditolak: ' . $reason);
        $this->notifyUsers([(int) $rb['requester_user_id']], "Request Budget {$rb['request_number']} ditolak", $reason, (int) $rb['id']);
        setFlash('success', 'Request Budget ditolak.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /** Pengaju: REJECTED -> DRAFT (revisi) */
    public function revise()
    {
        Middleware::requirePermission('request_budget', 'edit');
        $rb = $this->startAction(true);
        $this->assertStatus($rb, [RequestBudget::REJECTED], 'Hanya Request Budget yang ditolak yang bisa direvisi.');
        $this->doTransition($rb, [RequestBudget::REJECTED], RequestBudget::DRAFT, 'revise', [
            'approved_by' => null, 'approved_at' => null, 'approved_by_role' => null, 'submitted_at' => null,
        ], 'Dibuka kembali untuk revisi', 'dibuka untuk revisi');
        setFlash('success', 'Request Budget dikembalikan ke Draft. Silakan revisi lalu ajukan kembali.');
        $this->redirect('request_budget', 'edit', ['id' => $rb['id']]);
    }

    /**
     * Teruskan ke Purwati/Nissa: APPROVED -> FORWARDED.
     * HANYA pemilik izin 'forward' (Andy / Super Admin). Vicky boleh approve tetapi
     * TIDAK punya izin ini, jadi request yang di-approve Vicky tetap harus diteruskan Andy.
     * Izin dicek di Middleware (backend) + lagi di sini lewat availableActions-equivalent.
     */
    public function forward()
    {
        Middleware::requirePermission('request_budget', 'forward');
        $rb = $this->startAction(false);
        $this->assertStatus($rb, [RequestBudget::APPROVED], "Hanya Request Budget yang sudah disetujui yang bisa diteruskan ke Purwati/Nissa.");
        $dest = trim($_POST['forward_to'] ?? '');
        if (!in_array($dest, RequestBudget::FORWARD_DESTINATIONS, true)) {
            setFlash('error', 'Pilih tujuan pengajuan: ' . implode(' / ', RequestBudget::FORWARD_DESTINATIONS) . '.');
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        $role = $this->rbModel->roleNameOf(currentUserId());
        $this->doTransition($rb, [RequestBudget::APPROVED], RequestBudget::FORWARDED, 'forward',
            ['forwarded_by' => currentUserId(), 'forwarded_at' => date('Y-m-d H:i:s'), 'forwarded_to' => $dest, 'forwarded_by_role' => $role],
            'Diteruskan ke ' . $dest . ' oleh ' . currentUserName() . ' (' . $role . ')', 'diteruskan ke ' . $dest);
        $this->notifyUsers([(int) $rb['requester_user_id'], (int) $rb['approved_by']],
            "Request Budget {$rb['request_number']} diajukan ke {$dest}", 'Diteruskan oleh ' . currentUserName(), (int) $rb['id']);
        setFlash('success', "Request Budget diteruskan ke {$dest}.");
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /** Pemilik izin 'complete' (Andy / Super Admin): FORWARDED -> COMPLETED */
    public function complete()
    {
        Middleware::requirePermission('request_budget', 'complete');
        $rb = $this->startAction(false);
        $this->assertStatus($rb, [RequestBudget::FORWARDED], "Hanya Request Budget 'Diajukan ke Purwati/Nissa' yang bisa diselesaikan.");
        $this->doTransition($rb, [RequestBudget::FORWARDED], RequestBudget::COMPLETED, 'complete',
            ['completed_by' => currentUserId(), 'completed_at' => date('Y-m-d H:i:s')],
            'Request Budget diselesaikan oleh ' . currentUserName(), 'diselesaikan');
        $this->notifyUsers([(int) $rb['requester_user_id']], "Request Budget {$rb['request_number']} telah selesai", '', (int) $rb['id']);
        setFlash('success', 'Request Budget selesai.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }
    // =================== Hapus ===================

    public function delete()
    {
        Middleware::requirePermission('request_budget', 'delete');
        $rb = $this->startAction(false);
        $id = (int) $rb['id'];
        $isAdmin = currentUserRole() === ROLE_SUPER_ADMIN && can('request_budget', 'admin_delete');

        if ($rb['status'] === RequestBudget::DRAFT) {
            // Pengaju (pemilik) boleh hapus Draft-nya sendiri; Super Admin boleh semua Draft.
            if (!$isAdmin && (int) $rb['requester_user_id'] !== (int) currentUserId()) {
                $this->deny($rb, 'hapus Request Budget milik orang lain');
            }
        } elseif (!$isAdmin) {
            setFlash('error', 'Request Budget yang sudah diajukan tidak bisa dihapus. Hubungi Super Admin bila perlu.');
            $this->redirect('request_budget', 'detail', ['id' => $id]);
        }

        $this->rbModel->addHistory($id, currentUserId(), 'delete', $rb['status'], $rb['status'],
            $rb['status'] === RequestBudget::DRAFT ? 'Draft dihapus' : 'Dihapus secara administratif (status ' . $rb['status'] . ')');
        $this->rbModel->deleteById($id);
        $this->logAct($rb['status'] === RequestBudget::DRAFT ? 'delete' : 'admin_delete', $id, $rb['request_number'],
            'dihapus (status ' . $rb['status'] . ')');
        setFlash('success', "Request Budget {$rb['request_number']} dihapus.");
        $this->redirect('request_budget', 'index');
    }

    // =================== Cetak ===================

    public function print()
    {
        Middleware::requirePermission('request_budget', 'print');
        $rb = $this->loadViewable((int) ($_GET['id'] ?? 0));

        $autoprint = !empty($_GET['autoprint']);
        $this->logAct('print', (int) $rb['id'], $rb['request_number'], $autoprint ? 'dicetak' : 'dipratinjau');

        $this->view('request_budget/print', [
            'pageTitle' => 'Cetak Request Budget',
            'rb'        => $rb,
            'items'     => $this->rbModel->items((int) $rb['id']),
            'company'   => (new SystemSetting())->getGroup('company'),
            'autoprint' => $autoprint,
        ]);
    }

    // =================== Helper privat ===================

    /**
     * Aksi apa saja yang boleh tampil untuk user ini pada $rb (dipakai detail & daftar).
     * Murni tampilan -- setiap endpoint tetap memvalidasi ulang sendiri.
     */
    public function availableActions(array $rb): array
    {
        $s = $rb['status'];
        $uid = (int) currentUserId();
        $isOwner = (int) $rb['requester_user_id'] === $uid || currentUserRole() === ROLE_SUPER_ADMIN;
        $notSelf = (int) $rb['requester_user_id'] !== $uid || currentUserRole() === ROLE_SUPER_ADMIN;
        $a = [];
        if (can('request_budget', 'edit') && $isOwner && $s === RequestBudget::DRAFT) {
            $a['edit'] = true;
        }
        if (can('request_budget', 'submit') && $isOwner && $s === RequestBudget::DRAFT) {
            $a['submit'] = true;
        }
        if (can('request_budget', 'edit') && $isOwner && $s === RequestBudget::REJECTED) {
            $a['revise'] = true;
        }
        if ($s === RequestBudget::PENDING_APPROVAL && $notSelf) {
            if (can('request_budget', 'approve')) {
                $a['approve'] = true;
            }
            if (can('request_budget', 'reject')) {
                $a['reject'] = true;
            }
        }
        // Teruskan ke Purwati/Nissa: hanya pemilik izin 'forward' (Andy/Super Admin), BUKAN Vicky.
        if (can('request_budget', 'forward') && $s === RequestBudget::APPROVED) {
            $a['forward'] = true;
        }
        if (can('request_budget', 'complete') && $s === RequestBudget::FORWARDED) {
            $a['complete'] = true;
        }
        if (can('request_budget', 'delete')) {
            if ($s === RequestBudget::DRAFT && $isOwner) {
                $a['delete'] = true;
            } elseif (currentUserRole() === ROLE_SUPER_ADMIN && can('request_budget', 'admin_delete')) {
                $a['delete'] = true;
            }
        }
        if (can('request_budget', 'print')) {
            $a['print'] = true;
        }
        return $a;
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('request_budget', 'index');
        }
        verifyCsrf();
    }

    /** Awal setiap aksi POST: CSRF + muat baris + guard scope (+ opsional kepemilikan). */
    private function startAction(bool $ownerOnly): array
    {
        $this->requirePost();
        $id = (int) ($_POST['id'] ?? 0);
        return $ownerOnly ? $this->loadOwned($id) : $this->loadViewable($id);
    }

    /** Muat baris + pastikan user boleh MELIHATNYA (anti-IDOR lintas project/user). */
    private function loadViewable(int $id): array
    {
        $rb = $id > 0 ? $this->rbModel->findWithRelations($id) : null;
        if (!$rb) {
            setFlash('error', 'Request Budget tidak ditemukan.');
            $this->redirect('request_budget', 'index');
        }
        if (!$this->rbModel->canView($rb)) {
            $this->deny($rb, 'mengakses Request Budget di luar cakupan');
        }
        return $rb;
    }

    /** Seperti loadViewable() + harus pengaju-nya sendiri (atau Super Admin). */
    private function loadOwned(int $id): array
    {
        $rb = $this->loadViewable($id);
        if ((int) $rb['requester_user_id'] !== (int) currentUserId() && currentUserRole() !== ROLE_SUPER_ADMIN) {
            $this->deny($rb, 'mengubah Request Budget milik orang lain');
        }
        return $rb;
    }

    private function deny(array $rb, string $what): void
    {
        $this->activityLog->log(currentUserId(), 'request_budget', 'access_denied',
            "Ditolak: mencoba {$what} {$rb['request_number']} (#{$rb['id']})");
        denyAccess('Anda tidak berhak atas Request Budget ini.');
    }

    /** Pemberi approval tidak boleh menyetujui/menolak request buatannya sendiri (kecuali Super Admin). */
    private function assertNotSelfApproval(array $rb): void
    {
        if ((int) $rb['requester_user_id'] === (int) currentUserId() && currentUserRole() !== ROLE_SUPER_ADMIN) {
            $this->deny($rb, 'menyetujui/menolak Request Budget buatan sendiri');
        }
    }

    private function assertStatus(array $rb, array $allowed, string $message, string $redirectAction = 'detail'): void
    {
        if (!in_array($rb['status'], $allowed, true)) {
            setFlash('error', $message);
            $this->redirect('request_budget', $redirectAction === 'detail' ? 'detail' : 'index', ['id' => $rb['id']]);
        }
    }

    /** Eksekusi transisi atomik + history + activity log; gagal -> flash & kembali. */
    private function doTransition(array $rb, array $from, string $to, string $action, array $extra, string $historyNote, string $logNote): void
    {
        $id = (int) $rb['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            if (!$this->rbModel->transition($id, $from, $to, $extra)) {
                $pdo->rollBack();
                setFlash('error', 'Status Request Budget sudah berubah (kemungkinan diproses user lain). Muat ulang halaman.');
                $this->redirect('request_budget', 'detail', ['id' => $id]);
            }
            $this->rbModel->addHistory($id, currentUserId(), $action, $rb['status'], $to, $historyNote);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestBudget transition error: ' . $e->getMessage());
            setFlash('error', 'Gagal memproses Request Budget.');
            $this->redirect('request_budget', 'detail', ['id' => $id]);
        }
        $this->logAct($action, $id, $rb['request_number'], $logNote);
    }

    private function logAct(string $action, int $id, string $number, string $note): void
    {
        $this->activityLog->log(currentUserId(), 'request_budget', $action, "Request Budget {$number} (#{$id}) {$note}");
    }

    /** Notifikasi push ke semua user berizin $permission (best-effort, tidak boleh menggagalkan alur). */
    private function notify(string $permission, string $title, string $body, int $id, array $exclude = []): void
    {
        $ids = array_values(array_diff($this->rbModel->userIdsWithPermission($permission), array_map('intval', $exclude), [(int) currentUserId()]));
        $this->notifyUsers($ids, $title, $body, $id);
    }

    private function notifyUsers(array $userIds, string $title, string $body, int $id): void
    {
        $userIds = array_values(array_diff(array_map('intval', $userIds), [(int) currentUserId()]));
        if (!$userIds) {
            return;
        }
        try {
            sendPushToUsers($userIds, $title, $body, route('request_budget', 'detail', ['id' => $id]), 'request_budget.view');
        } catch (Throwable $e) {
            error_log('RequestBudget push gagal: ' . $e->getMessage());
        }
    }

    private function collectInput(): array
    {
        $names = (array) ($_POST['item_name'] ?? []);
        $rows = [];
        foreach ($names as $i => $name) {
            $rows[] = [
                'item_name'   => $name,
                'description' => ($_POST['item_description'][$i] ?? ''),
                'qty'         => parseQtyInput($_POST['qty'][$i] ?? 0),
                'unit_name'   => ($_POST['unit_name'][$i] ?? ''),
                'price'       => parseCurrencyInput($_POST['price'][$i] ?? 0),
                'notes'       => ($_POST['item_notes'][$i] ?? ''),
            ];
        }
        return [
            'request_date' => trim($_POST['request_date'] ?? ''),
            'project_id'   => (int) ($_POST['project_id'] ?? 0),
            'purpose'      => trim($_POST['purpose'] ?? ''),
            'period_label' => trim($_POST['period_label'] ?? ''),
            'description'  => trim($_POST['description'] ?? ''),
            'items'        => $rows,
        ];
    }

    private function validateInput(array $d): array
    {
        $errors = [];
        if ($d['request_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['request_date']) || strtotime($d['request_date']) === false) {
            $errors[] = 'Tanggal pengajuan wajib diisi dengan benar.';
        }
        if ($d['project_id'] <= 0) {
            $errors[] = 'Project wajib dipilih.';
        }
        if ($d['purpose'] === '') {
            $errors[] = 'Keperluan wajib diisi.';
        }
        [$items] = RequestBudget::computeItems($d['items']);
        if (!$items) {
            $errors[] = 'Minimal harus ada 1 item kebutuhan.';
        }
        foreach ($items as $i => $it) {
            $n = $i + 1;
            if ($it['item_name'] === '') {
                $errors[] = "Item #{$n}: nama barang/kebutuhan wajib diisi.";
            }
            if ($it['qty'] <= 0) {
                $errors[] = "Item #{$n}: qty harus lebih dari 0.";
            }
            if ($it['estimated_unit_price'] < 0) {
                $errors[] = "Item #{$n}: harga tidak boleh negatif.";
            }
        }
        return $errors;
    }
}
