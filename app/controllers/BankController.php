<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/BankTransaction.php';
require_once ROOT_PATH . '/app/models/BankTransactionItem.php';
require_once ROOT_PATH . '/app/models/MasterBank.php';
require_once ROOT_PATH . '/app/models/MasterRekening.php';
require_once ROOT_PATH . '/app/models/UserPicAssignment.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/CashNumber.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Modul Bank (Revisi Kas/Bank) -- KHUSUS Super Admin & Accounting (lihat
 * config/permissions.php modul 'bank'). Analog Kas tapi lebih flat (tanpa
 * kategori/barang/qty/satuan/integrasi stok -- Bank tidak menyentuh stok).
 * Tidak ada second-level auth (Accounting sudah exempt dari gerbang Kas).
 *
 * Rincian (Revisi lanjutan): satu transaksi Bank = 1 header + banyak baris
 * {uraian, amount} di bank_transaction_items (pola sederhana dari Kas, TANPA
 * kategori/barang/project/qty/satuan per baris). Header bank_transactions.uraian
 * (concat "; ") & .amount (SUM baris) TETAP dijaga sebagai ringkasan
 * denormalisasi -- persis pola cash_transactions.total_amount -- supaya
 * list/laporan/print Bank yang sudah ada TIDAK perlu tahu soal baris rincian.
 *
 * No Bukti pakai mekanisme atomic yang sama dengan Kas (CashNumber), TAPI
 * prefix dipecah per Mutasi (revisi lanjutan) -- BUKAN satu prefix "BK"
 * tunggal untuk semua transaksi Bank: Masuk -> "BM", Keluar -> "BK". Dua
 * sequence independen (masing-masing baris sendiri di cash_number_counters),
 * disemai dari tabel bank_transactions sendiri (bukan cash_transactions)
 * lewat parameter $table CashNumber::next(). Data lama (sebelum revisi ini)
 * semuanya berprefix "BK" apa pun mutasinya -- SENGAJA tidak direnumber,
 * counter "BK" melanjutkan dari situ; hanya transaksi baru yang ikut aturan
 * split BM/BK.
 */
class BankController extends Controller
{
    /** Prefix No Bukti sesuai Mutasi -- Masuk = "BM", Keluar (atau nilai lain/kosong) = "BK". */
    private function noBuktiPrefix(string $mutasi): string
    {
        return $mutasi === 'masuk' ? 'BM' : 'BK';
    }

    private BankTransaction $model;
    private BankTransactionItem $itemModel;
    private MasterBank $bankModel;
    private MasterRekening $rekeningModel;
    private UserPicAssignment $picModel;
    private Project $projectModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('bank', 'view');
        $this->model = new BankTransaction();
        $this->itemModel = new BankTransactionItem();
        $this->bankModel = new MasterBank();
        $this->rekeningModel = new MasterRekening();
        $this->picModel = new UserPicAssignment();
        $this->projectModel = new Project();
        $this->activityLog = new ActivityLog();
    }

    private function collectFilters(): array
    {
        return [
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
            'project_id' => $_GET['project_id'] ?? '',
            'bank_ids'   => isset($_GET['bank_ids']) && is_array($_GET['bank_ids']) ? array_map('intval', $_GET['bank_ids']) : [],
            'mutasi'     => $_GET['mutasi'] ?? '',
            'keyword'    => trim($_GET['keyword'] ?? ''),
        ];
    }

    /** Dropdown PIC form Bank -- sumber sama dengan Kas (Master Data > PIC Kas), semua nama (Bank tidak ber-scope PIC). */
    private function picOptions(): array
    {
        return $this->picModel->allPicNames();
    }

    /**
     * List Bank berdiri sendiri DIHAPUS dari navigasi (revisi lanjutan) --
     * transaksi Bank sekarang digabung tampil di menu Kas (CashController::index(),
     * hanya untuk can('bank','view')). Redirect supaya bookmark/link lama tidak mati.
     */
    public function index(): void
    {
        $this->redirect('cash', 'index');
    }

    public function create(): void
    {
        Middleware::requirePermission('bank', 'create');
        $this->view('bank/form', [
            'pageTitle' => 'Tambah Transaksi Bank',
            'mode'      => 'create',
            'row'       => null,
            'items'     => [],
            'banks'     => $this->bankModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'picOptions'      => $this->picOptions(),
            'rekeningOptions' => $this->rekeningModel->activeList(),
            // Prefix (jadi nomor) baru pasti setelah Mutasi dipilih -- pratinjau
            // diisi lewat AJAX previewNoBukti() saat user memilih Masuk/Keluar
            // (lihat bank/form.php), sama pola dengan No Bukti Kas per-PIC.
            // Nomor RESMI tetap dibuat server-side & atomic saat store().
            'noBuktiPreview'  => '',
        ]);
    }

    /** AJAX: pratinjau No Bukti saat Mutasi dipilih di form Tambah Bank. */
    public function previewNoBukti(): void
    {
        Middleware::requirePermission('bank', 'create');
        $mutasiRaw = $_GET['mutasi'] ?? '';
        $mutasi = $mutasiRaw === 'masuk' ? 'masuk' : ($mutasiRaw === 'keluar' ? 'keluar' : '');
        if ($mutasi === '') {
            $this->json(['preview' => '']);
        }
        $prefix = $this->noBuktiPrefix($mutasi);
        $this->json(['preview' => (new CashNumber())->preview($prefix, 'bank_transactions'), 'prefix' => $prefix]);
    }

    public function store(): void
    {
        Middleware::requirePermission('bank', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('bank', 'create');
        }
        verifyCsrf();

        $data  = $this->collectInput();
        $items = $this->collectItems();
        $errors = $this->validate($data, $items);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('bank', 'create');
        }

        $total = $this->sumItems($items);
        $uraianConcat = implode('; ', array_column($items, 'uraian'));

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            // No Bukti dibuat SERVER-SIDE & ATOMIC, prefix mengikuti Mutasi --
            // Masuk -> "BM", Keluar -> "BK" -- dua sequence terpisah (lihat
            // catatan class di atas). Mutasi sudah divalidasi non-kosong oleh
            // validate() sebelum sampai sini.
            $prefix = $this->noBuktiPrefix($data['mutasi']);
            $noBukti = (new CashNumber())->next($prefix, 'bank_transactions');
            $guard = 0;
            while ($this->model->noBuktiExists($noBukti) && $guard++ < 50) {
                $noBukti = (new CashNumber())->next($prefix, 'bank_transactions');
            }
            $trxId = $this->model->create(array_merge($data, [
                'no_bukti'   => $noBukti,
                'uraian'     => $uraianConcat,
                'amount'     => $total,
                'created_by' => currentUserId(),
            ]));
            $this->saveItems($trxId, $items);
            $this->activityLog->log(currentUserId(), 'bank', 'create', "Transaksi Bank {$data['mutasi']} '{$noBukti}' dibuat, " . count($items) . ' baris, total ' . formatRupiah($total));
            $pdo->commit();
            setFlash('success', 'Transaksi Bank berhasil disimpan.');
            $this->redirect('cash', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Bank store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan transaksi Bank.');
            $this->redirect('bank', 'create');
        }
    }

    public function edit(): void
    {
        Middleware::requirePermission('bank', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $row = $this->model->find($id);
        if (!$row) {
            setFlash('error', 'Transaksi Bank tidak ditemukan.');
            $this->redirect('cash', 'index');
        }
        $this->view('bank/form', [
            'pageTitle' => 'Edit Transaksi Bank',
            'mode'      => 'edit',
            'row'       => $row,
            'items'     => $this->itemModel->byTransaction($id),
            'banks'     => $this->bankModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'picOptions'      => $this->picOptions(),
            'rekeningOptions' => $this->rekeningModel->activeList(),
            'noBuktiPreview'  => $row['no_bukti'],
        ]);
    }

    public function update(): void
    {
        Middleware::requirePermission('bank', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->model->find($id);
        if (!$existing) {
            setFlash('error', 'Transaksi Bank tidak ditemukan.');
            $this->redirect('cash', 'index');
        }

        $data  = $this->collectInput();
        $items = $this->collectItems();
        $errors = $this->validate($data, $items);
        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('bank', 'edit', ['id' => $id]);
        }

        $total = $this->sumItems($items);
        $uraianConcat = implode('; ', array_column($items, 'uraian'));

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $this->model->updateById($id, array_merge($data, [
                'uraian' => $uraianConcat,
                'amount' => $total,
            ]));
            $this->itemModel->deleteByTransaction($id);
            $this->saveItems($id, $items);
            $this->activityLog->log(currentUserId(), 'bank', 'update', "Transaksi Bank #{$id} ('{$existing['no_bukti']}') diperbarui, " . count($items) . ' baris, total ' . formatRupiah($total));
            $pdo->commit();
            setFlash('success', 'Transaksi Bank berhasil diperbarui.');
            $this->redirect('cash', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Bank update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui transaksi Bank.');
            $this->redirect('bank', 'edit', ['id' => $id]);
        }
    }

    public function delete(): void
    {
        Middleware::requirePermission('bank', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->model->find($id);
        if ($row) {
            $this->model->deleteById($id);
            $this->activityLog->log(currentUserId(), 'bank', 'delete', "Transaksi Bank #{$id} ('{$row['no_bukti']}') dihapus");
            setFlash('success', 'Transaksi Bank dipindahkan ke Tempat Sampah.');
        } else {
            setFlash('error', 'Transaksi Bank tidak ditemukan.');
        }
        $this->redirect('cash', 'index');
    }

    /**
     * CETAK VOUCHER BANK. Terima daftar id transaksi Bank dari halaman Kas
     * (menu Bank digabung ke sana), tampilkan halaman PRATINJAU (SATU voucher
     * BUKTI BANK KELUAR/MASUK per No Bukti) di dalam layout aplikasi -- pola
     * identik CashController::printVoucher(). Tidak perlu scoping PIC/divisi
     * seperti Kas -- modul Bank sudah tunggal untuk SA/Accounting saja
     * (Middleware::requirePermission di constructor sudah menegakkan itu).
     */
    public function printVoucher(): void
    {
        $raw = $_GET['ids'] ?? '';
        $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($v) => $v > 0)));

        $vouchers = [];
        foreach ($ids as $id) {
            $header = $this->model->findWithRelations($id);
            if (!$header) {
                continue;
            }
            $vouchers[] = [
                'header' => $header,
                'items'  => $this->itemModel->byTransaction($id),
            ];
        }

        if (empty($vouchers)) {
            setFlash('error', 'Silakan pilih minimal satu transaksi Bank yang valid untuk dicetak.');
            $this->redirect('cash', 'index');
        }

        $this->activityLog->log(
            currentUserId(),
            'bank',
            'print',
            'Pratinjau/cetak voucher Bank terpilih: ' . implode(', ', array_map(fn($v) => $v['header']['no_bukti'], $vouchers))
        );

        $from    = ($_GET['from'] ?? '') === 'report' ? 'report' : 'index';
        $backUrl = $from === 'report'
            ? BASE_URL . '/index.php?module=cash&action=report'
            : BASE_URL . '/cash';

        $this->view('bank/voucher_preview', [
            'pageTitle' => 'Cetak Voucher Bank',
            'vouchers'  => $vouchers,
            'vCompany'  => ((new SystemSetting())->getGroup('company')['company_name']) ?: 'Perusahaan',
            'backUrl'   => $backUrl,
        ]);
    }

    /**
     * Laporan Bank berdiri sendiri DIHAPUS dari navigasi (revisi lanjutan) --
     * digabung ke Laporan Kas (centang Bank di filter). Redirect saja.
     */
    public function report(): void
    {
        $this->redirect('cash', 'report');
    }

    public function printReport(): void
    {
        [$ledger, $meta] = $this->buildReportData();
        $this->activityLog->log(currentUserId(), 'bank', 'print', 'Cetak PDF Laporan Bank (' . $meta['period'] . ')');
        ob_start();
        $company = $meta['company'];
        $periodText = $meta['period'];
        $reportTitle = $meta['title'];
        require ROOT_PATH . '/app/views/bank/_report_pdf.php';
        $html = ob_get_clean();
        streamPdf($html, 'laporan_bank_' . date('Ymd_His'));
    }

    private function buildReportData(): array
    {
        $filters = $this->collectFilters();
        $ledger = $this->model->reportLedger($filters);

        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $period = 'Periode : '
            . (!empty($filters['date_from']) ? formatTanggal($filters['date_from']) : 'Awal')
            . ' - '
            . (!empty($filters['date_to']) ? formatTanggal($filters['date_to']) : 'Sekarang');

        $title = 'LAPORAN BANK — SEMUA PROJECT';
        if (!empty($filters['project_id'])) {
            $p = $this->projectModel->find((int) $filters['project_id']);
            if ($p) {
                $title = 'LAPORAN BANK ' . mb_strtoupper($p['project_name']);
            }
        }

        return [$ledger, ['company' => $companyName, 'period' => $period, 'title' => $title]];
    }

    private function collectInput(): array
    {
        // uraian & amount SENGAJA tidak dibaca di sini -- keduanya diturunkan
        // dari baris rincian (collectItems()/sumItems()), sama pola dengan
        // cash_transactions.total_amount.
        return [
            'trx_date'    => trim($_POST['trx_date'] ?? ''),
            'bank_id'     => (int) ($_POST['bank_id'] ?? 0),
            'rekening_id' => !empty($_POST['rekening_id']) ? (int) $_POST['rekening_id'] : null,
            'project_id'  => !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null,
            'pic'         => trim($_POST['pic'] ?? '') ?: null,
            'mutasi'      => ($_POST['mutasi'] ?? '') === 'masuk' ? 'masuk'
                : (($_POST['mutasi'] ?? '') === 'keluar' ? 'keluar' : ''),
        ];
    }

    /**
     * Baris rincian Bank dari POST -- HANYA {uraian, amount} (Revisi lanjutan:
     * "seperti Rincian Kas, tapi cuma uraian + harga saja" -- tidak ada
     * kategori/barang/project/qty/satuan seperti Kas, Bank tidak menyentuh stok).
     */
    private function collectItems(): array
    {
        $uraian  = $_POST['item_uraian'] ?? [];
        $amount  = $_POST['item_amount'] ?? [];
        $out = [];
        for ($i = 0; $i < count($uraian); $i++) {
            $u = trim((string) ($uraian[$i] ?? ''));
            $a = parseCurrencyInput($amount[$i] ?? 0);
            if ($u === '' && abs($a) < 0.005) {
                continue; // baris kosong -> abaikan
            }
            $out[] = ['uraian' => $u, 'amount' => $a];
        }
        return $out;
    }

    private function sumItems(array $items): float
    {
        return round(array_sum(array_column($items, 'amount')), 2);
    }

    private function saveItems(int $trxId, array $items): void
    {
        foreach ($items as $it) {
            $this->itemModel->create([
                'bank_transaction_id' => $trxId,
                'uraian' => $it['uraian'],
                'amount' => $it['amount'],
            ]);
        }
    }

    private function validate(array $d, array $items = []): array
    {
        $errors = [];
        if ($d['trx_date'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['trx_date'])) {
            $errors[] = 'Tanggal wajib diisi.';
        }
        if ($d['bank_id'] <= 0 || !$this->bankModel->find($d['bank_id'])) {
            $errors[] = 'Bank wajib dipilih.';
        }
        if (!empty($d['rekening_id']) && !$this->rekeningModel->find((int) $d['rekening_id'])) {
            $errors[] = 'Rekening yang dipilih tidak valid.';
        }
        if (!empty($d['project_id']) && !$this->projectModel->find((int) $d['project_id'])) {
            $errors[] = 'Project yang dipilih tidak valid.';
        }
        if ($d['mutasi'] === '') {
            $errors[] = 'Mutasi wajib dipilih (Masuk / Keluar).';
        }
        if (empty($items)) {
            $errors[] = 'Minimal 1 baris rincian (Uraian, Nominal) wajib diisi.';
        } else {
            foreach ($items as $i => $it) {
                $n = $i + 1;
                if ($it['uraian'] === '') {
                    $errors[] = "Baris {$n}: Uraian wajib diisi.";
                }
                if (abs((float) $it['amount']) < 0.005) {
                    $errors[] = "Baris {$n}: Nominal wajib diisi.";
                }
            }
        }
        return $errors;
    }
}
