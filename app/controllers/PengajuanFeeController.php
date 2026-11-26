<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PengajuanFee.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Pengajuan Fee -- modul MANDIRI (terpisah dari Reimbursement; tidak terhubung ke Pengajuan Budget).
 * Struktur & rumus mengikuti sheet "Pengajuan Fee" pada Pengajuan fee.xlsx (lihat PengajuanFee).
 *
 * Alur (sesuai tanda tangan di Excel: Dibuat -> Diperiksa -> Disetujui):
 *   Draft --ajukan--> Diajukan --periksa--> Diperiksa --setujui--> Disetujui
 *   Ditolak dapat terjadi di tahap Diajukan (oleh pemeriksa) atau Diperiksa (oleh penyetuju), catatan WAJIB;
 *   Ditolak --revisi--> Draft (histori tetap). Disetujui terkunci.
 *
 * Akses HANYA lewat izin per akun / Super Admin (config/permissions.php, migrasi 2026_11_27_b): view, create, edit,
 * submit (pembuat), check (pemeriksa), approve (penyetuju), delete. Semua aksi divalidasi di backend; perpindahan
 * status memakai UPDATE bersyarat sehingga tiap tahap hanya bisa terjadi SEKALI.
 */
class PengajuanFeeController extends Controller
{
    private PengajuanFee $model;
    private ActivityLog $activityLog;

    private const MAX_ROWS = 200;

    public function __construct()
    {
        Middleware::requirePermission('pengajuan_fee', 'view');
        $this->model = new PengajuanFee();
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
        $this->view('pengajuan_fee/list', [
            'pageTitle'  => 'Pengajuan Fee',
            'rows'       => $this->model->listPaginated($filters, $pg['perPage'], $pg['offset']),
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter(['module' => 'pengajuan_fee'] + $filters)),
            'statuses'   => PengajuanFee::STATUS_LABELS,
        ]);
    }

    // =================== Tambah ===================

    public function create()
    {
        Middleware::requirePermission('pengajuan_fee', 'create');
        $this->renderForm('create', null, [], null);
    }

    public function store()
    {
        Middleware::requirePermission('pengajuan_fee', 'create');
        $this->requirePost();

        [$data, $items, $calc, $errors] = $this->collectAndValidate();
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderForm('create', null, $items, $data);
            return;
        }

        $submitNow = !empty($_POST['submit_after']) && can('pengajuan_fee', 'submit');
        $status = $submitNow ? PengajuanFee::DIAJUKAN : PengajuanFee::DRAFT;

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $number = $this->model->nextNumber($data['fee_date']);
            $id = $this->model->create($this->headerRow($data, $calc) + [
                'fee_number'   => $number,
                'status'       => $status,
                'submitted_at' => $submitNow ? date('Y-m-d H:i:s') : null,
                'created_by'   => (int) currentUserId(),
            ]);
            $this->model->replaceItems($id, $items);
            $this->model->addHistory($id, currentUserId(), 'create', null, PengajuanFee::DRAFT,
                "Pengajuan Fee {$number} dibuat (" . count($items) . ' baris, total ' . formatRupiah($calc['total_amount']) . ', fee ' . formatRupiah($calc['total_fee']) . ', diterima ' . formatRupiah($calc['net_fee']) . ')');
            if ($submitNow) {
                $this->model->addHistory($id, currentUserId(), 'submit', PengajuanFee::DRAFT, $status, 'Diajukan untuk diperiksa');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PengajuanFee store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Pengajuan Fee. Silakan coba lagi.');
            $this->renderForm('create', null, $items, $data);
            return;
        }

        $this->logAct('create', $id, $number, 'dibuat (' . PengajuanFee::statusLabel($status) . ')');
        setFlash('success', "Pengajuan Fee {$number} berhasil disimpan" . ($submitNow ? ' dan diajukan.' : ' sebagai Draft.'));
        $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
    }

    // =================== Detail ===================

    public function detail()
    {
        $f = $this->load((int) ($_GET['id'] ?? 0));
        $id = (int) $f['id'];
        $this->view('pengajuan_fee/detail', [
            'pageTitle' => 'Detail Pengajuan Fee',
            'f'         => $f,
            'items'     => $this->model->items($id),
            'history'   => $this->model->history($id),
            'actions'   => $this->availableActions($f),
        ]);
    }

    // =================== Edit (Draft) ===================

    public function edit()
    {
        Middleware::requirePermission('pengajuan_fee', 'edit');
        $f = $this->load((int) ($_GET['id'] ?? 0));
        $this->requireDraft($f);
        $this->renderForm('edit', $f, $this->model->items((int) $f['id']), null);
    }

    public function update()
    {
        Middleware::requirePermission('pengajuan_fee', 'edit');
        $this->requirePost();
        $f = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $f['id'];
        $this->requireDraft($f);

        $oldItems = $this->model->items($id);
        [$data, $items, $calc, $errors] = $this->collectAndValidate();
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderForm('edit', $f, $items, $data);
            return;
        }

        $submitNow = !empty($_POST['submit_after']) && can('pengajuan_fee', 'submit');
        $changes = [];
        foreach (['fee_date' => 'tanggal', 'city' => 'kota', 'invoice_number' => 'invoice no.', 'customer_name' => 'customer', 'sales_name' => 'sales',
                  'billing_status' => 'status tagihan', 'recipient_name' => 'penerima fee', 'recipient_tax_id' => 'NPWP/NIK', 'note' => 'note', 'nb' => 'NB'] as $k => $label) {
            if ((string) ($f[$k] ?? '') !== (string) ($data[$k] ?? '')) {
                $changes[] = "{$label} diubah";
            }
        }
        foreach (['fee_percent' => 'Fee %', 'ppn_percent' => 'PPN %', 'pph_percent' => 'PPh %'] as $k => $label) {
            if (round((float) $f[$k], 3) !== round((float) $data[$k], 3)) {
                $changes[] = "{$label} " . rtrim(rtrim(number_format((float) $f[$k], 3, '.', ''), '0'), '.') . ' -> ' . rtrim(rtrim(number_format((float) $data[$k], 3, '.', ''), '0'), '.');
            }
        }
        $sig = fn(array $x) => implode('|', [$x['item_name'], number_format((float) $x['qty'], 2, '.', ''), (string) ($x['unit'] ?? ''), number_format((float) $x['price'], 2, '.', '')]);
        if (array_map($sig, $oldItems) !== array_map($sig, $items)) {
            $changes[] = 'baris diubah (' . count($oldItems) . ' -> ' . count($items) . ' baris, total ' . formatRupiah($f['total_amount']) . ' -> ' . formatRupiah($calc['total_amount']) . ', fee ' . formatRupiah($f['total_fee']) . ' -> ' . formatRupiah($calc['total_fee']) . ')';
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $lock = $pdo->prepare("SELECT status FROM pengajuan_fees WHERE id = :id AND deleted_at IS NULL FOR UPDATE");
            $lock->execute(['id' => $id]);
            if ($lock->fetchColumn() !== PengajuanFee::DRAFT) {
                throw new RuntimeException('status-berubah');
            }
            $this->model->updateById($id, $this->headerRow($data, $calc));
            $this->model->replaceItems($id, $items);
            $this->model->addHistory($id, currentUserId(), 'update', PengajuanFee::DRAFT, PengajuanFee::DRAFT,
                $changes ? 'Diubah: ' . implode('; ', $changes) : 'Disimpan tanpa perubahan data');
            if ($submitNow) {
                if (!$this->model->transition($id, [PengajuanFee::DRAFT], PengajuanFee::DIAJUKAN, ['submitted_at' => date('Y-m-d H:i:s')])) {
                    throw new RuntimeException('status-berubah');
                }
                $this->model->addHistory($id, currentUserId(), 'submit', PengajuanFee::DRAFT, PengajuanFee::DIAJUKAN, 'Diajukan untuk diperiksa');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($e->getMessage() === 'status-berubah') {
                setFlash('error', 'Status pengajuan sudah berubah, perubahan tidak disimpan.');
                $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
            }
            error_log('PengajuanFee update error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan perubahan. Silakan coba lagi.');
            $this->renderForm('edit', $f, $items, $data);
            return;
        }

        $this->logAct('update', $id, $f['fee_number'], $changes ? implode('; ', $changes) : 'disimpan tanpa perubahan');
        if ($submitNow) {
            $this->logAct('submit', $id, $f['fee_number'], 'diajukan');
        }
        setFlash('success', "Pengajuan Fee {$f['fee_number']} berhasil diperbarui" . ($submitNow ? ' dan diajukan.' : '.'));
        $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
    }

    // =================== Aksi status ===================

    public function submit()
    {
        Middleware::requirePermission('pengajuan_fee', 'submit');
        $this->requirePost();
        $f = $this->load((int) ($_POST['id'] ?? 0));
        if ((float) $f['total_amount'] <= 0 || !$this->model->items((int) $f['id'])) {
            setFlash('error', 'Pengajuan Fee tanpa baris barang tidak bisa diajukan.');
            $this->redirect('pengajuan_fee', 'detail', ['id' => $f['id']]);
        }
        $this->doTransition($f, [PengajuanFee::DRAFT], PengajuanFee::DIAJUKAN, 'submit', 'Diajukan untuk diperiksa',
            ['submitted_at' => date('Y-m-d H:i:s')], 'diajukan');
    }

    /** Diajukan -> Diperiksa (pemeriksa). */
    public function check()
    {
        Middleware::requirePermission('pengajuan_fee', 'check');
        $this->requirePost();
        $f = $this->load((int) ($_POST['id'] ?? 0));
        $note = $this->note();
        $this->doTransition($f, [PengajuanFee::DIAJUKAN], PengajuanFee::DIPERIKSA, 'check',
            'Diperiksa oleh ' . currentUserName() . ($note !== '' ? ": {$note}" : ''),
            ['checked_by' => (int) currentUserId(), 'checked_at' => date('Y-m-d H:i:s')], 'diperiksa');
    }

    /** Diperiksa -> Disetujui (penyetuju). */
    public function approve()
    {
        Middleware::requirePermission('pengajuan_fee', 'approve');
        $this->requirePost();
        $f = $this->load((int) ($_POST['id'] ?? 0));
        $note = $this->note();
        $this->doTransition($f, [PengajuanFee::DIPERIKSA], PengajuanFee::DISETUJUI, 'approve',
            'Disetujui oleh ' . currentUserName() . ($note !== '' ? ": {$note}" : ''),
            ['approved_by' => (int) currentUserId(), 'approved_at' => date('Y-m-d H:i:s')], 'disetujui');
    }

    /** Tolak: tahap Diajukan oleh pemeriksa ('check'), tahap Diperiksa oleh penyetuju ('approve'). Catatan WAJIB. */
    public function reject()
    {
        if (!can('pengajuan_fee', 'check') && !can('pengajuan_fee', 'approve')) {
            Middleware::requirePermission('pengajuan_fee', 'check'); // 403 sebelum apa pun dibaca
        }
        $this->requirePost();
        $f = $this->load((int) ($_POST['id'] ?? 0));
        $perm = $f['status'] === PengajuanFee::DIPERIKSA ? 'approve' : 'check';
        Middleware::requirePermission('pengajuan_fee', $perm);
        $note = $this->note();
        if (mb_strlen($note) < 3) {
            setFlash('error', 'Alasan penolakan wajib diisi (3-500 karakter).');
            $this->redirect('pengajuan_fee', 'detail', ['id' => $f['id']]);
        }
        $this->doTransition($f, [PengajuanFee::DIAJUKAN, PengajuanFee::DIPERIKSA], PengajuanFee::DITOLAK, 'reject',
            'Ditolak oleh ' . currentUserName() . ": {$note}",
            ['rejected_by' => (int) currentUserId(), 'rejected_at' => date('Y-m-d H:i:s'), 'reject_note' => $note], 'ditolak');
    }

    /** Ditolak -> Draft untuk direvisi; catatan & pemeriksaan/persetujuan sebelumnya dibersihkan dari header tapi tetap di riwayat. */
    public function revise()
    {
        Middleware::requirePermission('pengajuan_fee', 'edit');
        $this->requirePost();
        $f = $this->load((int) ($_POST['id'] ?? 0));
        $this->doTransition($f, [PengajuanFee::DITOLAK], PengajuanFee::DRAFT, 'revise',
            'Dikembalikan ke Draft untuk direvisi (penolakan sebelumnya: ' . ($f['reject_note'] ?: '-') . ')',
            ['submitted_at' => null, 'checked_by' => null, 'checked_at' => null, 'approved_by' => null, 'approved_at' => null,
             'rejected_by' => null, 'rejected_at' => null, 'reject_note' => null],
            'dikembalikan ke Draft untuk direvisi');
    }

    public function delete()
    {
        Middleware::requirePermission('pengajuan_fee', 'delete');
        $this->requirePost();
        $f = $this->load((int) ($_POST['id'] ?? 0));
        $id = (int) $f['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $ok = $this->model->softDeleteGuarded($id, currentUserId());
            if ($ok) {
                $this->model->addHistory($id, currentUserId(), 'delete', $f['status'], $f['status'], 'Pengajuan Fee dihapus');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('PengajuanFee delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus pengajuan.');
            $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
        }
        if (!$ok) {
            setFlash('error', 'Hanya pengajuan berstatus Draft atau Ditolak yang bisa dihapus. Yang sudah diproses tersimpan permanen.');
            $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
        }
        $this->logAct('delete', $id, $f['fee_number'], 'dihapus (status ' . PengajuanFee::statusLabel($f['status']) . ')');
        setFlash('success', "Pengajuan Fee {$f['fee_number']} dihapus.");
        $this->redirect('pengajuan_fee', 'index');
    }

    // =================== Cetak (format sheet "Pengajuan Fee") ===================

    public function print()
    {
        $f = $this->load((int) ($_GET['id'] ?? 0));
        $this->activityLog->log(currentUserId(), 'pengajuan_fee', 'print', "Cetak Pengajuan Fee {$f['fee_number']} (#{$f['id']})");
        $this->view('pengajuan_fee/print', [
            'pageTitle' => 'Cetak Pengajuan Fee',
            'f'         => $f,
            'items'     => $this->model->items((int) $f['id']),
            'autoprint' => !empty($_GET['autoprint']),
        ]);
    }

    // =================== Helper privat ===================

    public function availableActions(array $f): array
    {
        $s = $f['status'];
        $a = [];
        if ($s === PengajuanFee::DRAFT) {
            $a['edit'] = can('pengajuan_fee', 'edit');
            $a['submit'] = can('pengajuan_fee', 'submit');
            $a['delete'] = can('pengajuan_fee', 'delete');
        } elseif ($s === PengajuanFee::DIAJUKAN) {
            $a['check'] = can('pengajuan_fee', 'check');
            $a['reject'] = can('pengajuan_fee', 'check');
        } elseif ($s === PengajuanFee::DIPERIKSA) {
            $a['approve'] = can('pengajuan_fee', 'approve');
            $a['reject'] = can('pengajuan_fee', 'approve');
        } elseif ($s === PengajuanFee::DITOLAK) {
            $a['revise'] = can('pengajuan_fee', 'edit');
            $a['delete'] = can('pengajuan_fee', 'delete');
        }
        return array_filter($a);
    }

    private function load(int $id): array
    {
        $f = $id > 0 ? $this->model->findWithRelations($id) : null;
        if (!$f) {
            setFlash('error', 'Pengajuan Fee tidak ditemukan.');
            $this->redirect('pengajuan_fee', 'index');
        }
        return $f;
    }

    private function requireDraft(array $f): void
    {
        if ($f['status'] !== PengajuanFee::DRAFT) {
            setFlash('error', 'Hanya pengajuan berstatus Draft yang bisa diedit.'
                . ($f['status'] === PengajuanFee::DITOLAK ? ' Untuk pengajuan Ditolak, tekan "Revisi" dulu.' : ' Yang sudah diajukan/disetujui terkunci.'));
            $this->redirect('pengajuan_fee', 'detail', ['id' => $f['id']]);
        }
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pengajuan_fee', 'index');
        }
        verifyCsrf();
    }

    private function note(): string
    {
        return is_string($_POST['note_action'] ?? null) ? mb_substr(trim($_POST['note_action']), 0, 500) : '';
    }

    private function logAct(string $action, int $id, string $number, string $detail): void
    {
        $this->activityLog->log(currentUserId(), 'pengajuan_fee', $action, "Pengajuan Fee {$number} (#{$id}): {$detail}");
    }

    private function doTransition(array $f, array $from, string $to, string $action, string $historyNote, array $extra, string $verb): void
    {
        $id = (int) $f['id'];
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $ok = $this->model->transition($id, $from, $to, $extra);
            if ($ok) {
                $this->model->addHistory($id, currentUserId(), $action, $f['status'], $to, $historyNote);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("PengajuanFee {$action} error: " . $e->getMessage());
            setFlash('error', 'Gagal memproses pengajuan. Silakan coba lagi.');
            $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
        }
        if (!$ok) {
            setFlash('error', 'Aksi ditolak: status saat ini (' . PengajuanFee::statusLabel($f['status']) . ') tidak mengizinkan aksi ini, atau sudah diubah pihak lain.');
            $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
        }
        $this->logAct($action, $id, $f['fee_number'], $verb);
        setFlash('success', "Pengajuan Fee {$f['fee_number']} {$verb}.");
        $this->redirect('pengajuan_fee', 'detail', ['id' => $id]);
    }

    private function headerRow(array $d, array $calc): array
    {
        return [
            'fee_date'         => $d['fee_date'],
            'city'             => $d['city'] ?: null,
            'invoice_number'   => $d['invoice_number'] ?: null,
            'customer_name'    => $d['customer_name'] ?: null,
            'sales_name'       => $d['sales_name'] ?: null,
            'billing_status'   => $d['billing_status'] ?: null,
            'recipient_name'   => $d['recipient_name'] ?: null,
            'recipient_tax_id' => $d['recipient_tax_id'] ?: null,
            'fee_percent'      => $d['fee_percent'],
            'ppn_percent'      => $d['ppn_percent'],
            'pph_percent'      => $d['pph_percent'],
            'note'             => $d['note'] ?: null,
            'nb'               => $d['nb'] ?: null,
            'total_amount'     => $calc['total_amount'],
            'total_fee'        => $calc['total_fee'],
            'pph_amount'       => $calc['pph_amount'],
            'net_fee'          => $calc['net_fee'],
        ];
    }

    private function renderForm(string $mode, ?array $f, array $items, ?array $posted): void
    {
        $this->view('pengajuan_fee/form', [
            'pageTitle' => $mode === 'edit' ? 'Edit Pengajuan Fee' : 'Buat Pengajuan Fee',
            'mode'      => $mode,
            'f'         => $f,
            'items'     => $items,
            'posted'    => $posted,
            'number'    => $f['fee_number'] ?? $this->model->previewNumber($posted['fee_date'] ?? date('Y-m-d')),
            'cities'    => $this->model->distinctValues('city'),
            'salesList' => $this->model->distinctValues('sales_name'),
            'recipients' => $this->model->distinctValues('recipient_name'),
            'canSubmit' => can('pengajuan_fee', 'submit'),
        ]);
    }

    /** Baca & validasi input. Mengembalikan [header, baris(+total/fee dihitung server), hasil compute(), errors]. */
    private function collectAndValidate(): array
    {
        $s = fn($k, $max = 255) => mb_substr(is_string($_POST[$k] ?? null) ? trim($_POST[$k]) : '', 0, $max);
        $errors = [];
        $data = [
            'fee_date' => $s('fee_date', 10), 'city' => $s('city', 100), 'invoice_number' => $s('invoice_number', 60),
            'customer_name' => $s('customer_name', 150), 'sales_name' => $s('sales_name', 150), 'billing_status' => $s('billing_status', 120),
            'recipient_name' => $s('recipient_name', 150), 'recipient_tax_id' => $s('recipient_tax_id', 40),
            'note' => $s('note', 500), 'nb' => $s('nb', 500),
        ];
        if (!isValidDateString($data['fee_date'])) {
            $errors[] = 'Tanggal tidak valid.';
        }
        // persentase: kosong = default Excel
        $pct = function (string $key, string $label, float $default, float $max) use ($s, &$errors, &$data) {
            $raw = $s($key, 10);
            $data[$key . '_raw'] = $raw;
            if ($raw === '') {
                return $data[$key] = $default;
            }
            $v = str_replace(',', '.', $raw);
            if (!preg_match('/^[0-9]+(\.[0-9]{1,3})?$/', $v) || (float) $v > $max) {
                $errors[] = "{$label} harus berupa angka 0-" . (int) $max . '.';
                return $data[$key] = $default;
            }
            return $data[$key] = round((float) $v, 3);
        };
        $feeP = $pct('fee_percent', 'Fee %', PengajuanFee::DEFAULT_FEE_PERCENT, 100);
        $ppnP = $pct('ppn_percent', 'PPN %', PengajuanFee::DEFAULT_PPN_PERCENT, 100);
        $pphP = $pct('pph_percent', 'Potong PPh %', PengajuanFee::DEFAULT_PPH_PERCENT, 100);

        $col = function ($k) { return (isset($_POST[$k]) && is_array($_POST[$k])) ? array_values($_POST[$k]) : []; };
        $names = $col('item_name');
        $qtys = $col('qty');
        $units = $col('unit');
        $prices = $col('price');
        $n = max(count($names), count($qtys), count($units), count($prices));
        if ($n > self::MAX_ROWS) {
            $errors[] = 'Jumlah baris melebihi batas ' . self::MAX_ROWS . '.';
            $n = self::MAX_ROWS;
        }

        $items = [];
        for ($i = 0; $i < $n; $i++) {
            $g = fn($arr) => is_string($arr[$i] ?? null) ? trim($arr[$i]) : '';
            $name = mb_substr($g($names), 0, 255);
            $qtyRaw = $g($qtys);
            $unit = mb_substr($g($units), 0, 30);
            $priceRaw = $g($prices);
            if ($name === '' && $qtyRaw === '' && $unit === '' && $priceRaw === '') {
                continue;
            }
            $line = count($items) + 1;
            $qty = parseCurrencyInput($qtyRaw);
            $price = parseCurrencyInput($priceRaw);
            if ($name === '') {
                $errors[] = "Baris {$line}: Barang wajib diisi.";
            }
            if ($qty <= 0 || $qty > PengajuanFee::MAX_AMOUNT) {
                $errors[] = "Baris {$line}: QTY harus lebih dari 0.";
            }
            if ($price <= 0 || $price > PengajuanFee::MAX_AMOUNT) {
                $errors[] = "Baris {$line}: Harga harus lebih dari 0.";
            } elseif ($qty * $price > PengajuanFee::MAX_AMOUNT) {
                $errors[] = "Baris {$line}: Total terlalu besar.";
            }
            $items[] = ['item_name' => $name, 'qty' => $qty, 'unit' => $unit, 'price' => $price, 'total' => 0.0, 'fee' => 0.0, 'qty_raw' => $qtyRaw, 'price_raw' => $priceRaw];
        }
        if (!$items) {
            $errors[] = 'Minimal harus ada 1 baris barang.';
        }

        $calc = PengajuanFee::compute($items, $feeP, $ppnP, $pphP);
        foreach ($items as $k => $_) {
            $items[$k]['total'] = $calc['rows'][$k]['total'];
            $items[$k]['fee'] = $calc['rows'][$k]['fee'];
        }
        if ($data['nb'] === '' && $feeP > 0) {
            // NB bawaan seperti Excel: "Fee 10% dari DPP" (hanya bila dikosongkan pengguna)
            $data['nb'] = 'Fee ' . rtrim(rtrim(number_format($feeP, 3, '.', ''), '0'), '.') . '% dari DPP';
        }
        return [$data, $items, $calc, array_values(array_unique($errors))];
    }
}
