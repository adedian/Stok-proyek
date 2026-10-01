<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/RequestPo.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Request PO -- permintaan pembuatan PO (modul MANDIRI).
 *
 * Alur: DRAFT -> MENUNGGU APPROVAL -> DISETUJUI | DITOLAK -> selesai (STOP).
 * TIDAK membuat PO / Request Budget / Invoice / Kas / Stok apa pun, dan tidak
 * punya relasi ke modul-modul itu. Semua aksi hanya mengubah request_pos +
 * request_po_items + request_po_history + activity log (+ push best-effort).
 *
 * Keamanan: tiap aksi (1) cek permission di backend, (2) cek kepemilikan untuk
 * aksi pembuat (edit/submit/hapus -- pembuat sendiri atau Super Admin),
 * (3) validasi status ATOMIK lewat RequestPo::transition(), (4) CSRF untuk semua POST.
 * Approve/Tolak: izin 'approve'/'reject' (Andy lewat izin per-akun, Super Admin),
 * dan tidak boleh untuk request buatan sendiri (kecuali Super Admin).
 */
class RequestPoController extends Controller
{
    private RequestPo $rpModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('request_po', 'view');
        $this->rpModel = new RequestPo();
        $this->activityLog = new ActivityLog();
    }

    // =================== Daftar ===================

    public function index()
    {
        $filters = [
            'number'     => trim($_GET['number'] ?? ''),
            'requester'  => trim($_GET['requester'] ?? ''),
            'project_id' => (int) ($_GET['project_id'] ?? 0),
            'status'     => trim($_GET['status'] ?? ''),
            'date_from'  => trim($_GET['date_from'] ?? ''),
            'date_to'    => trim($_GET['date_to'] ?? ''),
        ];

        $total = $this->rpModel->countFiltered($filters);
        $pg = paginationInfo($total, (int) ($_GET['page'] ?? 1));
        $rows = $this->rpModel->listPaginated($filters, $pg['perPage'], $pg['offset']);

        $this->view('request_po/list', [
            'pageTitle'  => 'Request PO',
            'rows'       => $rows,
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter(['module' => 'request_po'] + $filters)),
            'projects'   => (new Project())->activeList(),
            'statuses'   => RequestPo::STATUS_LABELS,
            'rpController' => $this,
        ]);
    }

    // =================== Tambah ===================

    public function create()
    {
        Middleware::requirePermission('request_po', 'create');

        $this->view('request_po/form', [
            'pageTitle' => 'Tambah Request PO',
            'mode'      => 'create',
            'rp'        => null,
            'items'     => [],
            'number'    => $this->rpModel->previewNumber(),
            'projects'  => (new Project())->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('request_po', 'create');
        $this->requirePost();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('request_po', 'create');
        }

        $submitNow = !empty($_POST['submit_after']) && can('request_po', 'submit');
        $items = RequestPo::normalizeItems($data['items']);
        $status = $submitNow ? RequestPo::PENDING_APPROVAL : RequestPo::DRAFT;

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $number = $this->rpModel->nextNumber();
            $id = $this->rpModel->create([
                'request_po_number' => $number,
                'request_date'      => $data['request_date'],
                'requester_name'    => $data['requester_name'],
                'project_id'        => $data['project_id'],
                'purpose'           => $data['purpose'],
                'notes'             => $data['notes'] ?: null,
                'status'            => $status,
                'submitted_at'      => $submitNow ? date('Y-m-d H:i:s') : null,
                'created_by'        => (int) currentUserId(),
            ]);
            $this->rpModel->replaceItems($id, $items);
            $this->rpModel->addHistory($id, currentUserId(), 'create', null, RequestPo::DRAFT, "Request PO {$number} dibuat");
            if ($submitNow) {
                $this->rpModel->addHistory($id, currentUserId(), 'submit', RequestPo::DRAFT, $status, 'Request PO disubmit untuk approval');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestPo store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Request PO. Silakan coba lagi.');
            $this->redirect('request_po', 'create');
        }

        $this->logAct('create', $id, $number, 'dibuat (' . RequestPo::statusLabel($status) . ')');
        if ($submitNow) {
            $this->logAct('submit', $id, $number, 'disubmit untuk approval');
            $this->notifyApprovers($number, $id);
        }
        setFlash('success', "Request PO {$number} berhasil disimpan" . ($submitNow ? ' dan disubmit untuk approval.' : ' sebagai Draft.'));
        $this->redirect('request_po', 'detail', ['id' => $id]);
    }

    // =================== Detail ===================

    public function detail()
    {
        $rp = $this->loadRequest((int) ($_GET['id'] ?? 0));
        $this->view('request_po/detail', [
            'pageTitle' => 'Detail Request PO',
            'rp'        => $rp,
            'items'     => $this->rpModel->items((int) $rp['id']),
            'history'   => $this->rpModel->history((int) $rp['id']),
            'actions'   => $this->availableActions($rp),
            'approverNames' => $this->approverNames(),
        ]);
    }

    // =================== Edit (hanya DRAFT) ===================

    public function edit()
    {
        Middleware::requirePermission('request_po', 'edit');
        $rp = $this->loadOwned((int) ($_GET['id'] ?? 0));
        $this->assertStatus($rp, [RequestPo::DRAFT], 'Request PO hanya bisa diedit saat berstatus Draft.');

        $this->view('request_po/form', [
            'pageTitle' => 'Edit Request PO',
            'mode'      => 'edit',
            'rp'        => $rp,
            'items'     => $this->rpModel->items((int) $rp['id']),
            'number'    => $rp['request_po_number'],
            'projects'  => (new Project())->activeList(),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('request_po', 'edit');
        $this->requirePost();

        $rp = $this->loadOwned((int) ($_POST['id'] ?? 0));
        $id = (int) $rp['id'];
        $this->assertStatus($rp, [RequestPo::DRAFT], 'Request PO hanya bisa diedit saat berstatus Draft.');

        $data = $this->collectInput();
        $errors = $this->validateInput($data);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('request_po', 'edit', ['id' => $id]);
        }

        $submitNow = !empty($_POST['submit_after']) && can('request_po', 'submit');
        $items = RequestPo::normalizeItems($data['items']);

        $pdo = getPDO();
        $ok = true;
        try {
            $pdo->beginTransaction();
            // Kunci baris + pastikan MASIH Draft di dalam transaksi (cegah edit setelah
            // request lain sempat men-submit/men-approve di antara load dan update).
            if ($this->rpModel->lockStatus($id) !== RequestPo::DRAFT) {
                $pdo->rollBack();
                setFlash('error', 'Status Request PO sudah berubah. Muat ulang halaman.');
                $this->redirect('request_po', 'detail', ['id' => $id]);
            }
            $this->rpModel->updateById($id, [
                'request_date'   => $data['request_date'],
                'requester_name' => $data['requester_name'],
                'project_id'     => $data['project_id'],
                'purpose'        => $data['purpose'],
                'notes'          => $data['notes'] ?: null,
            ]);
            $this->rpModel->replaceItems($id, $items);
            $this->rpModel->addHistory($id, currentUserId(), 'edit', RequestPo::DRAFT, RequestPo::DRAFT, 'Data Request PO diperbarui');
            if ($submitNow) {
                $ok = $this->rpModel->transition($id, [RequestPo::DRAFT], RequestPo::PENDING_APPROVAL, ['submitted_at' => date('Y-m-d H:i:s')]);
                if ($ok) {
                    $this->rpModel->addHistory($id, currentUserId(), 'submit', RequestPo::DRAFT, RequestPo::PENDING_APPROVAL, 'Request PO disubmit untuk approval');
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestPo update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Request PO.');
            $this->redirect('request_po', 'edit', ['id' => $id]);
        }

        $this->logAct('update', $id, $rp['request_po_number'], 'diedit');
        if ($submitNow && $ok) {
            $this->logAct('submit', $id, $rp['request_po_number'], 'disubmit untuk approval');
            $this->notifyApprovers($rp['request_po_number'], $id);
        }
        setFlash('success', 'Request PO berhasil diperbarui' . ($submitNow && $ok ? ' dan disubmit untuk approval.' : '.'));
        $this->redirect('request_po', 'detail', ['id' => $id]);
    }

    // =================== Transisi status ===================

    /** Pembuat: DRAFT -> MENUNGGU APPROVAL */
    public function submit()
    {
        Middleware::requirePermission('request_po', 'submit');
        $this->requirePost();
        $rp = $this->loadOwned((int) ($_POST['id'] ?? 0));
        $this->assertStatus($rp, [RequestPo::DRAFT], 'Hanya Request PO berstatus Draft yang bisa disubmit.');
        if (count($this->rpModel->items((int) $rp['id'])) === 0) {
            setFlash('error', 'Request PO belum punya barang.');
            $this->redirect('request_po', 'detail', ['id' => $rp['id']]);
        }
        $this->doTransition($rp, [RequestPo::DRAFT], RequestPo::PENDING_APPROVAL, 'submit',
            ['submitted_at' => date('Y-m-d H:i:s')], 'Request PO disubmit untuk approval', 'disubmit untuk approval');
        $this->notifyApprovers($rp['request_po_number'], (int) $rp['id']);
        setFlash('success', 'Request PO disubmit dan menunggu approval.');
        $this->redirect('request_po', 'detail', ['id' => $rp['id']]);
    }

    /** Approver (Andy / Super Admin): MENUNGGU APPROVAL -> DISETUJUI */
    public function approve()
    {
        Middleware::requirePermission('request_po', 'approve');
        $this->requirePost();
        $rp = $this->loadRequest((int) ($_POST['id'] ?? 0));
        $this->assertNotSelfApproval($rp);
        $this->assertStatus($rp, [RequestPo::PENDING_APPROVAL], "Hanya Request PO 'Menunggu Approval' yang bisa disetujui.");

        $note = mb_substr(trim($_POST['note'] ?? ''), 0, 500);
        $role = $this->rpModel->roleNameOf(currentUserId());
        $this->doTransition($rp, [RequestPo::PENDING_APPROVAL], RequestPo::APPROVED, 'approve', [
            'approved_by'      => (int) currentUserId(),
            'approved_by_role' => $role,
            'approved_at'      => date('Y-m-d H:i:s'),
            'approval_notes'   => $note !== '' ? $note : null,
        ], 'Disetujui oleh ' . currentUserName() . ($role ? " ({$role})" : '') . ($note !== '' ? ": {$note}" : ''), 'disetujui');
        setFlash('success', "Request PO {$rp['request_po_number']} disetujui.");
        $this->redirect('request_po', 'detail', ['id' => $rp['id']]);
    }

    /** Approver (Andy / Super Admin): MENUNGGU APPROVAL -> DITOLAK (alasan wajib) */
    public function reject()
    {
        Middleware::requirePermission('request_po', 'reject');
        $this->requirePost();
        $rp = $this->loadRequest((int) ($_POST['id'] ?? 0));
        $this->assertNotSelfApproval($rp);

        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            setFlash('error', 'Alasan penolakan wajib diisi.');
            $this->redirect('request_po', 'detail', ['id' => $rp['id']]);
        }
        $reason = mb_substr($reason, 0, 1000);
        $this->assertStatus($rp, [RequestPo::PENDING_APPROVAL], "Hanya Request PO 'Menunggu Approval' yang bisa ditolak.");

        $role = $this->rpModel->roleNameOf(currentUserId());
        $this->doTransition($rp, [RequestPo::PENDING_APPROVAL], RequestPo::REJECTED, 'reject', [
            'rejected_by'      => (int) currentUserId(),
            'rejected_by_role' => $role,
            'rejected_at'      => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason,
        ], 'Ditolak oleh ' . currentUserName() . ($role ? " ({$role})" : '') . ": {$reason}", 'ditolak');
        setFlash('success', "Request PO {$rp['request_po_number']} ditolak.");
        $this->redirect('request_po', 'detail', ['id' => $rp['id']]);
    }

    // =================== Hapus (hanya Draft) ===================

    public function delete()
    {
        Middleware::requirePermission('request_po', 'delete');
        $this->requirePost();
        $rp = $this->loadOwned((int) ($_POST['id'] ?? 0));
        $this->assertStatus($rp, [RequestPo::DRAFT], 'Hanya Request PO berstatus Draft yang bisa dihapus.');

        $id = (int) $rp['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            // Hapus bersyarat: hanya kalau MASIH Draft (cegah hapus setelah di-submit request lain).
            if (!$this->rpModel->softDeleteDraft($id, (int) currentUserId())) {
                $pdo->rollBack();
                setFlash('error', 'Status Request PO sudah berubah. Muat ulang halaman.');
                $this->redirect('request_po', 'detail', ['id' => $id]);
            }
            $this->rpModel->addHistory($id, currentUserId(), 'delete', RequestPo::DRAFT, RequestPo::DRAFT, 'Draft dihapus');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestPo delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus Request PO.');
            $this->redirect('request_po', 'detail', ['id' => $id]);
        }
        $this->logAct('delete', $id, $rp['request_po_number'], 'Draft dihapus');
        setFlash('success', "Request PO {$rp['request_po_number']} dihapus.");
        $this->redirect('request_po', 'index');
    }

    // =================== Helper (publik: dipakai view daftar/detail) ===================

    /**
     * Aksi yang boleh tampil untuk user ini pada $rp. Murni tampilan --
     * setiap endpoint tetap memvalidasi ulang sendiri.
     */
    public function availableActions(array $rp): array
    {
        $s = $rp['status'];
        $isOwner = $this->isOwner($rp);
        $notSelf = (int) $rp['created_by'] !== (int) currentUserId() || currentUserRole() === ROLE_SUPER_ADMIN;
        $a = [];
        if ($s === RequestPo::DRAFT && $isOwner) {
            if (can('request_po', 'edit')) {
                $a['edit'] = true;
            }
            if (can('request_po', 'submit')) {
                $a['submit'] = true;
            }
            if (can('request_po', 'delete')) {
                $a['delete'] = true;
            }
        }
        if ($s === RequestPo::PENDING_APPROVAL && $notSelf) {
            if (can('request_po', 'approve')) {
                $a['approve'] = true;
            }
            if (can('request_po', 'reject')) {
                $a['reject'] = true;
            }
        }
        return $a;
    }

    // =================== Helper privat ===================

    private function isOwner(array $rp): bool
    {
        return (int) $rp['created_by'] === (int) currentUserId() || currentUserRole() === ROLE_SUPER_ADMIN;
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('request_po', 'index');
        }
        verifyCsrf();
    }

    private function loadRequest(int $id): array
    {
        $rp = $id > 0 ? $this->rpModel->findWithRelations($id) : null;
        if (!$rp) {
            setFlash('error', 'Request PO tidak ditemukan.');
            $this->redirect('request_po', 'index');
        }
        return $rp;
    }

    /** Muat baris + harus pembuat-nya sendiri (atau Super Admin). */
    private function loadOwned(int $id): array
    {
        $rp = $this->loadRequest($id);
        if (!$this->isOwner($rp)) {
            $this->deny($rp, 'mengubah Request PO milik orang lain');
        }
        return $rp;
    }

    private function deny(array $rp, string $what): void
    {
        $this->activityLog->log(currentUserId(), 'request_po', 'access_denied',
            "Ditolak: mencoba {$what} {$rp['request_po_number']} (#{$rp['id']})");
        denyAccess('Anda tidak berhak atas Request PO ini.');
    }

    /** Pemberi approval tidak boleh menyetujui/menolak request buatannya sendiri (kecuali Super Admin). */
    private function assertNotSelfApproval(array $rp): void
    {
        if ((int) $rp['created_by'] === (int) currentUserId() && currentUserRole() !== ROLE_SUPER_ADMIN) {
            $this->deny($rp, 'menyetujui/menolak Request PO buatan sendiri');
        }
    }

    private function assertStatus(array $rp, array $allowed, string $message): void
    {
        if (!in_array($rp['status'], $allowed, true)) {
            setFlash('error', $message);
            $this->redirect('request_po', 'detail', ['id' => $rp['id']]);
        }
    }

    /** Eksekusi transisi atomik + history + activity log; gagal -> flash & kembali. */
    private function doTransition(array $rp, array $from, string $to, string $action, array $extra, string $historyNote, string $logNote): void
    {
        $id = (int) $rp['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            if (!$this->rpModel->transition($id, $from, $to, $extra)) {
                $pdo->rollBack();
                setFlash('error', 'Status Request PO sudah berubah (kemungkinan diproses user lain). Muat ulang halaman.');
                $this->redirect('request_po', 'detail', ['id' => $id]);
            }
            $this->rpModel->addHistory($id, currentUserId(), $action, $rp['status'], $to, $historyNote);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestPo transition error: ' . $e->getMessage());
            setFlash('error', 'Gagal memproses Request PO.');
            $this->redirect('request_po', 'detail', ['id' => $id]);
        }
        $this->logAct($action, $id, $rp['request_po_number'], $logNote);
    }

    private function logAct(string $action, int $id, string $number, string $note): void
    {
        $this->activityLog->log(currentUserId(), 'request_po', $action, "Request PO {$number} (#{$id}) {$note}");
    }

    /** Nama pemegang izin approve (ditampilkan sebagai "Approver" di detail). */
    private function approverNames(): array
    {
        $names = [];
        require_once ROOT_PATH . '/app/models/User.php';
        $ids = $this->rpModel->userIdsWithPermission('approve');
        foreach ((new User())->activeListWithRole() as $u) {
            if (in_array((int) $u['id'], $ids, true) && $u['role_slug'] !== ROLE_SUPER_ADMIN) {
                $names[] = $u['full_name'];
            }
        }
        return $names;
    }

    /** Push ke pemegang izin approve (best-effort, informasi saja, tidak boleh menggagalkan alur). */
    private function notifyApprovers(string $number, int $id): void
    {
        try {
            $ids = array_values(array_diff($this->rpModel->userIdsWithPermission('approve'), [(int) currentUserId()]));
            if ($ids) {
                sendPushToUsers($ids, "Request PO {$number} menunggu approval",
                    'Disubmit oleh ' . currentUserName(), route('request_po', 'detail', ['id' => $id]), 'request_po.view');
            }
        } catch (Throwable $e) {
            error_log('RequestPo push gagal: ' . $e->getMessage());
        }
    }

    private function collectInput(): array
    {
        $names = (array) ($_POST['item_name'] ?? []);
        $qtys = (array) ($_POST['qty'] ?? []);
        $rows = [];
        foreach ($names as $i => $name) {
            if (!is_scalar($name)) {
                continue;
            }
            $rows[] = [
                'item_name' => (string) $name,
                'qty'       => parseQtyInput(is_scalar($qtys[$i] ?? null) ? $qtys[$i] : 0),
            ];
        }
        $str = fn(string $k) => is_scalar($_POST[$k] ?? null) ? trim((string) $_POST[$k]) : '';
        return [
            'request_date'   => $str('request_date'),
            'requester_name' => $str('requester_name'),
            'project_id'     => (int) ($_POST['project_id'] ?? 0),
            'purpose'        => $str('purpose'),
            'notes'          => $str('notes'),
            'items'          => $rows,
        ];
    }

    private function validateInput(array $d): array
    {
        $errors = [];
        if ($d['request_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['request_date']) || strtotime($d['request_date']) === false) {
            $errors[] = 'Tanggal request wajib diisi dengan benar.';
        }
        if ($d['requester_name'] === '') {
            $errors[] = 'Requester wajib diisi.';
        } elseif (mb_strlen($d['requester_name']) > 100) {
            $errors[] = 'Nama requester maksimal 100 karakter.';
        }
        if ($d['project_id'] <= 0 || !(new Project())->find($d['project_id'])) {
            $errors[] = 'Project wajib dipilih.';
        }
        if ($d['purpose'] === '') {
            $errors[] = 'Keperluan wajib diisi.';
        } elseif (mb_strlen($d['purpose']) > 200) {
            $errors[] = 'Keperluan maksimal 200 karakter.';
        }
        $items = RequestPo::normalizeItems($d['items']);
        if (!$items) {
            $errors[] = 'Minimal harus ada 1 barang.';
        }
        if (count($items) > RequestPo::MAX_ITEMS) {
            $errors[] = 'Maksimal ' . RequestPo::MAX_ITEMS . ' barang per Request PO.';
        }
        foreach ($items as $i => $it) {
            $n = $i + 1;
            if ($it['item_name'] === '') {
                $errors[] = "Barang #{$n}: nama barang wajib diisi.";
            } elseif (mb_strlen($it['item_name']) > 200) {
                $errors[] = "Barang #{$n}: nama barang maksimal 200 karakter.";
            }
            if ($it['qty'] <= 0) {
                $errors[] = "Barang #{$n}: qty harus lebih dari 0.";
            } elseif ($it['qty'] > RequestPo::MAX_QTY) {
                $errors[] = "Barang #{$n}: qty terlalu besar.";
            }
        }
        return $errors;
    }
}
