<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/RekapPpn.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Rekap PPN -- PPN Masukan + PPN Keluaran dalam SATU menu (acuan: sheet "PPN Masukan" & "PPN Keluaran" pada Pengajuan fee.xlsx).
 * Register faktur pajak yang diisi manual; MANDIRI (tidak terhubung ke PO / Invoice Keluar / Kas). Rumus: lihat RekapPpn.
 *
 * Halaman utama = ringkasan setoran per masa + 2 tab (PPN Masukan | PPN Keluaran) + Export Excel (2 sheet).
 * Izin (config/permissions.php, modul 'rekap_ppn'): view, create, edit, delete, export. Semua aksi tulis divalidasi
 * di backend; nilai turunan (DPP Nilai Lain, PPN, Jumlah/Total) SELALU dihitung ulang di server, bukan dari form.
 */
class RekapPpnController extends Controller
{
    private RekapPpn $model;
    private ActivityLog $activityLog;

    private const MAX_ROWS = 200;

    public function __construct()
    {
        Middleware::requirePermission('rekap_ppn', 'view');
        $this->model = new RekapPpn();
        $this->activityLog = new ActivityLog();
    }

    // =================== Halaman utama ===================

    public function index()
    {
        $f = $this->filters();
        $tab = ($_GET['tab'] ?? 'masukan') === 'keluaran' ? 'keluaran' : 'masukan';
        $this->view('rekap_ppn/index', [
            'pageTitle' => 'Rekap PPN',
            'f'         => $f,
            'tab'       => $tab,
            'years'     => $this->model->years(),
            'summary'   => $this->model->summary($f['tahun'], $f['masa']),
            'masukan'   => $tab === 'masukan' ? $this->model->masukanList($f) : [],
            'keluaran'  => $tab === 'keluaran' ? $this->model->keluaranList($f) : [],
        ]);
    }

    /** Simpan "Lebih Bayar masa sebelumnya" (kompensasi) + catatan untuk satu masa. */
    public function savePeriod()
    {
        Middleware::requirePermission('rekap_ppn', 'edit');
        $this->requirePost();
        $tahun = (int) ($_POST['tahun'] ?? 0);
        $masa = (int) ($_POST['masa'] ?? 0);
        $back = ['tahun' => $tahun, 'masa' => $masa, 'tab' => ($_POST['tab'] ?? '') === 'keluaran' ? 'keluaran' : 'masukan'];
        if ($tahun < 2000 || $tahun > 2100 || $masa < 1 || $masa > 12) {
            setFlash('error', 'Pilih satu masa pajak (bulan & tahun) dulu untuk mengisi kompensasi.');
            $this->redirect('rekap_ppn', 'index', $back);
        }
        $raw = is_string($_POST['lebih_bayar'] ?? null) ? trim($_POST['lebih_bayar']) : '';
        $amount = parseCurrencyInput($raw);
        if ($amount < 0 || $amount > RekapPpn::MAX_AMOUNT) {
            setFlash('error', 'Lebih bayar masa sebelumnya harus berupa angka 0 atau lebih.');
            $this->redirect('rekap_ppn', 'index', $back);
        }
        $notes = mb_substr(is_string($_POST['period_notes'] ?? null) ? trim($_POST['period_notes']) : '', 0, 300);
        $old = $this->model->getPeriod($tahun, $masa);
        $this->model->savePeriod($tahun, $masa, $amount, $notes !== '' ? $notes : null, currentUserId());
        $this->logAct('update_period', 'Masa ' . RekapPpn::monthName($masa) . " {$tahun}: lebih bayar masa sebelumnya "
            . formatRupiah($old['lebih_bayar_sebelumnya']) . ' -> ' . formatRupiah($amount));
        setFlash('success', 'Kompensasi masa ' . RekapPpn::monthName($masa) . " {$tahun} disimpan.");
        $this->redirect('rekap_ppn', 'index', $back);
    }

    // =================== PPN Masukan ===================

    public function createMasukan()
    {
        Middleware::requirePermission('rekap_ppn', 'create');
        $this->renderMasukanForm('create', null, null);
    }

    public function storeMasukan()
    {
        Middleware::requirePermission('rekap_ppn', 'create');
        $this->requirePost();
        [$data, $row, $errors] = $this->collectMasukan(0);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderMasukanForm('create', null, $data);
            return;
        }
        try {
            $id = $this->model->create($row + ['created_by' => (int) currentUserId()]);
        } catch (Throwable $e) {
            error_log('RekapPpn storeMasukan error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan faktur. Silakan coba lagi.');
            $this->renderMasukanForm('create', null, $data);
            return;
        }
        $this->logAct('create', "PPN Masukan #{$id}: faktur {$row['faktur_number']} {$row['vendor_name']}, DPP " . formatRupiah($row['dpp']) . ', PPN ' . formatRupiah($row['ppn_amount']));
        setFlash('success', "Faktur Masukan {$row['faktur_number']} berhasil disimpan.");
        $this->redirect('rekap_ppn', 'index', ['tab' => 'masukan', 'tahun' => $row['tahun'], 'masa' => $row['masa']]);
    }

    public function editMasukan()
    {
        Middleware::requirePermission('rekap_ppn', 'edit');
        $r = $this->loadMasukan((int) ($_GET['id'] ?? 0));
        $this->renderMasukanForm('edit', $r, null);
    }

    public function updateMasukan()
    {
        Middleware::requirePermission('rekap_ppn', 'edit');
        $this->requirePost();
        $r = $this->loadMasukan((int) ($_POST['id'] ?? 0));
        [$data, $row, $errors] = $this->collectMasukan((int) $r['id']);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderMasukanForm('edit', $r, $data);
            return;
        }
        $changes = [];
        foreach (['faktur_number' => 'no. faktur', 'faktur_date' => 'tanggal', 'npwp' => 'NPWP', 'vendor_name' => 'nama', 'masa' => 'masa', 'tahun' => 'tahun',
                  'project_name' => 'project', 'notes' => 'keterangan'] as $k => $label) {
            if ((string) ($r[$k] ?? '') !== (string) ($row[$k] ?? '')) {
                $changes[] = "{$label} " . ($r[$k] ?: '-') . ' -> ' . ($row[$k] ?: '-');
            }
        }
        if (round((float) $r['dpp'], 2) !== round((float) $row['dpp'], 2)) {
            $changes[] = 'DPP ' . formatRupiah($r['dpp']) . ' -> ' . formatRupiah($row['dpp']);
        }
        try {
            $this->model->updateById((int) $r['id'], $row);
        } catch (Throwable $e) {
            error_log('RekapPpn updateMasukan error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan perubahan. Silakan coba lagi.');
            $this->renderMasukanForm('edit', $r, $data);
            return;
        }
        $this->logAct('update', "PPN Masukan #{$r['id']} ({$r['faktur_number']}): " . ($changes ? implode('; ', $changes) : 'disimpan tanpa perubahan'));
        setFlash('success', "Faktur Masukan {$row['faktur_number']} berhasil diperbarui.");
        $this->redirect('rekap_ppn', 'index', ['tab' => 'masukan', 'tahun' => $row['tahun'], 'masa' => $row['masa']]);
    }

    public function deleteMasukan()
    {
        Middleware::requirePermission('rekap_ppn', 'delete');
        $this->requirePost();
        $r = $this->loadMasukan((int) ($_POST['id'] ?? 0));
        $this->model->softDeleteMasukan((int) $r['id'], currentUserId());
        $this->logAct('delete', "PPN Masukan #{$r['id']} ({$r['faktur_number']} {$r['vendor_name']}, PPN " . formatRupiah($r['ppn_amount']) . ') dihapus');
        setFlash('success', "Faktur Masukan {$r['faktur_number']} dihapus.");
        $this->redirect('rekap_ppn', 'index', ['tab' => 'masukan', 'tahun' => $r['tahun'], 'masa' => $r['masa']]);
    }

    // =================== PPN Keluaran ===================

    public function createKeluaran()
    {
        Middleware::requirePermission('rekap_ppn', 'create');
        $this->renderKeluaranForm('create', null, [], null);
    }

    public function storeKeluaran()
    {
        Middleware::requirePermission('rekap_ppn', 'create');
        $this->requirePost();
        [$data, $items, $totals, $errors] = $this->collectKeluaran(0);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderKeluaranForm('create', null, $items, $data);
            return;
        }
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $id = $this->model->createKeluaran($data + $totals + ['created_by' => (int) currentUserId()], $items);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RekapPpn storeKeluaran error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan faktur. Silakan coba lagi.');
            $this->renderKeluaranForm('create', null, $items, $data);
            return;
        }
        $this->logAct('create', "PPN Keluaran #{$id}: invoice " . ($data['invoice_number'] ?: '-') . ", {$data['customer_name']}, " . count($items) . ' baris, PPN ' . formatRupiah($totals['total_ppn']));
        setFlash('success', 'Faktur Keluaran ' . ($data['invoice_number'] ?: $data['customer_name']) . ' berhasil disimpan.');
        $this->redirect('rekap_ppn', 'index', ['tab' => 'keluaran', 'tahun' => (int) substr($data['entry_date'], 0, 4), 'masa' => (int) substr($data['entry_date'], 5, 2)]);
    }

    public function editKeluaran()
    {
        Middleware::requirePermission('rekap_ppn', 'edit');
        $k = $this->loadKeluaran((int) ($_GET['id'] ?? 0));
        $this->renderKeluaranForm('edit', $k, $this->model->keluaranItems((int) $k['id']), null);
    }

    public function updateKeluaran()
    {
        Middleware::requirePermission('rekap_ppn', 'edit');
        $this->requirePost();
        $k = $this->loadKeluaran((int) ($_POST['id'] ?? 0));
        $id = (int) $k['id'];
        [$data, $items, $totals, $errors] = $this->collectKeluaran($id);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->renderKeluaranForm('edit', $k, $items, $data);
            return;
        }
        $changes = [];
        foreach (['entry_date' => 'tanggal', 'invoice_number' => 'invoice', 'faktur_number' => 'no. faktur', 'customer_name' => 'customer', 'npwp' => 'NPWP', 'notes' => 'keterangan'] as $col => $label) {
            if ((string) ($k[$col] ?? '') !== (string) ($data[$col] ?? '')) {
                $changes[] = "{$label} " . ($k[$col] ?: '-') . ' -> ' . ($data[$col] ?: '-');
            }
        }
        if (round((float) $k['total_ppn'], 2) !== round($totals['total_ppn'], 2) || round((float) $k['total_amount'], 2) !== round($totals['total_amount'], 2)) {
            $changes[] = 'nilai: PPN ' . formatRupiah($k['total_ppn']) . ' -> ' . formatRupiah($totals['total_ppn']) . ', total ' . formatRupiah($k['total_amount']) . ' -> ' . formatRupiah($totals['total_amount']);
        }
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $this->model->updateKeluaran($id, $data + $totals, $items);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RekapPpn updateKeluaran error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan perubahan. Silakan coba lagi.');
            $this->renderKeluaranForm('edit', $k, $items, $data);
            return;
        }
        $this->logAct('update', "PPN Keluaran #{$id} (" . ($k['invoice_number'] ?: $k['customer_name']) . '): ' . ($changes ? implode('; ', $changes) : 'disimpan tanpa perubahan data header') . ' (' . count($items) . ' baris)');
        setFlash('success', 'Faktur Keluaran ' . ($data['invoice_number'] ?: $data['customer_name']) . ' berhasil diperbarui.');
        $this->redirect('rekap_ppn', 'index', ['tab' => 'keluaran', 'tahun' => (int) substr($data['entry_date'], 0, 4), 'masa' => (int) substr($data['entry_date'], 5, 2)]);
    }

    public function deleteKeluaran()
    {
        Middleware::requirePermission('rekap_ppn', 'delete');
        $this->requirePost();
        $k = $this->loadKeluaran((int) ($_POST['id'] ?? 0));
        $this->model->softDeleteKeluaran((int) $k['id'], currentUserId());
        $this->logAct('delete', "PPN Keluaran #{$k['id']} (" . ($k['invoice_number'] ?: '-') . " {$k['customer_name']}, PPN " . formatRupiah($k['total_ppn']) . ') dihapus');
        setFlash('success', 'Faktur Keluaran ' . ($k['invoice_number'] ?: $k['customer_name']) . ' dihapus.');
        $this->redirect('rekap_ppn', 'index', ['tab' => 'keluaran', 'tahun' => (int) substr($k['entry_date'], 0, 4), 'masa' => (int) substr($k['entry_date'], 5, 2)]);
    }

    // =================== Export Excel ===================

    public function exportExcel()
    {
        Middleware::requirePermission('rekap_ppn', 'export');
        $f = $this->filters();
        $f['q'] = ''; // export = seluruh periode terpilih, tanpa kata kunci pencarian
        $masukan = $this->model->masukanList($f);
        $keluaran = $this->model->keluaranList($f);
        if (!$masukan && !$keluaran) {
            setFlash('error', 'Tidak ada data PPN pada periode ini untuk diexport.');
            $this->redirect('rekap_ppn', 'index', array_filter(['tahun' => $f['tahun'], 'masa' => $f['masa']], fn($v) => $v !== null));
        }
        require_once ROOT_PATH . '/app/helpers/excel_rekap_ppn_helper.php';
        $label = $f['masa'] > 0 ? RekapPpn::monthName($f['masa']) . ' ' . $f['tahun'] : 'Tahun ' . $f['tahun'];
        $this->logAct('export', "Export Excel Rekap PPN {$label} (" . count($masukan) . ' faktur masukan, ' . count($keluaran) . ' faktur keluaran)');
        streamRekapPpn($masukan, $keluaran, $this->model->summary($f['tahun'], $f['masa']), $label, 'Rekap_PPN_' . $f['tahun'] . ($f['masa'] > 0 ? '_' . str_pad((string) $f['masa'], 2, '0', STR_PAD_LEFT) : ''));
    }

    // =================== Helper privat ===================

    /** Filter periode + pencarian. Tanpa parameter periode -> masa terbaru yang punya data. */
    private function filters(): array
    {
        $latest = $this->model->latestPeriod();
        $tahun = isset($_GET['tahun']) && is_scalar($_GET['tahun']) ? (int) $_GET['tahun'] : $latest['tahun'];
        if ($tahun < 2000 || $tahun > 2100) {
            $tahun = $latest['tahun'];
        }
        if (isset($_GET['masa']) && is_scalar($_GET['masa'])) {
            $masa = (int) $_GET['masa'];
            if ($masa < 0 || $masa > 12) {
                $masa = 0;
            }
        } else {
            $masa = isset($_GET['tahun']) ? 0 : $latest['masa'];
        }
        return ['tahun' => $tahun, 'masa' => $masa, 'q' => is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 100) : ''];
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('rekap_ppn', 'index');
        }
        verifyCsrf();
    }

    private function logAct(string $action, string $detail): void
    {
        $this->activityLog->log(currentUserId(), 'rekap_ppn', $action, $detail);
    }

    private function loadMasukan(int $id): array
    {
        $r = $id > 0 ? $this->model->findMasukan($id) : null;
        if (!$r) {
            setFlash('error', 'Faktur PPN Masukan tidak ditemukan.');
            $this->redirect('rekap_ppn', 'index', ['tab' => 'masukan']);
        }
        return $r;
    }

    private function loadKeluaran(int $id): array
    {
        $k = $id > 0 ? $this->model->findKeluaran($id) : null;
        if (!$k) {
            setFlash('error', 'Faktur PPN Keluaran tidak ditemukan.');
            $this->redirect('rekap_ppn', 'index', ['tab' => 'keluaran']);
        }
        return $k;
    }

    private function str(string $key, int $max): string
    {
        return mb_substr(is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '', 0, $max);
    }

    /** NPWP/faktur: hanya angka, titik, strip, spasi (Excel menyimpan sebagai teks 15-16 digit). */
    private function validTaxId(string $v): bool
    {
        return $v === '' || preg_match('/^[0-9.\-\s]{1,40}$/', $v) === 1;
    }

    // ---- Masukan ----

    /** @return array [data mentah utk form, baris siap simpan, errors] */
    private function collectMasukan(int $exceptId): array
    {
        $errors = [];
        $data = [
            'faktur_number' => $this->str('faktur_number', 40), 'faktur_date' => $this->str('faktur_date', 10),
            'npwp' => $this->str('npwp', 30), 'vendor_name' => $this->str('vendor_name', 150),
            'masa' => $this->str('masa', 2), 'tahun' => $this->str('tahun', 4), 'dpp' => $this->str('dpp', 30),
            'project_name' => $this->str('project_name', 150), 'notes' => $this->str('notes', 500),
        ];
        if ($data['faktur_number'] === '' || !$this->validTaxId($data['faktur_number'])) {
            $errors[] = 'No. Faktur wajib diisi (hanya angka, titik, atau strip).';
        } elseif ($this->model->masukanDuplicate($data['faktur_number'], $exceptId)) {
            $errors[] = "No. Faktur {$data['faktur_number']} sudah ada di PPN Masukan.";
        }
        if (!isValidDateString($data['faktur_date'])) {
            $errors[] = 'Tanggal faktur tidak valid.';
        }
        if ($data['vendor_name'] === '') {
            $errors[] = 'Nama vendor wajib diisi.';
        }
        if (!$this->validTaxId($data['npwp'])) {
            $errors[] = 'NPWP hanya boleh berisi angka, titik, atau strip.';
        }
        $masa = ctype_digit($data['masa']) ? (int) $data['masa'] : 0;
        $tahun = ctype_digit($data['tahun']) ? (int) $data['tahun'] : 0;
        if ($masa < 1 || $masa > 12) {
            $errors[] = 'Masa pajak harus 1-12.';
        }
        if ($tahun < 2000 || $tahun > 2100) {
            $errors[] = 'Tahun pajak tidak valid.';
        }
        $dpp = parseCurrencyInput($data['dpp']);
        if ($dpp <= 0 || $dpp > RekapPpn::MAX_AMOUNT) {
            $errors[] = 'DPP harus lebih dari 0.';
        }
        $calc = RekapPpn::computeMasukan($dpp);
        $row = [
            'faktur_number' => $data['faktur_number'], 'faktur_date' => $data['faktur_date'], 'npwp' => $data['npwp'] ?: null,
            'vendor_name' => $data['vendor_name'], 'masa' => $masa, 'tahun' => $tahun,
            'project_name' => $data['project_name'] ?: null, 'notes' => $data['notes'] ?: null,
        ] + $calc;
        return [$data, $row, $errors];
    }

    private function renderMasukanForm(string $mode, ?array $r, ?array $posted): void
    {
        $latest = $this->model->latestPeriod();
        $this->view('rekap_ppn/form_masukan', [
            'pageTitle' => $mode === 'edit' ? 'Edit PPN Masukan' : 'Tambah PPN Masukan',
            'mode'      => $mode,
            'r'         => $r,
            'posted'    => $posted,
            'defMasa'   => $latest['masa'],
            'defTahun'  => $latest['tahun'],
            'vendors'   => $this->model->vendorNames(),
            'npwpMap'   => $this->model->npwpByVendor(),
            'projects'  => $this->model->projectNames(),
        ]);
    }

    // ---- Keluaran ----

    /** @return array [header, baris (dihitung server), total header, errors] */
    private function collectKeluaran(int $exceptId): array
    {
        $errors = [];
        $data = [
            'entry_date' => $this->str('entry_date', 10), 'invoice_number' => $this->str('invoice_number', 60),
            'faktur_number' => $this->str('faktur_number', 40), 'customer_name' => $this->str('customer_name', 150),
            'npwp' => $this->str('npwp', 30), 'notes' => $this->str('notes', 500),
        ];
        if (!isValidDateString($data['entry_date'])) {
            $errors[] = 'Tanggal tidak valid.';
        }
        if ($data['customer_name'] === '') {
            $errors[] = 'Nama customer wajib diisi.';
        }
        if (!$this->validTaxId($data['faktur_number'])) {
            $errors[] = 'No. Faktur Pajak hanya boleh berisi angka, titik, atau strip.';
        } elseif ($this->model->keluaranDuplicate($data['faktur_number'], $exceptId)) {
            $errors[] = "No. Faktur Pajak {$data['faktur_number']} sudah ada di PPN Keluaran.";
        }
        if (!$this->validTaxId($data['npwp'])) {
            $errors[] = 'NPWP hanya boleh berisi angka, titik, atau strip.';
        }

        $col = fn($k) => (isset($_POST[$k]) && is_array($_POST[$k])) ? array_values($_POST[$k]) : [];
        $names = $col('item_name');
        $qtys = $col('qty');
        $prices = $col('unit_price');
        $lists = $col('pricelist');
        $n = max(count($names), count($qtys), count($prices), count($lists));
        if ($n > self::MAX_ROWS) {
            $errors[] = 'Jumlah baris melebihi batas ' . self::MAX_ROWS . '.';
            $n = self::MAX_ROWS;
        }
        $items = [];
        for ($i = 0; $i < $n; $i++) {
            $g = fn($arr) => is_string($arr[$i] ?? null) ? trim($arr[$i]) : '';
            $name = mb_substr($g($names), 0, 255);
            $qtyRaw = $g($qtys);
            $priceRaw = $g($prices);
            $listRaw = $g($lists);
            if ($name === '' && $qtyRaw === '' && $priceRaw === '' && $listRaw === '') {
                continue;
            }
            $line = count($items) + 1;
            $qty = parseCurrencyInput($qtyRaw);
            $price = parseCurrencyInput($priceRaw);
            $list = $listRaw === '' ? null : parseCurrencyInput($listRaw);
            if ($name === '') {
                $errors[] = "Baris {$line}: Nama barang wajib diisi.";
            }
            if ($qty <= 0 || $qty > RekapPpn::MAX_AMOUNT) {
                $errors[] = "Baris {$line}: Qty harus lebih dari 0.";
            }
            if ($price <= 0 || $price > RekapPpn::MAX_AMOUNT) {
                $errors[] = "Baris {$line}: Harga satuan harus lebih dari 0.";
            } elseif ($qty * $price > RekapPpn::MAX_AMOUNT) {
                $errors[] = "Baris {$line}: Subtotal terlalu besar.";
            }
            if ($list !== null && ($list < 0 || $list > RekapPpn::MAX_AMOUNT)) {
                $errors[] = "Baris {$line}: Pricelist tidak valid.";
            }
            $items[] = ['item_name' => $name, 'qty' => $qty, 'pricelist' => $list, 'unit_price' => $price, 'qty_raw' => $qtyRaw, 'price_raw' => $priceRaw, 'list_raw' => $listRaw]
                + RekapPpn::computeKeluaranRow($qty, $price);
        }
        if (!$items) {
            $errors[] = 'Minimal harus ada 1 baris barang.';
        }
        $totals = RekapPpn::sumKeluaran($items);
        $data['invoice_number'] = $data['invoice_number'] ?: null;
        $data['faktur_number'] = $data['faktur_number'] ?: null;
        $data['npwp'] = $data['npwp'] ?: null;
        $data['notes'] = $data['notes'] ?: null;
        return [$data, $items, $totals, $errors];
    }

    private function renderKeluaranForm(string $mode, ?array $k, array $items, ?array $posted): void
    {
        $this->view('rekap_ppn/form_keluaran', [
            'pageTitle' => $mode === 'edit' ? 'Edit PPN Keluaran' : 'Tambah PPN Keluaran',
            'mode'      => $mode,
            'k'         => $k,
            'items'     => $items,
            'posted'    => $posted,
            'customers' => $this->model->customerNames(),
            'npwpMap'   => $this->model->npwpByCustomer(),
        ]);
    }
}
