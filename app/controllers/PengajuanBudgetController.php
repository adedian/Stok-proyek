<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PengajuanBudget.php';
require_once ROOT_PATH . '/app/models/MasterRekeningTujuan.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Pengajuan Budget (Permintaan Otorisasi) -- halaman "Form Pengajuan".
 *
 * Akses: HANYA akun Purwati & Nissa (izin per-akun lewat user_permissions, lihat
 * config/permissions.php) + Super Admin. Tiap aksi memanggil Middleware::requirePermission()
 * (403 + activity log 'access_denied' bila ditolak) -- bukan sekadar menyembunyikan menu.
 *
 * Modul MANDIRI: tidak menulis ke bank_transactions/Kas/PO. Pengajuan hanya mencatat
 * RENCANA pembayaran; saldo Bank aktual tidak pernah berubah karenanya.
 *
 * Status: draft -> diajukan -> direalisasikan | dibatalkan. Perpindahan status memakai
 * UPDATE bersyarat (PengajuanBudget::transition()) sehingga aman dari klik ganda / request
 * basi. direalisasikan terkunci: tidak bisa diedit, dibatalkan, ataupun dihapus.
 * Semua perubahan dicatat di pengajuan_budget_history + activity log. Semua POST wajib CSRF
 * dan memakai prepared statement + transaksi DB.
 */
class PengajuanBudgetController extends Controller
{
    private PengajuanBudget $model;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('pengajuan_budget', 'view');
        $this->model = new PengajuanBudget();
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
        $total = $this->model->countFiltered($filters);
        $pg = paginationInfo($total, (int) ($_GET['page'] ?? 1));

        $this->view('pengajuan_budget/list', [
            'pageTitle'  => 'Form Pengajuan Budget',
            'rows'       => $this->model->listPaginated($filters, $pg['perPage'], $pg['offset']),
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter(['module' => 'pengajuan_budget'] + $filters)),
            'statuses'   => PengajuanBudget::STATUS_LABELS,
        ]);
    }

    // =================== Tambah ===================

    public function create()
    {
        Middleware::requirePermission('pengajuan_budget', 'create');
        $this->renderForm('create', null, []);
    }

    public function store()
    {
        Middleware::requirePermission('pengajuan_budget', 'create');
        $this->requirePost();

        [$data, $items, $errors] = $this->collectAndValidate([]);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderForm('create', null, $items, $data);
            return;
        }

        $submitNow = !empty($_POST['submit_after']) && can('pengajuan_budget', 'submit');
        $status = $submitNow ? PengajuanBudget::DIAJUKAN : PengajuanBudget::DRAFT;

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $number = $this->model->nextNumber($data['pengajuan_date']);
            $id = $this->model->create([
                'pengajuan_number' => $number,
                'pengajuan_date'   => $data['pengajuan_date'],
                'notes'            => $data['notes'] !== '' ? $data['notes'] : null,
                'status'           => $status,
                'submitted_at'     => $submitNow ? date('Y-m-d H:i:s') : null,
                'created_by'       => (int) currentUserId(),
            ]);
            $this->model->replaceItems($id, $items);
            $total = array_sum(array_column($items, 'amount'));
            $this->model->addHistory($id, currentUserId(), 'create', null, PengajuanBudget::DRAFT,
                "Pengajuan {$number} dibuat (" . count($items) . ' baris, total ' . formatRupiah($total) . ')');
            if ($submitNow) {
                $this->model->addHistory($id, currentUserId(), 'submit', PengajuanBudget::DRAFT, $status, 'Pengajuan diajukan');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PengajuanBudget store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Pengajuan Budget. Silakan coba lagi.');
            $this->renderForm('create', null, $items, $data);
            return;
        }

        $this->logAct('create', $id, $number, 'dibuat (' . PengajuanBudget::statusLabel($status) . ')');
        setFlash('success', "Pengajuan Budget {$number} berhasil disimpan" . ($submitNow ? ' dan diajukan.' : ' sebagai Draft.'));
        $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
    }

    // =================== Detail ===================

    public function detail()
    {
        $pb = $this->load((int) ($_GET['id'] ?? 0));
        $id = (int) $pb['id'];
        $items = $this->model->items($id);
        $this->view('pengajuan_budget/detail', [
            'pageTitle' => 'Detail Pengajuan Budget',
            'pb'        => $pb,
            'items'     => $items,
            'history'   => $this->model->history($id),
            'actions'   => $this->availableActions($pb),
        ]);
    }

    // =================== Edit (hanya draft) ===================

    public function edit()
    {
        Middleware::requirePermission('pengajuan_budget', 'edit');
        $pb = $this->load((int) ($_GET['id'] ?? 0));
        if ($pb['status'] !== PengajuanBudget::DRAFT) {
            setFlash('error', 'Hanya pengajuan berstatus Draft yang bisa diedit. ' .
                ($pb['status'] === PengajuanBudget::DIAJUKAN ? 'Kembalikan ke Draft dulu bila perlu mengubahnya.' : 'Pengajuan ini sudah terkunci.'));
            $this->redirect('pengajuan_budget', 'detail', ['id' => $pb['id']]);
        }
        $this->renderForm('edit', $pb, $this->model->items((int) $pb['id']));
    }

    public function update()
    {
        Middleware::requirePermission('pengajuan_budget', 'edit');
        $this->requirePost();

        $pb = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $pb['id'];
        if ($pb['status'] !== PengajuanBudget::DRAFT) {
            setFlash('error', 'Hanya pengajuan berstatus Draft yang bisa diedit.');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
        }

        $oldItems = $this->model->items($id);
        [$data, $items, $errors] = $this->collectAndValidate($oldItems);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderForm('edit', $pb, $items, $data);
            return;
        }

        $submitNow = !empty($_POST['submit_after']) && can('pengajuan_budget', 'submit');
        $oldTotal = array_sum(array_map(fn($r) => (float) $r['amount'], $oldItems));
        $newTotal = array_sum(array_column($items, 'amount'));

        $changes = [];
        if ($pb['pengajuan_date'] !== $data['pengajuan_date']) {
            $changes[] = "tanggal {$pb['pengajuan_date']} -> {$data['pengajuan_date']}";
        }
        if ((string) ($pb['notes'] ?? '') !== $data['notes']) {
            $changes[] = 'catatan diubah';
        }
        $sig = fn(array $r) => implode('|', [(int) $r['source_bank_id'], $r['jenis_transaksi'], $r['tujuan'], number_format((float) $r['amount'], 2, '.', ''), (string) ($r['keterangan'] ?? ''), (int) ($r['category_id'] ?? 0), (int) ($r['project_id'] ?? 0), (int) ($r['tujuan_id'] ?? 0)]);
        $oldSigs = array_map($sig, $oldItems);
        $newSigs = array_map($sig, $items);
        if ($oldSigs !== $newSigs) {
            $changes[] = 'baris diubah (' . count($oldItems) . ' -> ' . count($items) . ' baris, total ' . formatRupiah($oldTotal) . ' -> ' . formatRupiah($newTotal) . ')';
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            // Guard atomik: baris header hanya diubah bila MASIH draft (cegah balapan dengan Ajukan/Batalkan).
            $upd = $pdo->prepare(
                "UPDATE pengajuan_budgets SET pengajuan_date = :d, notes = :n
                  WHERE id = :id AND deleted_at IS NULL AND status = 'draft'"
            );
            $upd->execute(['d' => $data['pengajuan_date'], 'n' => $data['notes'] !== '' ? $data['notes'] : null, 'id' => $id]);
            // rowCount()=0 bisa juga berarti nilai identik -> verifikasi status terpisah
            $cur = $pdo->prepare("SELECT status FROM pengajuan_budgets WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
            $cur->execute(['id' => $id]);
            if ($cur->fetchColumn() !== PengajuanBudget::DRAFT) {
                throw new RuntimeException('status-berubah');
            }
            $this->model->replaceItems($id, $items);
            $this->model->addHistory($id, currentUserId(), 'update', PengajuanBudget::DRAFT, PengajuanBudget::DRAFT,
                $changes ? 'Diubah: ' . implode('; ', $changes) : 'Disimpan tanpa perubahan data');
            if ($submitNow) {
                if (!$this->model->transition($id, [PengajuanBudget::DRAFT], PengajuanBudget::DIAJUKAN, ['submitted_at' => date('Y-m-d H:i:s')])) {
                    throw new RuntimeException('status-berubah');
                }
                $this->model->addHistory($id, currentUserId(), 'submit', PengajuanBudget::DRAFT, PengajuanBudget::DIAJUKAN, 'Pengajuan diajukan');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getMessage() === 'status-berubah') {
                setFlash('error', 'Status pengajuan sudah berubah, perubahan tidak disimpan.');
                $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
            }
            error_log('PengajuanBudget update error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan perubahan. Silakan coba lagi.');
            $this->renderForm('edit', $pb, $items, $data);
            return;
        }

        $this->logAct('update', $id, $pb['pengajuan_number'], $changes ? implode('; ', $changes) : 'disimpan tanpa perubahan');
        if ($submitNow) {
            $this->logAct('submit', $id, $pb['pengajuan_number'], 'diajukan');
        }
        setFlash('success', "Pengajuan Budget {$pb['pengajuan_number']} berhasil diperbarui" . ($submitNow ? ' dan diajukan.' : '.'));
        $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
    }

    // =================== Aksi status ===================

    public function submit()
    {
        Middleware::requirePermission('pengajuan_budget', 'submit');
        $this->requirePost();
        $pb = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $pb['id'];
        if ($this->model->totalOf($id) <= 0) {
            setFlash('error', 'Pengajuan tanpa baris/nominal tidak bisa diajukan.');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
        }
        $this->doTransition($pb, [PengajuanBudget::DRAFT], PengajuanBudget::DIAJUKAN, 'submit',
            'Pengajuan diajukan', ['submitted_at' => date('Y-m-d H:i:s')], 'diajukan');
    }

    /** Diajukan -> Draft (supaya bisa diedit lagi). Memakai izin edit. */
    public function revert()
    {
        Middleware::requirePermission('pengajuan_budget', 'edit');
        $this->requirePost();
        $pb = $this->load((int) ($_POST['id'] ?? 0));
        $this->doTransition($pb, [PengajuanBudget::DIAJUKAN], PengajuanBudget::DRAFT, 'revert',
            'Dikembalikan ke Draft untuk diedit', ['submitted_at' => null], 'dikembalikan ke Draft');
    }

    public function realize()
    {
        Middleware::requirePermission('pengajuan_budget', 'realize');
        $this->requirePost();
        $pb = $this->load((int) ($_POST['id'] ?? 0));
        $this->doTransition($pb, [PengajuanBudget::DIAJUKAN], PengajuanBudget::DIREALISASIKAN, 'realize',
            'Ditandai Direalisasikan (pengajuan terkunci)',
            ['realized_at' => date('Y-m-d H:i:s'), 'realized_by' => (int) currentUserId()], 'direalisasikan');
    }

    public function cancel()
    {
        Middleware::requirePermission('pengajuan_budget', 'cancel');
        $this->requirePost();
        $pb = $this->load((int) ($_POST['id'] ?? 0));
        $reason = is_string($_POST['cancel_reason'] ?? null) ? trim($_POST['cancel_reason']) : '';
        if ($reason === '' || mb_strlen($reason) > 500) {
            setFlash('error', 'Alasan pembatalan wajib diisi (maksimal 500 karakter).');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $pb['id']]);
        }
        $this->doTransition($pb, [PengajuanBudget::DRAFT, PengajuanBudget::DIAJUKAN], PengajuanBudget::DIBATALKAN, 'cancel',
            'Dibatalkan: ' . $reason,
            ['cancelled_at' => date('Y-m-d H:i:s'), 'cancelled_by' => (int) currentUserId(), 'cancel_reason' => $reason], 'dibatalkan');
    }

    public function delete()
    {
        Middleware::requirePermission('pengajuan_budget', 'delete');
        $this->requirePost();
        $pb = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $pb['id'];

        if ($pb['status'] === PengajuanBudget::DIREALISASIKAN) {
            setFlash('error', 'Pengajuan yang sudah Direalisasikan tidak boleh dihapus.');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
        }
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $ok = $this->model->softDeleteGuarded($id, currentUserId());
            if ($ok) {
                $this->model->addHistory($id, currentUserId(), 'delete', $pb['status'], $pb['status'], 'Pengajuan dihapus');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PengajuanBudget delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus pengajuan.');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
        }
        if (!$ok) {
            setFlash('error', 'Pengajuan hanya bisa dihapus saat berstatus Draft atau Dibatalkan (Diajukan: batalkan dulu).');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
        }
        $this->logAct('delete', $id, $pb['pengajuan_number'], 'dihapus (status ' . PengajuanBudget::statusLabel($pb['status']) . ')');
        setFlash('success', "Pengajuan Budget {$pb['pengajuan_number']} dihapus.");
        $this->redirect('pengajuan_budget', 'index');
    }

    // =================== Helper privat ===================

    /** Tombol aksi yang boleh tampil di detail (murni tampilan; tiap endpoint memvalidasi ulang sendiri). */
    public function availableActions(array $pb): array
    {
        $s = $pb['status'];
        $a = [];
        if ($s === PengajuanBudget::DRAFT) {
            $a['edit'] = can('pengajuan_budget', 'edit');
            $a['submit'] = can('pengajuan_budget', 'submit');
            $a['cancel'] = can('pengajuan_budget', 'cancel');
            $a['delete'] = can('pengajuan_budget', 'delete');
        } elseif ($s === PengajuanBudget::DIAJUKAN) {
            $a['revert'] = can('pengajuan_budget', 'edit');
            $a['realize'] = can('pengajuan_budget', 'realize');
            $a['cancel'] = can('pengajuan_budget', 'cancel');
        } elseif ($s === PengajuanBudget::DIBATALKAN) {
            $a['delete'] = can('pengajuan_budget', 'delete');
        }
        return array_filter($a);
    }

    private function doTransition(array $pb, array $from, string $to, string $action, string $historyNote, array $extra, string $verb): void
    {
        $id = (int) $pb['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $ok = $this->model->transition($id, $from, $to, $extra);
            if ($ok) {
                $this->model->addHistory($id, currentUserId(), $action, $pb['status'], $to, $historyNote);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("PengajuanBudget {$action} error: " . $e->getMessage());
            setFlash('error', 'Gagal memproses pengajuan. Silakan coba lagi.');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
        }
        if (!$ok) {
            setFlash('error', 'Aksi ditolak: status pengajuan saat ini (' . PengajuanBudget::statusLabel($pb['status']) . ') tidak mengizinkan aksi ini, atau sudah diubah pihak lain.');
            $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
        }
        $this->logAct($action, $id, $pb['pengajuan_number'], $verb);
        setFlash('success', "Pengajuan Budget {$pb['pengajuan_number']} {$verb}.");
        $this->redirect('pengajuan_budget', 'detail', ['id' => $id]);
    }

    private function load(int $id): array
    {
        $pb = $id > 0 ? $this->model->findWithRelations($id) : null;
        if (!$pb) {
            setFlash('error', 'Pengajuan Budget tidak ditemukan.');
            $this->redirect('pengajuan_budget', 'index');
        }
        return $pb;
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pengajuan_budget', 'index');
        }
        verifyCsrf();
    }

    private function logAct(string $action, int $id, string $number, string $detail): void
    {
        $this->activityLog->log(currentUserId(), 'pengajuan_budget', $action, "Pengajuan Budget {$number} (#{$id}): {$detail}");
    }

    /** Project aktif + (saat edit) project lama yang sudah tersimpan walau kini closed. */
    private function projectChoices(array $items): array
    {
        $list = $this->model->projectOptions();
        $have = array_map(fn($p) => (int) $p['id'], $list);
        $missing = array_filter(array_map(fn($r) => (int) ($r['project_id'] ?? 0), $items), fn($id) => $id && !in_array($id, $have, true));
        if ($missing) {
            $in = implode(',', array_map('intval', array_unique($missing)));
            $list = array_merge($list, getPDO()->query("SELECT id, project_name FROM projects WHERE id IN ({$in})")->fetchAll());
        }
        return $list;
    }

    private function renderForm(string $mode, ?array $pb, array $items, ?array $posted = null): void
    {
        $existingBankIds = array_map(fn($r) => (int) ($r['source_bank_id'] ?? 0), $items);
        $date = $posted['pengajuan_date'] ?? ($pb['pengajuan_date'] ?? date('Y-m-d'));
        $jenis = $this->model->jenisTransaksiOptions();
        foreach ($items as $r) { // nilai lama yang sudah tidak ada di master tetap tampil (tidak hilang diam-diam)
            if (!empty($r['jenis_transaksi']) && !in_array($r['jenis_transaksi'], $jenis, true)) {
                $jenis[] = $r['jenis_transaksi'];
            }
        }
        $this->view('pengajuan_budget/form', [
            'pageTitle'  => $mode === 'edit' ? 'Edit Pengajuan Budget' : 'Buat Pengajuan Budget',
            'mode'       => $mode,
            'pb'         => $pb,
            'items'      => $items,
            'posted'     => $posted,
            'number'     => $pb['pengajuan_number'] ?? $this->model->previewNumber($date),
            'banks'      => $this->model->sourceBankOptions($existingBankIds),
            'jenisList'  => $jenis,
            'categories' => $this->model->categoryOptions(),
            'projects'   => $this->projectChoices($items),
            'tujuanList' => (new MasterRekeningTujuan())->activeListWith(array_map(fn($r) => (int) ($r['tujuan_id'] ?? 0), $items)),
            'canAddTujuan' => can('master_rekening_tujuan', 'quick_add'),
            'canSubmit'  => can('pengajuan_budget', 'submit'),
        ]);
    }

    /**
     * Baca & validasi input form (SELURUH validasi di backend; JS di form hanya kemudahan).
     * @param array $oldItems baris lama (saat edit) -- nilai lama yang sudah nonaktif di master tetap boleh.
     * @return array [$data, $items, $errors]
     */
    private function collectAndValidate(array $oldItems): array
    {
        $date = is_string($_POST['pengajuan_date'] ?? null) ? trim($_POST['pengajuan_date']) : '';
        $notes = is_string($_POST['notes'] ?? null) ? trim($_POST['notes']) : '';
        $data = ['pengajuan_date' => $date, 'notes' => $notes];
        $errors = [];

        if (!isValidDateString($date)) {
            $errors[] = 'Tanggal pengajuan tidak valid.';
        }
        if (mb_strlen($notes) > 2000) {
            $errors[] = 'Catatan terlalu panjang (maksimal 2000 karakter).';
        }

        $col = function (string $k): array {
            $v = $_POST[$k] ?? [];
            return is_array($v) ? array_values($v) : [];
        };
        $banks = $col('source_bank_id');
        $jenisIn = $col('jenis_transaksi');
        $tujuanIn = $col('tujuan');
        $amountIn = $col('amount');
        $ketIn = $col('keterangan');
        $catIn = $col('category_id');
        $projIn = $col('project_id');
        $tidIn = $col('tujuan_id');
        $n = max(count($banks), count($jenisIn), count($tujuanIn), count($amountIn), count($ketIn), count($catIn), count($projIn), count($tidIn));

        // himpunan nilai master yang sah
        $validBank = [];
        foreach ((new MasterBank())->activeList() as $b) {
            $validBank[(int) $b['id']] = true;
        }
        foreach ($oldItems as $o) {
            $validBank[(int) $o['source_bank_id']] = true;
        }
        $validJenis = $this->model->jenisTransaksiOptions();
        foreach ($oldItems as $o) {
            $validJenis[] = $o['jenis_transaksi'];
        }
        $validCat = [];
        foreach ($this->model->categoryOptions() as $c) {
            $validCat[(int) $c['id']] = true;
        }
        foreach ($oldItems as $o) {
            if (!empty($o['category_id'])) {
                $validCat[(int) $o['category_id']] = true;
            }
        }

        $validTujuan = [];
        foreach ((new MasterRekeningTujuan())->activeList() as $t) {
            $validTujuan[(int) $t['id']] = $t;
        }
        foreach ($oldItems as $o) {
            if (!empty($o['tujuan_id']) && empty($validTujuan[(int) $o['tujuan_id']]) && ($old = (new MasterRekeningTujuan())->find((int) $o['tujuan_id']))) {
                $validTujuan[(int) $o['tujuan_id']] = $old;
            }
        }
        $validProj = [];
        foreach ($this->model->projectOptions() as $p) {
            $validProj[(int) $p['id']] = true;
        }
        foreach ($oldItems as $o) {
            if (!empty($o['project_id'])) {
                $validProj[(int) $o['project_id']] = true;
            }
        }

        $items = [];
        for ($i = 0; $i < $n; $i++) {
            $s = fn($arr) => is_string($arr[$i] ?? null) ? trim($arr[$i]) : '';
            $bank = (int) ($s($banks));
            $jenis = $s($jenisIn);
            $tujuan = $s($tujuanIn);
            $amountRaw = $s($amountIn);
            $ket = $s($ketIn);
            $cat = (int) $s($catIn);
            $proj = (int) $s($projIn);
            $tidRaw = $s($tidIn);
            $tid = ctype_digit($tidRaw) ? (int) $tidRaw : 0; // 'manual'/kosong = ketik manual

            // baris benar-benar kosong -> abaikan
            if ($bank === 0 && $jenis === '' && $tujuan === '' && ($tidRaw === '' || $tidRaw === 'manual') && $amountRaw === '' && $ket === '' && $cat === 0 && $proj === 0) {
                continue;
            }
            $amount = parseCurrencyInput($amountRaw);
            $line = count($items) + 1;
            // Rekening Tujuan dari master -> teks diambil dari master (snapshot); selain itu teks manual.
            if ($tid > 0 && !empty($validTujuan[$tid])) {
                $tujuan = MasterRekeningTujuan::label($validTujuan[$tid]);
            } elseif ($tid > 0) {
                $errors[] = "Baris {$line}: Rekening Tujuan master tidak valid.";
                $tid = 0;
            }
            $items[] = [
                'source_bank_id' => $bank, 'jenis_transaksi' => $jenis, 'tujuan' => $tujuan, 'tujuan_id' => $tid ?: null,
                'amount' => $amount, 'keterangan' => $ket, 'category_id' => $cat ?: null, 'project_id' => $proj ?: null,
            ];

            if ($bank <= 0 || empty($validBank[$bank])) {
                $errors[] = "Baris {$line}: Rekening Sumber wajib dipilih dari Master Bank aktif.";
            }
            if ($jenis === '' || !in_array($jenis, $validJenis, true)) {
                $errors[] = "Baris {$line}: Jenis Transaksi wajib dipilih dari daftar.";
            }
            if ($tujuan === '') {
                $errors[] = "Baris {$line}: Rekening Tujuan wajib diisi.";
            } elseif (mb_strlen($tujuan) > 255) {
                $errors[] = "Baris {$line}: Rekening Tujuan maksimal 255 karakter.";
            }
            if ($amount <= 0) {
                $errors[] = "Baris {$line}: Jumlah pembayaran harus lebih dari 0.";
            } elseif ($amount > PengajuanBudget::MAX_AMOUNT) {
                $errors[] = "Baris {$line}: Jumlah pembayaran terlalu besar.";
            }
            if (mb_strlen($ket) > 2000) {
                $errors[] = "Baris {$line}: Keterangan maksimal 2000 karakter.";
            }
            if ($cat !== 0 && empty($validCat[$cat])) {
                $errors[] = "Baris {$line}: Kategori tidak valid.";
            }
            if ($proj !== 0 && empty($validProj[$proj])) {
                $errors[] = "Baris {$line}: Project tidak valid.";
            }
        }
        if (!$items) {
            $errors[] = 'Minimal harus ada 1 baris pembayaran.';
        }
        return [$data, $items, array_values(array_unique($errors))];
    }
}
