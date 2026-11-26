<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PengajuanBudget.php';
require_once ROOT_PATH . '/app/models/MasterBank.php';
require_once ROOT_PATH . '/app/models/BankBalanceAdjustment.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Laporan Pengajuan Budget -- "PERMINTAAN OTORISASI" (menu kedua grup Pengajuan Budget).
 *
 * Akses: HANYA Purwati & Nissa (izin per-akun) + Super Admin; modul izin terpisah
 * 'pengajuan_budget_report' (view / print / export), ditegakkan di backend.
 *
 * Isi laporan dibangun PengajuanBudget::buildReport(): baris dikelompokkan per Rekening Sumber
 * (warna per rekening, nomor urut mulai 1 per kelompok, TOTAL per kelompok, TOTAL keseluruhan),
 * lalu blok "Saldo ... tgl." (saldo AKTUAL modul Bank, hanya dibaca) dan "Sisa Saldo setelah pembayaran".
 * Layar, Cetak, dan Excel memakai data yang SAMA persis.
 */
class PengajuanBudgetReportController extends Controller
{
    private PengajuanBudget $model;

    public function __construct()
    {
        Middleware::requirePermission('pengajuan_budget_report', 'view');
        $this->model = new PengajuanBudget();
    }

    private function filters(): array
    {
        return PengajuanBudget::normalizeReportFilters($_GET);
    }

    /** Query string filter (tanpa kunci turunan) untuk tautan Cetak/Excel. */
    private function filterQuery(array $f): array
    {
        return array_filter([
            'tanggal' => $f['tanggal'], 'date_from' => $f['tanggal'] === '' ? $f['date_from'] : '', 'date_to' => $f['tanggal'] === '' ? $f['date_to'] : '',
            'bank_id' => $f['bank_id'], 'created_by' => $f['created_by'], 'status' => $f['status'], 'category_id' => $f['category_id'],
        ], fn($v) => $v !== '' && $v !== 0 && $v !== null);
    }

    public function index()
    {
        $f = $this->filters();
        $this->view('pengajuan_budget_report/list', [
            'pageTitle'  => 'Laporan Pengajuan Budget',
            'activeModuleOverride' => 'report', // tampil sebagai bagian menu Laporan
            'filters'    => $f,
            'report'     => $this->model->buildReport($f),
            'filterQuery' => $this->filterQuery($f),
            'banks'      => (new MasterBank())->filterList(),
            'creators'   => $this->model->creatorOptions(),
            'categories' => $this->model->categoryOptions(),
            'statuses'   => PengajuanBudget::STATUS_LABELS,
        ]);
    }

    public function print()
    {
        Middleware::requirePermission('pengajuan_budget_report', 'print');
        $f = $this->filters();
        $report = $this->model->buildReport($f);
        (new ActivityLog())->log(currentUserId(), 'pengajuan_budget_report', 'print',
            'Cetak Permintaan Otorisasi ' . self::periodLabel($f) . ' (' . $report['item_count'] . ' baris)');
        $this->view('pengajuan_budget_report/print', [
            'pageTitle'  => 'Cetak Permintaan Otorisasi',
            'activeModuleOverride' => 'report',
            'filters'    => $f,
            'report'     => $report,
            'company'    => (new SystemSetting())->getGroup('company'),
            'filterNote' => $this->filterNote($f),
            'autoprint'  => !empty($_GET['autoprint']),
            'backQuery'  => $this->filterQuery($f),
        ]);
    }

    public function exportExcel()
    {
        Middleware::requirePermission('pengajuan_budget_report', 'export');
        $f = $this->filters();
        $report = $this->model->buildReport($f);
        if (!$report['groups']) {
            setFlash('error', 'Tidak ada data Pengajuan Budget sesuai filter ini untuk diexport.');
            $this->redirect('pengajuan_budget_report', 'index', $this->filterQuery($f));
        }
        (new ActivityLog())->log(currentUserId(), 'pengajuan_budget_report', 'export',
            'Export Excel Permintaan Otorisasi ' . self::periodLabel($f) . ' (' . $report['item_count'] . ' baris)');
        require_once ROOT_PATH . '/app/helpers/excel_pengajuan_budget_helper.php';
        streamPermintaanOtorisasi($report, self::periodLabel($f), $this->filterNote($f), 'Permintaan_Otorisasi_' . date('Ymd_His'));
    }

    // =================== Saldo bank manual ===================

    /** Rekening yang boleh diedit saldonya: Master Bank aktif berjenis Loan/HR/Kas. */
    private function editableBank(int $id): ?array
    {
        $b = $id > 0 ? (new MasterBank())->find($id) : null;
        return ($b && (int) $b['is_active'] === 1 && in_array($b['jenis'], ['loan', 'hr', 'kas'], true)) ? $b : null;
    }

    /** Query kembali ke laporan (tanggal/filter yang sedang dilihat). */
    private function backQuery(): array
    {
        $in = $_POST + $_GET;
        $q = [];
        foreach (['tanggal', 'date_from', 'date_to'] as $k) {
            $v = is_string($in['r_' . $k] ?? null) ? $in['r_' . $k] : '';
            if ($v !== '' && isValidDateString($v)) {
                $q[$k] = $v;
            }
        }
        return $q;
    }

    public function editSaldo()
    {
        Middleware::requirePermission('pengajuan_budget_report', 'edit_saldo');
        $bank = $this->editableBank((int) ($_GET['bank_id'] ?? 0));
        if (!$bank) {
            setFlash('error', 'Rekening tidak ditemukan atau bukan rekening aktif.');
            $this->redirect('pengajuan_budget_report', 'index', $this->backQuery());
        }
        $date = is_string($_GET['date'] ?? null) && isValidDateString($_GET['date']) ? $_GET['date'] : date('Y-m-d');
        if ($date > date('Y-m-d')) {
            $date = date('Y-m-d');
        }
        $cur = (new BankBalanceAdjustment())->balanceAt((int) $bank['id'], $date);
        $this->view('pengajuan_budget_report/edit_saldo', [
            'pageTitle' => 'Edit Saldo Bank',
            'activeModuleOverride' => 'report',
            'bank'      => $bank,
            'date'      => $date,
            'current'   => $cur,
            'posted'    => null,
            'backQuery' => $this->backQuery(),
        ]);
    }

    /** AJAX: saldo rekening pada tanggal berlaku yang dipilih (untuk kolom "Saldo Saat Ini"). */
    public function balanceAt()
    {
        Middleware::requirePermission('pengajuan_budget_report', 'edit_saldo');
        $bank = $this->editableBank((int) ($_GET['bank_id'] ?? 0));
        $date = $_GET['date'] ?? '';
        if (!$bank || !is_string($date) || !isValidDateString($date)) {
            $this->json(['error' => 'Parameter tidak valid.'], 422);
        }
        $cur = (new BankBalanceAdjustment())->balanceAt((int) $bank['id'], $date);
        $this->json(['saldo' => $cur['saldo'], 'manual_date' => $cur['manual']['effective_date'] ?? null]);
    }

    public function saveSaldo()
    {
        Middleware::requirePermission('pengajuan_budget_report', 'edit_saldo');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pengajuan_budget_report', 'index');
        }
        verifyCsrf();

        $bankId = (int) ($_POST['bank_id'] ?? 0);
        $bank = $this->editableBank($bankId);
        $back = $this->backQuery();
        if (!$bank) {
            setFlash('error', 'Rekening tidak ditemukan atau bukan rekening aktif.');
            $this->redirect('pengajuan_budget_report', 'index', $back);
        }
        $date = is_string($_POST['effective_date'] ?? null) ? trim($_POST['effective_date']) : '';
        $note = is_string($_POST['note'] ?? null) ? trim($_POST['note']) : '';
        $rawNew = is_string($_POST['new_balance'] ?? null) ? trim($_POST['new_balance']) : '';
        $newBalance = parseCurrencyInput($rawNew);

        $errors = [];
        if (!isValidDateString($date)) {
            $errors[] = 'Tanggal Berlaku tidak valid.';
        } elseif ($date > date('Y-m-d')) {
            $errors[] = 'Tanggal Berlaku tidak boleh melewati hari ini.';
        }
        if ($rawNew === '' || !preg_match('/^-?[0-9][0-9,]*(\.[0-9]{1,2})?$/', $rawNew)) {
            $errors[] = 'Saldo Baru harus berupa angka (contoh: 423,198,660).';
        } elseif (abs($newBalance) > BankBalanceAdjustment::MAX_ABS) {
            $errors[] = 'Saldo Baru terlalu besar.';
        }
        if (mb_strlen($note) < 3 || mb_strlen($note) > 500) {
            $errors[] = 'Keterangan perubahan wajib diisi (3-500 karakter).';
        }
        if (empty($_POST['confirmed'])) {
            $errors[] = 'Perubahan saldo harus dikonfirmasi sebelum disimpan.';
        }

        $model = new BankBalanceAdjustment();
        if (!$errors) {
            $old = $model->balanceAt($bankId, $date)['saldo'];
            if (round($old, 2) === round($newBalance, 2)) {
                $errors[] = 'Saldo Baru sama dengan Saldo Saat Ini pada tanggal tersebut -- tidak ada yang diubah.';
            }
        }
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->view('pengajuan_budget_report/edit_saldo', [
                'pageTitle' => 'Edit Saldo Bank',
                'activeModuleOverride' => 'report',
                'bank'      => $bank,
                'date'      => isValidDateString($date) ? $date : date('Y-m-d'),
                'current'   => $model->balanceAt($bankId, isValidDateString($date) ? $date : date('Y-m-d')),
                'posted'    => ['new_balance' => $rawNew, 'note' => $note],
                'backQuery' => $back,
            ]);
            return;
        }

        try {
            $oldBalance = $model->balanceAt($bankId, $date)['saldo'];
            $model->record($bankId, $date, $newBalance, $note, currentUserId());
        } catch (Throwable $e) {
            error_log('BankBalanceAdjustment record error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan saldo. Silakan coba lagi.');
            $this->redirect('pengajuan_budget_report', 'editSaldo', ['bank_id' => $bankId, 'date' => $date] + $back);
        }
        (new ActivityLog())->log(currentUserId(), 'pengajuan_budget_report', 'edit_saldo',
            "Saldo manual {$bank['bank_name']} berlaku {$date}: " . number_format($oldBalance, 2, '.', ',') . ' -> ' . number_format($newBalance, 2, '.', ',') . " ({$note})");
        setFlash('success', "Saldo {$bank['bank_name']} berhasil diperbarui. Transaksi Kas/Bank tidak diubah.");
        $this->redirect('pengajuan_budget_report', 'index', $back);
    }

    public function saldoHistory()
    {
        $bankId = (int) ($_GET['bank_id'] ?? 0);
        $bank = $bankId > 0 ? (new MasterBank())->find($bankId) : null;
        $this->view('pengajuan_budget_report/saldo_history', [
            'pageTitle' => 'Riwayat Saldo Bank',
            'activeModuleOverride' => 'report',
            'bank'      => $bank,
            'rows'      => (new BankBalanceAdjustment())->history($bank ? (int) $bank['id'] : null),
            'backQuery' => $this->backQuery(),
        ]);
    }

    /** "TGL. 29 Oktober 2026" (satu hari) atau "PERIODE 01 Oktober 2026 s/d 29 Oktober 2026". */
    public static function periodLabel(array $f): string
    {
        $from = $f['date_from'] ?? '';
        $to = $f['date_to'] ?? '';
        if ($from !== '' && $from === $to) {
            return 'TGL. ' . formatTanggal($from);
        }
        if ($from !== '' && $to !== '') {
            return 'PERIODE ' . formatTanggal($from) . ' s/d ' . formatTanggal($to);
        }
        if ($from !== '') {
            return 'PERIODE sejak ' . formatTanggal($from);
        }
        return 'PERIODE s/d ' . formatTanggal($to);
    }

    /** Ringkasan filter non-tanggal untuk header cetak/Excel (kosong bila tidak ada). */
    private function filterNote(array $f): string
    {
        $parts = [];
        if ($f['bank_id']) {
            $b = (new MasterBank())->find((int) $f['bank_id']);
            $parts[] = 'Rekening: ' . ($b ? MasterBank::reportLabel($b) : '#' . $f['bank_id']);
        }
        if ($f['created_by']) {
            foreach ($this->model->creatorOptions() as $u) {
                if ((int) $u['id'] === (int) $f['created_by']) {
                    $parts[] = 'Pengaju: ' . $u['full_name'];
                }
            }
        }
        if ($f['category_id']) {
            foreach ($this->model->categoryOptions() as $c) {
                if ((int) $c['id'] === (int) $f['category_id']) {
                    $parts[] = 'Kategori: ' . $c['category_name'];
                }
            }
        }
        $parts[] = 'Status: ' . ($f['status'] !== '' ? PengajuanBudget::statusLabel($f['status']) : 'Semua kecuali Dibatalkan');
        return implode(' | ', $parts);
    }
}
