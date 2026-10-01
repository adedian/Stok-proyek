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
                $this->rbModel->createApprovals($id);
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
            'approvals' => $this->rbModel->approvals((int) $rb['id']),
            'pos'       => $this->rbModel->pos((int) $rb['id']),
            'invoices'  => $this->rbModel->invoices((int) $rb['id']),
            'attachments' => $this->rbModel->attachments((int) $rb['id']),
            'forwardProblems' => $this->rbModel->purchaseDataProblems((int) $rb['id']),
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
                    $this->rbModel->createApprovals($id);
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
        $this->rbModel->createApprovals((int) $rb['id']);
        $this->notify('approve', "Request Budget {$rb['request_number']} menunggu approval",
            'Diajukan oleh ' . currentUserName() . ' -- Rp ' . number_format((float) $rb['total_amount'], 0, ',', '.'), (int) $rb['id']);
        setFlash('success', 'Request Budget diajukan dan menunggu approval.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /**
     * Approval per SLOT: 'pm' (Project Manager, mis. Vicky) dan 'purchase' (mis. Andy), disimpan
     * terpisah di request_budget_approvals. User hanya bisa mengisi slot sesuai role-nya;
     * Super Admin mengisi semua slot yang masih pending. Request baru berstatus APPROVED
     * kalau KEDUANYA sudah APPROVED -- sebelum itu tetap "Menunggu Approval".
     */
    public function approve()
    {
        Middleware::requirePermission('request_budget', 'approve');
        $rb = $this->startAction(false);
        $this->assertNotSelfApproval($rb);
        $this->assertStatus($rb, [RequestBudget::PENDING_APPROVAL], "Hanya Request Budget 'Menunggu Approval' yang bisa disetujui.");
        $slots = $this->mySlots((int) $rb['id'], $rb);
        // Urutan wajib: Purchase (Andy) baru boleh menyetujui SETELAH Project Manager (Vicky) menyetujui.
        // (Super Admin yang mengisi dua slot sekaligus tetap diproses berurutan: PM dulu, lalu Purchase.)
        $pmDone = ($this->rbModel->approvals((int) $rb['id'])['pm']['status'] ?? '') === 'APPROVED';
        if (in_array('purchase', $slots, true) && !$pmDone && !in_array('pm', $slots, true)) {
            setFlash('error', 'Approval Purchase baru bisa dilakukan setelah Project Manager menyetujui Request Budget ini.');
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        $note = trim($_POST['note'] ?? '') ?: null;
        $role = $this->rbModel->roleNameOf(currentUserId());
        $id = (int) $rb['id'];

        $pdo = getPDO();
        $allDone = false;
        try {
            $pdo->beginTransaction();
            $n = $this->rbModel->actApprovals($id, $slots, 'APPROVED', currentUserId(), $role, $note);
            if ($n === 0) {
                $pdo->rollBack();
                setFlash('error', 'Approval Anda sudah tercatat atau status sudah berubah. Muat ulang halaman.');
                $this->redirect('request_budget', 'detail', ['id' => $id]);
            }
            $this->rbModel->addHistory($id, currentUserId(), 'approve', $rb['status'], RequestBudget::PENDING_APPROVAL,
                'Approval ' . implode(' & ', array_map(fn($k) => RequestBudget::APPROVAL_SLOTS[$k]['label'], $slots)) . ' oleh ' . currentUserName() . ' (' . $role . ')' . ($note ? ': ' . $note : ''));
            if (!$this->rbModel->pendingApprovalSlots($id)) {
                $allDone = $this->rbModel->transition($id, [RequestBudget::PENDING_APPROVAL], RequestBudget::APPROVED,
                    ['approved_by' => currentUserId(), 'approved_at' => date('Y-m-d H:i:s'), 'approved_by_role' => $role]);
                if ($allDone) {
                    $this->rbModel->addHistory($id, currentUserId(), 'approved_all', RequestBudget::PENDING_APPROVAL, RequestBudget::APPROVED, 'Seluruh approval selesai -- menunggu proses Purchase');
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestBudget approve error: ' . $e->getMessage());
            setFlash('error', 'Gagal memproses approval.');
            $this->redirect('request_budget', 'detail', ['id' => $id]);
        }
        $this->logAct('approve', $id, $rb['request_number'], 'disetujui oleh ' . currentUserName() . ' (' . $role . ')' . ($allDone ? ' -- seluruh approval selesai' : ' -- menunggu approval lain'));
        if ($allDone) {
            $this->notifyUsers([(int) $rb['requester_user_id']], "Request Budget {$rb['request_number']} telah disetujui", 'Seluruh approval selesai', $id);
            $this->notify('purchase_process', "Request Budget {$rb['request_number']} menunggu proses Purchase",
                'Approval selesai -- lengkapi PO/Invoice lalu ajukan', $id, [(int) $rb['requester_user_id']]);
            setFlash('success', 'Approval Anda tercatat. Seluruh approval selesai -- Request Budget menunggu proses Purchase.');
        } else {
            setFlash('success', 'Approval Anda tercatat. Request Budget masih menunggu approval pihak lain.');
        }
        $this->redirect('request_budget', 'detail', ['id' => $id]);
    }

    /** Slot approval yang akan diisi user ini (anti-IDOR/anti-tebakan: dihitung server dari role + status slot). */
    private function mySlots(int $id, array $rb): array
    {
        $mine = $this->rbModel->approvalSlotForCurrentUser();
        $pending = $this->rbModel->pendingApprovalSlots($id);
        if ($mine === null) {
            $this->deny($rb, 'memberi approval tanpa slot approval');
        }
        $slots = $mine === 'all' ? $pending : array_values(array_intersect([$mine], $pending));
        // urut sesuai alur approval: pm -> purchase
        $slots = array_values(array_intersect(array_keys(RequestBudget::APPROVAL_SLOTS), $slots));
        if (!$slots) {
            setFlash('error', 'Tidak ada approval yang menunggu Anda pada Request Budget ini.');
            $this->redirect('request_budget', 'detail', ['id' => $id]);
        }
        return $slots;
    }

    /** Pemilik izin reject (pada slot yang masih menunggunya): PENDING_APPROVAL -> REJECTED (alasan wajib) */
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
        $id = (int) $rb['id'];
        $slots = $this->mySlots($id, $rb);
        $role = $this->rbModel->roleNameOf(currentUserId());
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            if ($this->rbModel->actApprovals($id, $slots, 'REJECTED', currentUserId(), $role, $reason) === 0
                || !$this->rbModel->transition($id, [RequestBudget::PENDING_APPROVAL], RequestBudget::REJECTED,
                    ['rejected_by' => currentUserId(), 'rejected_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $reason])) {
                $pdo->rollBack();
                setFlash('error', 'Status Request Budget sudah berubah. Muat ulang halaman.');
                $this->redirect('request_budget', 'detail', ['id' => $id]);
            }
            $this->rbModel->addHistory($id, currentUserId(), 'reject', $rb['status'], RequestBudget::REJECTED,
                'Ditolak oleh ' . currentUserName() . ' (' . $role . '): ' . $reason);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RequestBudget reject error: ' . $e->getMessage());
            setFlash('error', 'Gagal menolak Request Budget.');
            $this->redirect('request_budget', 'detail', ['id' => $id]);
        }
        $this->logAct('reject', $id, $rb['request_number'], 'ditolak: ' . $reason);
        $this->notifyUsers([(int) $rb['requester_user_id']], "Request Budget {$rb['request_number']} ditolak", $reason, $id);
        setFlash('success', 'Request Budget ditolak.');
        $this->redirect('request_budget', 'detail', ['id' => $id]);
    }

    /** Pengaju: REJECTED -> DRAFT (revisi). Approval lama dihapus; submit ulang membuat slot baru. */
    public function revise()
    {
        Middleware::requirePermission('request_budget', 'edit');
        $rb = $this->startAction(true);
        $this->assertStatus($rb, [RequestBudget::REJECTED], 'Hanya Request Budget yang ditolak yang bisa direvisi.');
        $this->doTransition($rb, [RequestBudget::REJECTED], RequestBudget::DRAFT, 'revise', [
            'approved_by' => null, 'approved_at' => null, 'approved_by_role' => null, 'submitted_at' => null,
        ], 'Dibuka kembali untuk revisi', 'dibuka untuk revisi');
        $this->rbModel->clearApprovals((int) $rb['id']);
        setFlash('success', 'Request Budget dikembalikan ke Draft. Silakan revisi lalu ajukan kembali.');
        $this->redirect('request_budget', 'edit', ['id' => $rb['id']]);
    }

    // =================== Proses Purchase (Andy / Super Admin) ===================

    /** Tambah PO (nomor, tanggal, vendor, nominal, file). Hanya saat APPROVED (menunggu proses Purchase). */
    public function addPo()
    {
        $rb = $this->purchaseStart();
        $number = trim($_POST['po_number'] ?? '');
        $date = trim($_POST['po_date'] ?? '');
        $this->requireDocFields($rb, [[$number !== '', 'Nomor PO wajib diisi.'], [$this->validDate($date), 'Tanggal PO wajib diisi dengan benar.']]);
        $file = $this->uploadDoc($rb, false);
        $docId = $this->rbModel->addDoc('po', (int) $rb['id'], [
            'po_number' => mb_substr($number, 0, 80), 'po_date' => $date,
            'vendor_name' => mb_substr(trim($_POST['vendor_name'] ?? ''), 0, 200) ?: null,
            'amount' => parseCurrencyInput($_POST['amount'] ?? 0), 'file_path' => $file, 'created_by' => currentUserId(),
        ]);
        $this->rbModel->addHistory((int) $rb['id'], currentUserId(), 'add_po', $rb['status'], $rb['status'], "PO {$number} ditambahkan");
        $this->logAct('add_po', (int) $rb['id'], $rb['request_number'], "PO {$number} ditambahkan");
        setFlash('success', 'PO ditambahkan.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    public function addInvoice()
    {
        $rb = $this->purchaseStart();
        $number = trim($_POST['invoice_number'] ?? '');
        $date = trim($_POST['invoice_date'] ?? '');
        $this->requireDocFields($rb, [[$number !== '', 'Nomor Invoice wajib diisi.'], [$this->validDate($date), 'Tanggal Invoice wajib diisi dengan benar.']]);
        $file = $this->uploadDoc($rb, false);
        $this->rbModel->addDoc('invoice', (int) $rb['id'], [
            'invoice_number' => mb_substr($number, 0, 80), 'invoice_date' => $date,
            'vendor_name' => mb_substr(trim($_POST['vendor_name'] ?? ''), 0, 200) ?: null,
            'amount' => parseCurrencyInput($_POST['amount'] ?? 0), 'file_path' => $file, 'created_by' => currentUserId(),
        ]);
        $this->rbModel->addHistory((int) $rb['id'], currentUserId(), 'add_invoice', $rb['status'], $rb['status'], "Invoice {$number} ditambahkan");
        $this->logAct('add_invoice', (int) $rb['id'], $rb['request_number'], "Invoice {$number} ditambahkan");
        setFlash('success', 'Invoice ditambahkan.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /** Dokumen pendukung lain: nama, keterangan, file WAJIB. Boleh lebih dari satu. */
    public function addAttachment()
    {
        $rb = $this->purchaseStart();
        $name = trim($_POST['doc_name'] ?? '');
        $this->requireDocFields($rb, [[$name !== '', 'Nama dokumen wajib diisi.']]);
        $file = $this->uploadDoc($rb, true);
        $this->rbModel->addDoc('attachment', (int) $rb['id'], [
            'doc_name' => mb_substr($name, 0, 200), 'description' => mb_substr(trim($_POST['description'] ?? ''), 0, 500) ?: null,
            'file_path' => $file, 'created_by' => currentUserId(),
        ]);
        $this->rbModel->addHistory((int) $rb['id'], currentUserId(), 'add_attachment', $rb['status'], $rb['status'], "Dokumen pendukung '{$name}' ditambahkan");
        $this->logAct('add_attachment', (int) $rb['id'], $rb['request_number'], "dokumen pendukung '{$name}' ditambahkan");
        setFlash('success', 'Dokumen pendukung ditambahkan.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    public function deleteDoc()
    {
        $rb = $this->purchaseStart();
        $type = (string) ($_POST['type'] ?? '');
        $docId = (int) ($_POST['doc_id'] ?? 0);
        // findDoc membatasi ke request ini -> tidak bisa menghapus dokumen request lain lewat doc_id.
        $doc = $this->rbModel->findDoc($type, $docId, (int) $rb['id']);
        if (!$doc) {
            setFlash('error', 'Dokumen tidak ditemukan.');
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        $this->rbModel->deleteDoc($type, $docId, (int) $rb['id']);
        $this->removeUpload($doc['file_path'] ?? null);
        $this->rbModel->addHistory((int) $rb['id'], currentUserId(), 'delete_doc', $rb['status'], $rb['status'], 'Dokumen (' . $type . ') dihapus');
        $this->logAct('delete_doc', (int) $rb['id'], $rb['request_number'], "dokumen {$type} #{$docId} dihapus");
        setFlash('success', 'Dokumen dihapus.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /** APPROVED -> PURCHASE_COMPLETED ("Dilengkapi Andy"): data wajib harus lengkap. */
    public function purchaseComplete()
    {
        Middleware::requirePermission('request_budget', 'purchase_process');
        $rb = $this->startAction(false);
        $this->assertStatus($rb, [RequestBudget::APPROVED], 'Request Budget harus berstatus Disetujui (menunggu proses Purchase).');
        $problems = $this->rbModel->purchaseDataProblems((int) $rb['id']);
        if ($problems) {
            setFlash('error', 'Data Purchase belum lengkap: ' . implode(' ', $problems));
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        $this->doTransition($rb, [RequestBudget::APPROVED], RequestBudget::PURCHASE_COMPLETED, 'purchase_complete', [
            'purchase_completed_by' => currentUserId(), 'purchase_completed_at' => date('Y-m-d H:i:s'),
            'purchase_notes' => trim($_POST['purchase_notes'] ?? '') ?: null,
        ], 'Data Purchase dilengkapi oleh ' . currentUserName(), 'data Purchase dilengkapi');
        setFlash('success', 'Data Purchase ditandai lengkap. Request Budget siap diajukan ke Purwati/Nissa.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /** PURCHASE_COMPLETED -> APPROVED: buka kembali untuk menambah/mengubah dokumen. */
    public function purchaseReopen()
    {
        Middleware::requirePermission('request_budget', 'purchase_process');
        $rb = $this->startAction(false);
        $this->assertStatus($rb, [RequestBudget::PURCHASE_COMPLETED], "Hanya Request Budget 'Dilengkapi' yang bisa dibuka kembali.");
        $this->doTransition($rb, [RequestBudget::PURCHASE_COMPLETED], RequestBudget::APPROVED, 'purchase_reopen',
            ['purchase_completed_by' => null, 'purchase_completed_at' => null],
            'Dibuka kembali untuk melengkapi data Purchase', 'dibuka kembali untuk proses Purchase');
        setFlash('success', 'Request Budget dibuka kembali untuk proses Purchase.');
        $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
    }

    /**
     * Teruskan ke Purwati/Nissa: PURCHASE_COMPLETED -> FORWARDED.
     * Syarat (semua dicek di SERVER): izin 'forward' (Andy / Super Admin -- Vicky TIDAK punya),
     * KEDUA approval = APPROVED, data Purchase lengkap, tujuan valid.
     */
    public function forward()
    {
        Middleware::requirePermission('request_budget', 'forward');
        $rb = $this->startAction(false);
        $this->assertStatus($rb, [RequestBudget::PURCHASE_COMPLETED], "Request Budget harus berstatus 'Dilengkapi' sebelum diajukan ke Purwati/Nissa.");
        $id = (int) $rb['id'];
        $approvals = $this->rbModel->approvals($id);
        foreach (array_keys(RequestBudget::APPROVAL_SLOTS) as $slot) {
            if (($approvals[$slot]['status'] ?? '') !== 'APPROVED') {
                setFlash('error', 'Tidak bisa diajukan: ' . RequestBudget::APPROVAL_SLOTS[$slot]['label'] . ' belum selesai.');
                $this->redirect('request_budget', 'detail', ['id' => $id]);
            }
        }
        $problems = $this->rbModel->purchaseDataProblems($id);
        if ($problems) {
            setFlash('error', 'Data Purchase belum lengkap: ' . implode(' ', $problems));
            $this->redirect('request_budget', 'detail', ['id' => $id]);
        }
        $dest = trim($_POST['forward_to'] ?? '');
        if (!in_array($dest, RequestBudget::FORWARD_DESTINATIONS, true)) {
            setFlash('error', 'Pilih tujuan pengajuan: ' . implode(' / ', RequestBudget::FORWARD_DESTINATIONS) . '.');
            $this->redirect('request_budget', 'detail', ['id' => $id]);
        }
        $role = $this->rbModel->roleNameOf(currentUserId());
        $this->doTransition($rb, [RequestBudget::PURCHASE_COMPLETED], RequestBudget::FORWARDED, 'forward',
            ['forwarded_by' => currentUserId(), 'forwarded_at' => date('Y-m-d H:i:s'), 'forwarded_to' => $dest, 'forwarded_by_role' => $role],
            'Diajukan ke ' . $dest . ' oleh ' . currentUserName() . ' (' . $role . ')', 'diajukan ke ' . $dest);
        $this->notifyUsers([(int) $rb['requester_user_id'], (int) $rb['approved_by']],
            "Request Budget {$rb['request_number']} diajukan ke {$dest}", 'Diajukan oleh ' . currentUserName(), $id);
        setFlash('success', "Request Budget diajukan ke {$dest}.");
        $this->redirect('request_budget', 'detail', ['id' => $id]);
    }

    /** Awal aksi proses Purchase: izin + CSRF + scope + status APPROVED (satu-satunya tahap yang boleh mengubah dokumen). */
    private function purchaseStart(): array
    {
        Middleware::requirePermission('request_budget', 'purchase_process');
        $rb = $this->startAction(false);
        $this->assertStatus($rb, [RequestBudget::APPROVED], 'Dokumen hanya bisa diubah saat Request Budget berstatus Disetujui (menunggu proses Purchase).');
        return $rb;
    }

    private function validDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    }

    /** @param array $rules  daftar [kondisi-valid, pesan] -- ada yang gagal -> flash + kembali */
    private function requireDocFields(array $rb, array $rules): void
    {
        $errors = [];
        foreach ($rules as [$ok, $msg]) {
            if (!$ok) {
                $errors[] = $msg;
            }
        }
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
    }

    private function uploadDoc(array $rb, bool $required): ?string
    {
        try {
            $path = handleFileUpload('file', 'request_budget', ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 10);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        if ($required && !$path) {
            setFlash('error', 'File dokumen wajib diunggah.');
            $this->redirect('request_budget', 'detail', ['id' => $rb['id']]);
        }
        return $path ?: null;
    }

    private function removeUpload(?string $rel): void
    {
        if (!$rel || !preg_match('#^uploads/request_budget/[a-f0-9]{32}\.(jpg|jpeg|png|webp|pdf)$#', $rel)) {
            return;
        }
        $full = realpath(UPLOAD_PATH . '/' . substr($rel, strlen('uploads/')));
        $base = realpath(UPLOAD_PATH);
        if ($full && $base && strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) === 0 && is_file($full)) {
            @unlink($full);
        }
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
            // Tombol approve/tolak hanya muncul kalau SLOT milik user ini masih menunggu.
            $mine = $this->rbModel->approvalSlotForCurrentUser();
            $apps = $this->rbModel->approvals((int) $rb['id']);
            $myPending = $mine === 'all'
                ? (bool) array_filter($apps, fn($x) => $x['status'] === 'PENDING')
                : ($mine !== null && ($apps[$mine]['status'] ?? '') === 'PENDING');
            // Slot Purchase baru aktif setelah slot PM approved (Super Admin dikecualikan: mengisi berurutan).
            $purchaseWaitsPm = $mine === 'purchase' && ($apps['pm']['status'] ?? '') !== 'APPROVED';
            if ($myPending && !$purchaseWaitsPm && can('request_budget', 'approve')) {
                $a['approve'] = true;
            }
            if ($myPending && can('request_budget', 'reject')) {
                $a['reject'] = true;
            }
        }
        // Proses Purchase (PO/Invoice/Dokumen): hanya 'purchase_process' (Andy/Super Admin), BUKAN Vicky.
        if (can('request_budget', 'purchase_process')) {
            if ($s === RequestBudget::APPROVED) {
                $a['purchase_edit'] = true;
                $a['purchase_complete'] = true;
            }
            if ($s === RequestBudget::PURCHASE_COMPLETED) {
                $a['purchase_reopen'] = true;
            }
        }
        // Teruskan ke Purwati/Nissa: hanya pemilik izin 'forward' (Andy/Super Admin), setelah data dilengkapi.
        if (can('request_budget', 'forward') && $s === RequestBudget::PURCHASE_COMPLETED) {
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
