<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Reimbursement.php';
require_once ROOT_PATH . '/app/models/PengajuanBudget.php';
require_once ROOT_PATH . '/app/models/MasterRekeningTujuan.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Reimbursement ("Laporan Biaya Reimburse") -- modul terpisah dengan form, daftar, detail, status, riwayat.
 *
 * AKSES (semua ditegakkan di backend):
 *   - Semua akun yang punya izin 'reimbursement' boleh membuat pengajuan dan HANYA melihat/mengubah pengajuannya sendiri.
 *   - Nissa & Purwati (izin per akun 'view_all' + 'review') melihat SEMUA pengajuan dan memeriksanya.
 *   - Setujui/Tolak hanya untuk pemegang 'review'. Persetujuan SALAH SATU akun cukup; keputusan hanya bisa terjadi
 *     SEKALI (UPDATE bersyarat status 'menunggu_pemeriksaan' + UNIQUE pengajuan_budget_id/source di DB).
 *
 * PENERUSAN: Setujui dengan kurang bayar (pengeluaran - kasbon > 0) membuat Pengajuan Budget (Draft) lewat modul
 * Pengajuan Budget yang sudah ada, dalam SATU transaksi DB bersama perubahan status reimbursement; gagal -> semuanya rollback
 * dan reimbursement tetap Menunggu Pemeriksaan. Alur Pengajuan Budget itu sendiri tidak diubah.
 */
class ReimbursementController extends Controller
{
    private Reimbursement $model;
    private ActivityLog $activityLog;

    private const MAX_ROWS = 200;
    private const PROOF_EXT = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public function __construct()
    {
        Middleware::requirePermission('reimbursement', 'view');
        $this->model = new Reimbursement();
        $this->activityLog = new ActivityLog();
    }

    // =================== Daftar ===================

    public function index()
    {
        $valid = fn($v) => (is_string($v) && isValidDateString($v)) ? $v : '';
        $filters = [
            'number'    => is_string($_GET['number'] ?? null) ? trim($_GET['number']) : '',
            'status'    => is_string($_GET['status'] ?? null) ? trim($_GET['status']) : '',
            'date_from' => $valid($_GET['date_from'] ?? ''),
            'date_to'   => $valid($_GET['date_to'] ?? ''),
        ];
        $only = can('reimbursement', 'view_all') ? null : (int) currentUserId();
        $total = $this->model->countFiltered($filters, $only);
        $pg = paginationInfo($total, (int) ($_GET['page'] ?? 1));

        $this->view('reimbursement/list', [
            'pageTitle'  => 'Reimbursement',
            'rows'       => $this->model->listPaginated($filters, $only, $pg['perPage'], $pg['offset']),
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter(['module' => 'reimbursement'] + $filters)),
            'statuses'   => Reimbursement::STATUS_LABELS,
            'viewAll'    => $only === null,
        ]);
    }

    // =================== Tambah ===================

    public function create()
    {
        Middleware::requirePermission('reimbursement', 'create');
        $this->renderForm('create', null, [], null);
    }

    public function store()
    {
        Middleware::requirePermission('reimbursement', 'create');
        $this->requirePost();

        [$data, $items, $errors] = $this->collectAndValidate([]);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderForm('create', null, $items, $data);
            return;
        }

        $uploaded = [];
        try {
            $items = $this->applyProofs($items, [], $uploaded);
        } catch (RuntimeException $e) {
            $this->cleanup($uploaded);
            setFlash('error', $e->getMessage());
            $this->renderForm('create', null, $items, $data);
            return;
        }

        $submitNow = !empty($_POST['submit_after']) && can('reimbursement', 'submit');
        $status = $submitNow ? Reimbursement::MENUNGGU : Reimbursement::DRAFT;
        $t = Reimbursement::totals(array_column($items, 'amount'), $data['kasbon_amount']);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $number = $this->model->nextNumber($data['reimb_date']);
            $id = $this->model->create([
                'reimb_number'    => $number,
                'reimb_date'      => $data['reimb_date'],
                'employee_name'   => $data['employee_name'],
                'division'        => $data['division'] ?: null,
                'city'            => $data['city'] ?: null,
                'task'            => $data['task'] ?: null,
                'kasbon_amount'   => $t['kasbon'],
                'total_expense'   => $t['total'],
                'net_amount'      => $t['net'],
                'status'          => $status,
                'submitted_at'    => $submitNow ? date('Y-m-d H:i:s') : null,
                'created_by'      => (int) currentUserId(),
            ]);
            $this->model->replaceItems($id, $items);
            $this->model->addHistory($id, currentUserId(), 'create', null, Reimbursement::DRAFT,
                "Reimbursement {$number} dibuat (" . count($items) . ' baris, total pengeluaran ' . formatRupiah($t['total']) . ($t['kasbon'] > 0 ? ', kasbon ' . formatRupiah($t['kasbon']) : '') . ')');
            if ($submitNow) {
                $this->model->addHistory($id, currentUserId(), 'submit', Reimbursement::DRAFT, $status, 'Diajukan untuk diperiksa (Nissa / Purwati)');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->cleanup($uploaded);
            error_log('Reimbursement store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Reimbursement. Silakan coba lagi.');
            $this->renderForm('create', null, $items, $data);
            return;
        }

        $this->logAct('create', $id, $number, 'dibuat (' . Reimbursement::statusLabel($status) . ')');
        setFlash('success', "Reimbursement {$number} berhasil disimpan" . ($submitNow ? ' dan diajukan untuk diperiksa.' : ' sebagai Draft.'));
        $this->redirect('reimbursement', 'detail', ['id' => $id]);
    }

    // =================== Detail ===================

    public function detail()
    {
        $r = $this->load((int) ($_GET['id'] ?? 0));
        $id = (int) $r['id'];
        $canReviewNow = $r['status'] === Reimbursement::MENUNGGU && can('reimbursement', 'review');
        $pbModel = new PengajuanBudget();
        $this->view('reimbursement/detail', [
            'pageTitle' => 'Detail Reimbursement',
            'r'         => $r,
            'items'     => $this->model->items($id),
            'history'   => $this->model->history($id),
            'actions'   => $this->availableActions($r),
            // opsi dialog Setujui (data penerusan ke Pengajuan Budget): hanya dimuat untuk pemeriksa
            'banks'     => $canReviewNow ? $pbModel->sourceBankOptions() : [],
            'jenisList' => $canReviewNow ? $pbModel->jenisTransaksiOptions() : [],
            'tujuanList' => $canReviewNow ? (new MasterRekeningTujuan())->activeList() : [],
        ]);
    }

    // =================== Edit (Draft milik sendiri) ===================

    public function edit()
    {
        Middleware::requirePermission('reimbursement', 'edit');
        $r = $this->load((int) ($_GET['id'] ?? 0));
        $this->requireEditable($r);
        $this->renderForm('edit', $r, $this->model->items((int) $r['id']), null);
    }

    public function update()
    {
        Middleware::requirePermission('reimbursement', 'edit');
        $this->requirePost();
        $r = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $r['id'];
        $this->requireEditable($r);

        $oldItems = $this->model->items($id);
        [$data, $items, $errors] = $this->collectAndValidate($oldItems);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderForm('edit', $r, $items, $data);
            return;
        }

        $uploaded = [];
        $toDelete = [];
        try {
            $items = $this->applyProofs($items, $oldItems, $uploaded, $toDelete);
        } catch (RuntimeException $e) {
            $this->cleanup($uploaded);
            setFlash('error', $e->getMessage());
            $this->renderForm('edit', $r, $items, $data);
            return;
        }

        $submitNow = !empty($_POST['submit_after']) && can('reimbursement', 'submit');
        $t = Reimbursement::totals(array_column($items, 'amount'), $data['kasbon_amount']);
        $oldT = Reimbursement::totals(array_column($oldItems, 'amount'), $r['kasbon_amount']);

        $changes = [];
        foreach (['reimb_date' => 'tanggal', 'employee_name' => 'nama', 'division' => 'divisi', 'city' => 'kota', 'task' => 'tugas'] as $k => $label) {
            if ((string) ($r[$k] ?? '') !== (string) ($data[$k] ?? '')) {
                $changes[] = "{$label} diubah";
            }
        }
        if (round((float) $r['kasbon_amount'], 2) !== $t['kasbon']) {
            $changes[] = 'kasbon ' . formatRupiah($r['kasbon_amount']) . ' -> ' . formatRupiah($t['kasbon']);
        }
        $sig = fn(array $x) => implode('|', [$x['expense_date'], (int) ($x['category_id'] ?? 0), $x['description'], number_format((float) $x['amount'], 2, '.', ''), (string) ($x['proof_file'] ?? '')]);
        if (array_map($sig, $oldItems) !== array_map($sig, $items)) {
            $changes[] = 'baris diubah (' . count($oldItems) . ' -> ' . count($items) . ' baris, total ' . formatRupiah($oldT['total']) . ' -> ' . formatRupiah($t['total']) . ')';
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT status FROM reimbursements WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
            $lock->execute(['id' => $id]);
            if ($lock->fetchColumn() !== Reimbursement::DRAFT) {
                throw new RuntimeException('status-berubah');
            }
            $this->model->updateById($id, [
                'reimb_date'    => $data['reimb_date'],
                'employee_name' => $data['employee_name'],
                'division'      => $data['division'] ?: null,
                'city'          => $data['city'] ?: null,
                'task'          => $data['task'] ?: null,
                'kasbon_amount' => $t['kasbon'],
                'total_expense' => $t['total'],
                'net_amount'    => $t['net'],
            ]);
            $this->model->replaceItems($id, $items);
            $this->model->addHistory($id, currentUserId(), 'update', Reimbursement::DRAFT, Reimbursement::DRAFT,
                $changes ? 'Diubah: ' . implode('; ', $changes) : 'Disimpan tanpa perubahan data');
            if ($submitNow) {
                if (!$this->model->transition($id, [Reimbursement::DRAFT], Reimbursement::MENUNGGU, ['submitted_at' => date('Y-m-d H:i:s')])) {
                    throw new RuntimeException('status-berubah');
                }
                $this->model->addHistory($id, currentUserId(), 'submit', Reimbursement::DRAFT, Reimbursement::MENUNGGU, 'Diajukan untuk diperiksa (Nissa / Purwati)');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $this->cleanup($uploaded);
            if ($e->getMessage() === 'status-berubah') {
                setFlash('error', 'Status reimbursement sudah berubah, perubahan tidak disimpan.');
                $this->redirect('reimbursement', 'detail', ['id' => $id]);
            }
            error_log('Reimbursement update error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan perubahan. Silakan coba lagi.');
            $this->renderForm('edit', $r, $items, $data);
            return;
        }
        $this->cleanup($toDelete); // bukti lama yang diganti/dihapus -- baru dibuang SETELAH commit sukses

        $this->logAct('update', $id, $r['reimb_number'], $changes ? implode('; ', $changes) : 'disimpan tanpa perubahan');
        if ($submitNow) {
            $this->logAct('submit', $id, $r['reimb_number'], 'diajukan');
        }
        setFlash('success', "Reimbursement {$r['reimb_number']} berhasil diperbarui" . ($submitNow ? ' dan diajukan untuk diperiksa.' : '.'));
        $this->redirect('reimbursement', 'detail', ['id' => $id]);
    }

    // =================== Aksi status ===================

    /** Draft -> Menunggu Pemeriksaan (hanya pengaju). */
    public function submit()
    {
        Middleware::requirePermission('reimbursement', 'submit');
        $this->requirePost();
        $r = $this->load((int) ($_POST['id'] ?? 0));
        $this->requireOwner($r);
        if (Reimbursement::totals(array_column($this->model->items((int) $r['id']), 'amount'), 0)['total'] <= 0) {
            setFlash('error', 'Reimbursement tanpa baris pengeluaran tidak bisa diajukan.');
            $this->redirect('reimbursement', 'detail', ['id' => $r['id']]);
        }
        $this->doTransition($r, [Reimbursement::DRAFT], Reimbursement::MENUNGGU, 'submit',
            'Diajukan untuk diperiksa (Nissa / Purwati)', ['submitted_at' => date('Y-m-d H:i:s')], 'diajukan untuk diperiksa');
    }

    /** Ditolak -> Draft supaya pengaju bisa merevisi (catatan penolakan tetap tersimpan di riwayat). */
    public function revise()
    {
        Middleware::requirePermission('reimbursement', 'edit');
        $this->requirePost();
        $r = $this->load((int) ($_POST['id'] ?? 0));
        $this->requireOwner($r);
        $this->doTransition($r, [Reimbursement::DITOLAK], Reimbursement::DRAFT, 'revise',
            'Dikembalikan ke Draft untuk direvisi (penolakan sebelumnya: ' . ($r['review_note'] ?: '-') . ')',
            ['submitted_at' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'review_decision' => null, 'review_note' => null],
            'dikembalikan ke Draft untuk direvisi');
    }

    /** Tolak (pemeriksa; catatan WAJIB). Tidak membuat Pengajuan Budget. */
    public function reject()
    {
        Middleware::requirePermission('reimbursement', 'review');
        $this->requirePost();
        $r = $this->load((int) ($_POST['id'] ?? 0));
        $note = is_string($_POST['review_note'] ?? null) ? trim($_POST['review_note']) : '';
        if (mb_strlen($note) < 3 || mb_strlen($note) > 500) {
            setFlash('error', 'Alasan penolakan wajib diisi (3-500 karakter).');
            $this->redirect('reimbursement', 'detail', ['id' => $r['id']]);
        }
        $now = date('Y-m-d H:i:s');
        $this->doTransition($r, [Reimbursement::MENUNGGU], Reimbursement::DITOLAK, 'reject',
            'Ditolak oleh ' . currentUserName() . ': ' . $note,
            ['reviewed_by' => (int) currentUserId(), 'reviewed_at' => $now, 'review_decision' => 'tolak', 'review_note' => $note],
            'ditolak');
    }

    /**
     * Setujui (pemeriksa). Salah satu pemeriksa cukup; keputusan kedua tidak mungkin karena status bersyarat.
     * Kurang bayar > 0 -> buat Pengajuan Budget (Draft) di modul yang sudah ada, SATU transaksi DB.
     */
    public function approve()
    {
        Middleware::requirePermission('reimbursement', 'review');
        $this->requirePost();
        $r = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $r['id'];
        $back = fn() => $this->redirect('reimbursement', 'detail', ['id' => $id]);

        if ($r['status'] !== Reimbursement::MENUNGGU) {
            setFlash('error', $this->alreadyDecidedMessage($r));
            $back();
        }
        $items = $this->model->items($id);
        $t = Reimbursement::totals(array_column($items, 'amount'), $r['kasbon_amount']);
        $note = is_string($_POST['review_note'] ?? null) ? mb_substr(trim($_POST['review_note']), 0, 500) : '';

        // Data penerusan (hanya bila ada kurang bayar) -- divalidasi ke master di backend.
        $fwd = null;
        if ($t['net'] > 0) {
            [$fwd, $errs] = $this->collectForwardInput();
            if ($errs) {
                setFlash('error', implode(' ', $errs));
                $back();
            }
        }

        $pbModel = new PengajuanBudget();
        $pdo = getPDO();
        $now = date('Y-m-d H:i:s');
        $pbId = null;
        $pbNumber = null;
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT status, pengajuan_budget_id, total_expense, kasbon_amount FROM reimbursements WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
            $lock->execute(['id' => $id]);
            $cur = $lock->fetch();
            if (!$cur || $cur['status'] !== Reimbursement::MENUNGGU || $cur['pengajuan_budget_id'] !== null) {
                throw new RuntimeException('status-berubah');
            }
            // nominal dihitung ulang dari DATA TERKUNCI di DB (bukan dari form) -- harus sama dengan yang diperiksa
            if (round((float) $cur['total_expense'], 2) !== $t['total'] || round((float) $cur['kasbon_amount'], 2) !== $t['kasbon']) {
                throw new RuntimeException('data-berubah');
            }

            if ($fwd !== null) {
                $pbNumber = $pbModel->nextNumber(date('Y-m-d'));
                $pbId = $pbModel->create([
                    'pengajuan_number' => $pbNumber,
                    'pengajuan_date'   => date('Y-m-d'),
                    'notes'            => "Dari Reimbursement {$r['reimb_number']} a.n. {$r['employee_name']}",
                    'source_type'      => Reimbursement::SOURCE_TYPE,
                    'source_id'        => $id,
                    'status'           => PengajuanBudget::DRAFT,
                    'created_by'       => (int) currentUserId(),
                ]);
                $cats = array_values(array_unique(array_filter(array_map(fn($i) => (int) ($i['category_id'] ?? 0), $items))));
                $allCat = count($cats) === 1 && count(array_filter($items, fn($i) => empty($i['category_id']))) === 0 ? $cats[0] : null;
                $pbModel->replaceItems($pbId, [[
                    'source_bank_id'  => $fwd['source_bank_id'],
                    'jenis_transaksi' => $fwd['jenis_transaksi'],
                    'tujuan'          => $fwd['tujuan'],
                    'tujuan_id'       => $fwd['tujuan_id'],
                    'amount'          => $t['net'],
                    'keterangan'      => $this->forwardDescription($r, $items, $t),
                    'category_id'     => $allCat,
                    'project_id'      => null,
                ]]);
                $pbModel->addHistory($pbId, currentUserId(), 'create', null, PengajuanBudget::DRAFT,
                    "Dibuat otomatis dari Reimbursement {$r['reimb_number']} (disetujui " . currentUserName() . '), total ' . formatRupiah($t['net']));
            }

            $ok = $this->model->transition($id, [Reimbursement::MENUNGGU], $fwd !== null ? Reimbursement::DITERUSKAN : Reimbursement::DISETUJUI, [
                'reviewed_by' => (int) currentUserId(), 'reviewed_at' => $now, 'review_decision' => 'setuju',
                'review_note' => $note !== '' ? $note : null, 'pengajuan_budget_id' => $pbId,
            ]);
            if (!$ok) {
                throw new RuntimeException('status-berubah');
            }
            $this->model->addHistory($id, currentUserId(), 'approve', Reimbursement::MENUNGGU, $fwd !== null ? Reimbursement::DITERUSKAN : Reimbursement::DISETUJUI,
                'Disetujui oleh ' . currentUserName() . ($note !== '' ? ": {$note}" : ''));
            if ($fwd !== null) {
                $this->model->addHistory($id, currentUserId(), 'forward', null, null,
                    "Diteruskan ke Pengajuan Budget {$pbNumber} sebesar " . formatRupiah($t['net']) . ' (Rekening Sumber/Jenis/Tujuan dipilih pemeriksa)');
            } else {
                $this->model->addHistory($id, currentUserId(), 'forward_skip', null, null,
                    'Tidak diteruskan ke Pengajuan Budget: kasbon ' . formatRupiah($t['kasbon']) . ' >= total pengeluaran ' . formatRupiah($t['total']) . ' (tidak ada kurang bayar)');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getMessage() === 'status-berubah' || (string) $e->getCode() === '23000') {
                setFlash('error', 'Pengajuan ini sudah diputuskan pemeriksa lain / statusnya sudah berubah. Muat ulang halaman. Tidak ada Pengajuan Budget ganda yang dibuat.');
            } elseif ($e->getMessage() === 'data-berubah') {
                setFlash('error', 'Data reimbursement berubah saat diperiksa. Muat ulang halaman lalu periksa kembali.');
            } else {
                error_log('Reimbursement approve error: ' . $e->getMessage());
                setFlash('error', 'Gagal menyetujui & meneruskan ke Pengajuan Budget. Reimbursement tetap Menunggu Pemeriksaan; silakan coba lagi.');
            }
            $back();
        }

        $this->logAct('approve', $id, $r['reimb_number'], 'disetujui' . ($pbNumber ? " dan diteruskan ke Pengajuan Budget {$pbNumber}" : ' (tanpa kurang bayar)'));
        if ($pbNumber) {
            $this->activityLog->log(currentUserId(), 'pengajuan_budget', 'create', "Pengajuan Budget {$pbNumber} (#{$pbId}) dibuat otomatis dari Reimbursement {$r['reimb_number']}");
        }
        setFlash('success', $pbNumber
            ? "Reimbursement {$r['reimb_number']} disetujui dan diteruskan ke Pengajuan Budget {$pbNumber} (Draft)."
            : "Reimbursement {$r['reimb_number']} disetujui. Tidak ada kurang bayar, jadi tidak diteruskan ke Pengajuan Budget.");
        $back();
    }

    public function delete()
    {
        Middleware::requirePermission('reimbursement', 'delete');
        $this->requirePost();
        $r = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $r['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $ok = $this->model->softDeleteGuarded($id, currentUserId());
            if ($ok) {
                $this->model->addHistory($id, currentUserId(), 'delete', $r['status'], $r['status'], 'Reimbursement dihapus');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Reimbursement delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus reimbursement.');
            $this->redirect('reimbursement', 'detail', ['id' => $id]);
        }
        if (!$ok) {
            setFlash('error', 'Hanya reimbursement berstatus Draft atau Ditolak yang bisa dihapus. Yang sudah diajukan/diproses tersimpan permanen.');
            $this->redirect('reimbursement', 'detail', ['id' => $id]);
        }
        $this->logAct('delete', $id, $r['reimb_number'], 'dihapus (status ' . Reimbursement::statusLabel($r['status']) . ')');
        setFlash('success', "Reimbursement {$r['reimb_number']} dihapus.");
        $this->redirect('reimbursement', 'index');
    }

    // =================== Cetak (format Excel "LAPORAN BIAYA REIMBURSE") ===================

    public function print()
    {
        $r = $this->load((int) ($_GET['id'] ?? 0));
        $this->activityLog->log(currentUserId(), 'reimbursement', 'print', "Cetak Reimbursement {$r['reimb_number']} (#{$r['id']})");
        $this->view('reimbursement/print', [
            'pageTitle' => 'Cetak Reimbursement',
            'r'         => $r,
            'items'     => $this->model->items((int) $r['id']),
            'autoprint' => !empty($_GET['autoprint']),
        ]);
    }

    // =================== Helper privat ===================

    public function availableActions(array $r): array
    {
        $s = $r['status'];
        $own = (int) $r['created_by'] === (int) currentUserId() || currentUserRole() === ROLE_SUPER_ADMIN;
        $a = [];
        if ($s === Reimbursement::DRAFT) {
            $a['edit'] = $own && can('reimbursement', 'edit');
            $a['submit'] = $own && can('reimbursement', 'submit');
            $a['delete'] = can('reimbursement', 'delete');
        } elseif ($s === Reimbursement::MENUNGGU) {
            $a['approve'] = can('reimbursement', 'review');
            $a['reject'] = can('reimbursement', 'review');
        } elseif ($s === Reimbursement::DITOLAK) {
            $a['revise'] = $own && can('reimbursement', 'edit');
            $a['delete'] = can('reimbursement', 'delete');
        }
        return array_filter($a);
    }

    private function load(int $id): array
    {
        $r = $id > 0 ? $this->model->findWithRelations($id) : null;
        if (!$r) {
            setFlash('error', 'Reimbursement tidak ditemukan.');
            $this->redirect('reimbursement', 'index');
        }
        if (!Reimbursement::canViewRow($r)) {
            (new ActivityLog())->log(currentUserId(), 'reimbursement', 'access_denied', "Akses ditolak: mencoba membuka Reimbursement #{$id} milik akun lain");
            http_response_code(403);
            require ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }
        return $r;
    }

    /** Hanya pengaju sendiri (atau Super Admin) yang boleh mengubah/mengajukan miliknya. */
    private function requireOwner(array $r): void
    {
        if ((int) $r['created_by'] !== (int) currentUserId() && currentUserRole() !== ROLE_SUPER_ADMIN) {
            (new ActivityLog())->log(currentUserId(), 'reimbursement', 'access_denied', "Akses ditolak: mengubah Reimbursement {$r['reimb_number']} milik akun lain");
            http_response_code(403);
            require ROOT_PATH . '/app/views/errors/403.php';
            exit;
        }
    }

    private function requireEditable(array $r): void
    {
        $this->requireOwner($r);
        if ($r['status'] !== Reimbursement::DRAFT) {
            setFlash('error', 'Hanya reimbursement berstatus Draft yang bisa diedit.'
                . ($r['status'] === Reimbursement::DITOLAK ? ' Untuk pengajuan Ditolak, tekan "Revisi" dulu.' : ' Yang sudah diajukan/disetujui terkunci.'));
            $this->redirect('reimbursement', 'detail', ['id' => $r['id']]);
        }
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('reimbursement', 'index');
        }
        verifyCsrf();
    }

    private function logAct(string $action, int $id, string $number, string $detail): void
    {
        $this->activityLog->log(currentUserId(), 'reimbursement', $action, "Reimbursement {$number} (#{$id}): {$detail}");
    }

    private function alreadyDecidedMessage(array $r): string
    {
        if (in_array($r['status'], [Reimbursement::DITERUSKAN, Reimbursement::DISETUJUI], true)) {
            return 'Reimbursement ini sudah disetujui' . ($r['reviewed_by_name'] ? ' oleh ' . $r['reviewed_by_name'] : '') . '. Persetujuan tidak bisa diulang.';
        }
        if ($r['status'] === Reimbursement::DITOLAK) {
            return 'Reimbursement ini sudah ditolak' . ($r['reviewed_by_name'] ? ' oleh ' . $r['reviewed_by_name'] : '') . '.';
        }
        return 'Status reimbursement saat ini (' . Reimbursement::statusLabel($r['status']) . ') tidak bisa diperiksa.';
    }

    private function doTransition(array $r, array $from, string $to, string $action, string $historyNote, array $extra, string $verb): void
    {
        $id = (int) $r['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $ok = $this->model->transition($id, $from, $to, $extra);
            if ($ok) {
                $this->model->addHistory($id, currentUserId(), $action, $r['status'], $to, $historyNote);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Reimbursement {$action} error: " . $e->getMessage());
            setFlash('error', 'Gagal memproses reimbursement. Silakan coba lagi.');
            $this->redirect('reimbursement', 'detail', ['id' => $id]);
        }
        if (!$ok) {
            setFlash('error', 'Aksi ditolak: status saat ini (' . Reimbursement::statusLabel($r['status']) . ') tidak mengizinkan aksi ini, atau sudah diubah pihak lain.');
            $this->redirect('reimbursement', 'detail', ['id' => $id]);
        }
        $this->logAct($action, $id, $r['reimb_number'], $verb);
        setFlash('success', "Reimbursement {$r['reimb_number']} {$verb}.");
        $this->redirect('reimbursement', 'detail', ['id' => $id]);
    }

    private function renderForm(string $mode, ?array $r, array $items, ?array $posted): void
    {
        $me = currentUserName();
        $this->view('reimbursement/form', [
            'pageTitle'  => $mode === 'edit' ? 'Edit Reimbursement' : 'Buat Reimbursement',
            'mode'       => $mode,
            'r'          => $r,
            'items'      => $items,
            'posted'     => $posted,
            'number'     => $r['reimb_number'] ?? $this->model->previewNumber($posted['reimb_date'] ?? date('Y-m-d')),
            'defaultName' => $me,
            'categories' => $this->model->categoryOptions(),
            'divisions'  => $this->model->distinctValues('division'),
            'cities'     => $this->model->distinctValues('city'),
            'canSubmit'  => can('reimbursement', 'submit'),
        ]);
    }

    /**
     * Baca & validasi input form (SELURUH validasi di backend). Mengembalikan [header, baris, errors].
     * Baris: expense_date, category_id, description, amount, amount_expr, item_id (baris lama), proof_del, _row (indeks form).
     */
    private function collectAndValidate(array $oldItems): array
    {
        $s = fn($k, $max = 255) => mb_substr(is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '', 0, $max);
        $errors = [];
        $data = [
            'reimb_date'    => $s('reimb_date', 10),
            'employee_name' => $s('employee_name', 150),
            'division'      => $s('division', 100),
            'city'          => $s('city', 100),
            'task'          => $s('task', 255),
            'kasbon_amount' => 0.0,
        ];
        if (!isValidDateString($data['reimb_date'])) {
            $errors[] = 'Tanggal tidak valid.';
        }
        if ($data['employee_name'] === '') {
            $errors[] = 'Nama wajib diisi.';
        }
        $kRaw = $s('kasbon_amount', 30);
        $data['kasbon_raw'] = $kRaw;
        if ($kRaw !== '') {
            if (!preg_match('/^[0-9][0-9,]*(\.[0-9]{1,2})?$/', $kRaw)) {
                $errors[] = 'B S (kasbon) harus berupa angka.';
            } else {
                $data['kasbon_amount'] = parseCurrencyInput($kRaw);
                if ($data['kasbon_amount'] > Reimbursement::MAX_AMOUNT) {
                    $errors[] = 'B S (kasbon) terlalu besar.';
                }
            }
        }

        $col = function ($k) { return (isset($_POST[$k]) && is_array($_POST[$k])) ? array_values($_POST[$k]) : []; };
        $dates = $col('expense_date');
        $cats = $col('category_id');
        $descs = $col('description');
        $amounts = $col('amount');
        $itemIds = $col('item_id');
        $dels = $col('proof_del');
        $n = max(count($dates), count($cats), count($descs), count($amounts));
        if ($n > self::MAX_ROWS) {
            $errors[] = 'Jumlah baris melebihi batas ' . self::MAX_ROWS . '.';
            $n = self::MAX_ROWS;
        }

        $validCat = [];
        foreach ($this->model->categoryOptions() as $c) {
            $validCat[(int) $c['id']] = true;
        }
        $oldById = [];
        foreach ($oldItems as $o) {
            $oldById[(int) $o['id']] = $o;
            if (!empty($o['category_id'])) {
                $validCat[(int) $o['category_id']] = true;
            }
        }

        $items = [];
        for ($i = 0; $i < $n; $i++) {
            $g = fn($arr) => is_string($arr[$i] ?? null) ? trim($arr[$i]) : '';
            $date = $g($dates);
            $cat = (int) $g($cats);
            $desc = mb_substr($g($descs), 0, 255);
            $amtRaw = $g($amounts);
            $iid = ctype_digit($g($itemIds)) ? (int) $g($itemIds) : 0;
            $hasUpload = !empty($_FILES['proof_file']['name'][$i] ?? '');
            $keepOld = $iid > 0 && isset($oldById[$iid]);

            if ($date === '' && $cat === 0 && $desc === '' && $amtRaw === '' && !$hasUpload && !$keepOld) {
                continue; // baris kosong
            }
            $line = count($items) + 1;
            $parsed = Reimbursement::parseAmount($amtRaw);
            if (!isValidDateString($date)) {
                $errors[] = "Baris {$line}: Tanggal tidak valid.";
            }
            if ($desc === '') {
                $errors[] = "Baris {$line}: Keterangan wajib diisi.";
            }
            if ($parsed === null) {
                $errors[] = "Baris {$line}: Jumlah harus berupa angka lebih dari 0 (boleh penjumlahan, mis. 9500+9000).";
            }
            if ($cat !== 0 && empty($validCat[$cat])) {
                $errors[] = "Baris {$line}: Kategori tidak valid.";
            }
            $items[] = [
                'expense_date' => $date, 'category_id' => $cat ?: null, 'description' => $desc,
                'amount' => $parsed ? $parsed[0] : 0.0, 'amount_expr' => $parsed ? $parsed[1] : null,
                'amount_raw' => $amtRaw, 'item_id' => $keepOld ? $iid : 0,
                'proof_del' => ($g($dels) === '1'), 'proof_file' => $keepOld ? ($oldById[$iid]['proof_file'] ?? null) : null,
                '_row' => $i,
            ];
        }
        if (!$items) {
            $errors[] = 'Minimal harus ada 1 baris pengeluaran.';
        }
        return [$data, $items, array_values(array_unique($errors))];
    }

    /**
     * Proses upload bukti per baris (setelah validasi lolos supaya tidak ada file yatim). $uploaded = file BARU
     * (dibuang bila gagal), $toDelete = file LAMA yang diganti/dihapus (dibuang setelah commit).
     * @throws RuntimeException pesan siap tampil
     */
    private function applyProofs(array $items, array $oldItems, array &$uploaded, array &$toDelete = []): array
    {
        $old = [];
        foreach ($oldItems as $o) {
            $old[(int) $o['id']] = $o;
        }
        $keptOld = [];
        foreach ($items as $k => $it) {
            $line = $k + 1;
            $oldPath = $it['item_id'] && isset($old[$it['item_id']]) ? ($old[$it['item_id']]['proof_file'] ?? null) : null;
            $items[$k]['proof_file'] = $oldPath;
            $row = $it['_row'];
            if (!empty($_FILES['proof_file']['name'][$row] ?? '')) {
                $_FILES['reimb_proof_one'] = [
                    'name' => $_FILES['proof_file']['name'][$row], 'type' => $_FILES['proof_file']['type'][$row] ?? '',
                    'tmp_name' => $_FILES['proof_file']['tmp_name'][$row] ?? '', 'error' => $_FILES['proof_file']['error'][$row] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $_FILES['proof_file']['size'][$row] ?? 0,
                ];
                try {
                    $new = handleFileUpload('reimb_proof_one', 'reimbursement', self::PROOF_EXT, 5);
                } catch (RuntimeException $e) {
                    throw new RuntimeException("Baris {$line}: " . $e->getMessage());
                }
                if ($new) {
                    $uploaded[] = $new;
                    $items[$k]['proof_file'] = $new;
                    if ($oldPath) {
                        $toDelete[] = $oldPath;
                    }
                }
            } elseif ($it['proof_del'] && $oldPath) {
                $items[$k]['proof_file'] = null;
                $toDelete[] = $oldPath;
            }
            if ($items[$k]['proof_file'] && $oldPath === $items[$k]['proof_file']) {
                $keptOld[$oldPath] = true;
            }
        }
        // bukti dari baris lama yang DIHAPUS dari form ikut dibuang setelah commit
        foreach ($old as $o) {
            if (!empty($o['proof_file']) && empty($keptOld[$o['proof_file']]) && !in_array($o['proof_file'], $toDelete, true)) {
                $toDelete[] = $o['proof_file'];
            }
        }
        return $items;
    }

    private function cleanup(array $paths): void
    {
        foreach ($paths as $p) {
            if (is_string($p) && $p !== '') {
                deleteUploadedFile($p);
            }
        }
    }

    /** Input dialog "Setujui" (data Pengajuan Budget): Rekening Sumber, Jenis Transaksi, Rekening Tujuan. */
    private function collectForwardInput(): array
    {
        $pbModel = new PengajuanBudget();
        $errors = [];
        $bank = (int) ($_POST['source_bank_id'] ?? 0);
        $jenis = is_string($_POST['jenis_transaksi'] ?? null) ? trim($_POST['jenis_transaksi']) : '';
        $tidRaw = is_string($_POST['tujuan_id'] ?? null) ? trim($_POST['tujuan_id']) : '';
        $manual = mb_substr(is_string($_POST['tujuan_manual'] ?? null) ? trim($_POST['tujuan_manual']) : '', 0, 255);

        $validBank = false;
        foreach ($pbModel->sourceBankOptions() as $b) {
            if ((int) $b['id'] === $bank) {
                $validBank = true;
            }
        }
        if (!$validBank) {
            $errors[] = 'Rekening Sumber wajib dipilih dari Master Bank aktif.';
        }
        if ($jenis === '' || !in_array($jenis, $pbModel->jenisTransaksiOptions(), true)) {
            $errors[] = 'Jenis Transaksi wajib dipilih dari daftar.';
        }
        $tujuan = '';
        $tid = null;
        if (ctype_digit($tidRaw) && (int) $tidRaw > 0) {
            foreach ((new MasterRekeningTujuan())->activeList() as $t) {
                if ((int) $t['id'] === (int) $tidRaw) {
                    $tujuan = MasterRekeningTujuan::label($t);
                    $tid = (int) $t['id'];
                }
            }
            if ($tid === null) {
                $errors[] = 'Rekening Tujuan master tidak valid.';
            }
        } else {
            $tujuan = $manual;
        }
        if ($tujuan === '') {
            $errors[] = 'Rekening Tujuan wajib dipilih/diisi.';
        }
        return [['source_bank_id' => $bank, 'jenis_transaksi' => $jenis, 'tujuan' => $tujuan, 'tujuan_id' => $tid], $errors];
    }

    /** Keterangan baris Pengajuan Budget hasil penerusan: ringkas, rujukan nomor + pengaju + tugas. */
    private function forwardDescription(array $r, array $items, array $t): string
    {
        $parts = ["Reimbursement {$r['reimb_number']} a.n. {$r['employee_name']}"];
        if (!empty($r['task'])) {
            $parts[] = 'Tugas: ' . $r['task'];
        }
        $parts[] = count($items) . ' pengeluaran total ' . formatRupiah($t['total']);
        if ($t['kasbon'] > 0) {
            $parts[] = 'dikurangi kasbon ' . formatRupiah($t['kasbon']);
        }
        return mb_substr(implode(' | ', $parts), 0, 2000);
    }
}
