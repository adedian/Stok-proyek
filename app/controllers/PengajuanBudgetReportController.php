<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PengajuanBudget.php';
require_once ROOT_PATH . '/app/models/MasterBank.php';
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
