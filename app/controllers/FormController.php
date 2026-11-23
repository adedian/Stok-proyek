<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/FormDocument.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * FormController -- menu "Form".
 * Satu menu, beberapa jenis form yang dipilih lewat dropdown saat Tambah:
 *   pengiriman    : alamat Kepada + Pengirim (dua kotak)
 *   kwitansi      : sudah terima dari, jumlah uang (+terbilang), untuk pembayaran
 *   order_form    : "Form Order Barang" -- data pemesan, pembayaran, daftar barang, pajak, total
 *   bon_sementara : pengajuan bon, rincian estimasi pengeluaran, kolom tanda tangan
 *   tanda_terima  : Kepada + Alamat + daftar dokumen (jenis, jumlah, keterangan)
 * Isian disimpan sebagai JSON (forms.data); jenis tidak bisa diganti setelah dibuat.
 * Kwitansi / Bon Sementara / Tanda Terima Dokumen / Form Order Barang dapat nomor otomatis saat
 * pertama disimpan (forms.form_number, lihat FormDocument::createNumbered) dan nomornya tetap selamanya.
 * Modul mandiri -- tanpa efek ke stok/kas/PO/invoice.
 */
class FormController extends Controller
{
    private FormDocument $formModel;
    private ActivityLog $activityLog;

    /** Batas jumlah baris rincian (Form Order Barang / Bon Sementara) & nominal maksimum. */
    private const MAX_ROWS = 30;
    private const MAX_AMOUNT = 999999999999.99;

    public function __construct()
    {
        Middleware::requirePermission('form', 'view');

        $this->formModel   = new FormDocument();
        $this->activityLog = new ActivityLog();
    }

    private function listFilters(): array
    {
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        $type = $_GET['type'] ?? '';
        return [
            'type'      => FormDocument::isValidType($type) ? $type : '',
            'keyword'   => trim((string) ($_GET['keyword'] ?? '')),
            'date_from' => isValidDateString($from) ? $from : '',
            'date_to'   => isValidDateString($to) ? $to : '',
        ];
    }

    public function index()
    {
        $filters = $this->listFilters();

        $totalRows = $this->formModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, (int) ($_GET['page'] ?? 1));

        $this->view('form/list', [
            'pageTitle'  => 'Form',
            'rows'       => $this->formModel->listPaginated($filters, $pg['perPage'], $pg['offset']),
            'filters'    => $filters,
            'types'      => FormDocument::types(),
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter($filters)),
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('form', 'create');

        $type = $_GET['type'] ?? '';
        $this->view('form/form', [
            'pageTitle' => 'Tambah Form',
            'mode'      => 'create',
            'type'      => FormDocument::isValidType($type) ? $type : 'pengiriman',
            'types'     => FormDocument::types(),
            'd'         => [],
            'numberPreviews' => $this->numberPreviews(),
        ]);
    }

    /** Pratinjau nomor berikutnya per jenis bernomor (hanya tampilan; counter tidak naik). */
    private function numberPreviews(): array
    {
        $out = [];
        foreach (array_keys(FormDocument::types()) as $slug) {
            $n = $this->formModel->previewNumber($slug);
            if ($n !== null) {
                $out[$slug] = $n;
            }
        }
        return $out;
    }

    public function store()
    {
        Middleware::requirePermission('form', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('form', 'index');
        }
        verifyCsrf();

        $type = $_POST['form_type'] ?? '';
        if (!FormDocument::isValidType($type)) {
            setFlash('error', 'Jenis form tidak valid.');
            $this->redirect('form', 'create');
        }

        try {
            [$data, $summary] = $this->readInput($type);
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('form', 'create', ['type' => $type]);
        }
        BackDate::enforce('form', $this->docDate($data), null, 'form', 'create', ['type' => $type]);

        try {
            $id = $this->formModel->createNumbered([
                'form_type'  => $type,
                'summary'    => $summary,
                'data'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                'created_by' => currentUserId(),
            ], $this->docDate($data));
            $number = (string) ($this->formModel->find($id)['form_number'] ?? '');
            $this->activityLog->log(currentUserId(), 'form', 'create',
                FormDocument::typeLabel($type) . ($number !== '' ? " {$number}" : '') . " dibuat: {$summary}");
            setFlash('success', FormDocument::typeLabel($type) . ' berhasil disimpan.');
            $this->redirect('form', 'print', ['id' => $id]);
        } catch (PDOException $e) {
            error_log('Form store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan form. Silakan coba lagi.');
            $this->redirect('form', 'create', ['type' => $type]);
        }
    }

    public function edit()
    {
        Middleware::requirePermission('form', 'edit');

        $row = FormDocument::hydrate($this->formModel->find((int) ($_GET['id'] ?? 0)));
        if (!$row) {
            setFlash('error', 'Form tidak ditemukan.');
            $this->redirect('form', 'index');
        }

        $this->view('form/form', [
            'pageTitle' => 'Edit Form',
            'mode'      => 'edit',
            'type'      => $row['form_type'],
            'types'     => FormDocument::types(),
            'row'       => $row,
            'd'         => $row['d'],
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('form', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('form', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->formModel->find($id);
        if (!$existing) {
            setFlash('error', 'Form tidak ditemukan.');
            $this->redirect('form', 'index');
        }
        // Jenis form TIDAK bisa diganti (isiannya beda skema) -- selalu pakai jenis tersimpan.
        $type = $existing['form_type'];

        try {
            [$data, $summary] = $this->readInput($type);
        } catch (InvalidArgumentException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('form', 'edit', ['id' => $id]);
        }
        $oldData = json_decode((string) ($existing['data'] ?? ''), true);
        // Form Order Barang lama (sebelum bernomor otomatis) punya "Order Number" ketikan sendiri -- jangan hilang saat diedit.
        if ($type === 'order_form' && empty($existing['form_number']) && is_array($oldData) && !empty($oldData['order_number'])) {
            $data['order_number'] = $oldData['order_number'];
        }
        BackDate::enforce('form', $this->docDate($data), is_array($oldData) ? $this->docDate($oldData) : null, 'form', 'edit', ['id' => $id]);

        try {
            $this->formModel->updateById($id, [
                'summary' => $summary,
                'data'    => json_encode($data, JSON_UNESCAPED_UNICODE),
            ]);
            $this->activityLog->log(currentUserId(), 'form', 'update',
                FormDocument::typeLabel($type) . (!empty($existing['form_number']) ? " {$existing['form_number']}" : '') . " diperbarui: {$summary}");
            setFlash('success', FormDocument::typeLabel($type) . ' berhasil diperbarui.');
            $this->redirect('form', 'print', ['id' => $id]);
        } catch (PDOException $e) {
            error_log('Form update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui form.');
            $this->redirect('form', 'edit', ['id' => $id]);
        }
    }

    public function print()
    {
        $row = FormDocument::hydrate($this->formModel->find((int) ($_GET['id'] ?? 0)));
        if (!$row) {
            setFlash('error', 'Form tidak ditemukan.');
            $this->redirect('form', 'index');
        }

        $this->renderPrint([$row]);
    }

    /** Cetak Terpilih / Cetak Semua (tanpa ?ids= -> ikut filter aktif, bukan seluruh tabel). */
    public function printMany()
    {
        $ids = $this->parseIds($_GET['ids'] ?? []);

        $rows = [];
        if (!empty($ids)) {
            foreach ($ids as $id) {
                if ($row = $this->formModel->find($id)) {
                    $rows[] = $row;
                }
            }
        } else {
            $rows = $this->formModel->listAll($this->listFilters());
        }

        if (empty($rows)) {
            setFlash('error', 'Form yang dipilih tidak ditemukan.');
            $this->redirect('form', 'index');
        }

        $this->renderPrint(array_map([FormDocument::class, 'hydrate'], $rows));
    }

    private function renderPrint(array $forms): void
    {
        $this->view('form/print', [
            'pageTitle' => 'Cetak Form',
            'forms'     => $forms,
            'company'   => (new SystemSetting())->getGroup('company'),
        ]);
    }

    public function delete()
    {
        Middleware::requirePermission('form', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('form', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $row = $this->formModel->find($id);
        if (!$row) {
            setFlash('error', 'Form tidak ditemukan.');
            $this->redirect('form', 'index');
        }

        try {
            $this->formModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'form', 'delete',
                FormDocument::typeLabel($row['form_type']) . " dihapus: {$row['summary']}");
            setFlash('success', 'Form berhasil dihapus.');
        } catch (Throwable $e) {
            error_log('Form delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus form.');
        }
        $this->redirect('form', 'index');
    }

    // =====================================================================
    // Baca & validasi isian per jenis. Return [data, summary]; gagal -> InvalidArgumentException.
    // =====================================================================

    /** Tanggal dokumen sebuah Form (kolom tanggalnya beda per jenis): form_date / order_date / request_date. */
    private function docDate(array $data): ?string
    {
        foreach (['form_date', 'order_date', 'request_date'] as $k) {
            if (!empty($data[$k]) && is_string($data[$k])) {
                return $data[$k];
            }
        }
        return null;
    }

    private function readInput(string $type): array
    {
        switch ($type) {
            case 'kwitansi':      return $this->readKwitansi();
            case 'order_form':    return $this->readOrderForm();
            case 'bon_sementara': return $this->readBonSementara();
            case 'tanda_terima':  return $this->readTandaTerima();
            default:              return $this->readPengiriman();
        }
    }

    private function readPengiriman(): array
    {
        $data = [];
        foreach (['to' => 'Kepada', 'from' => 'Pengirim'] as $p => $title) {
            $data["{$p}_name"]        = $this->text("{$p}_name", 150, "Nama ($title)", true);
            $data["{$p}_company"]     = $this->text("{$p}_company", 150, "Nama Perusahaan ($title)");
            $data["{$p}_address"]     = $this->text("{$p}_address", 255, "Alamat ($title)", true);
            $data["{$p}_city"]        = $this->text("{$p}_city", 150, "Kota ($title)");
            $data["{$p}_postal_code"] = $this->text("{$p}_postal_code", 20, "Kode Pos ($title)");
            $data["{$p}_phone"]       = $this->text("{$p}_phone", 50, "No. Telp ($title)");
        }
        return [$data, mb_substr('Kepada ' . $data['to_name'] . ' - dari ' . $data['from_name'], 0, 255)];
    }

    private function readKwitansi(): array
    {
        $amount = parseCurrencyInput($_POST['amount'] ?? '');
        if ($amount <= 0 || $amount > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Jumlah uang harus lebih dari 0 dan tidak melebihi batas.');
        }

        $data = [
            'received_from' => $this->text('received_from', 200, 'Sudah terima dari', true),
            'amount'        => $amount,
            'purpose'       => $this->text('purpose', 500, 'Untuk pembayaran', true, true),
            'city'          => $this->text('city', 100, 'Kota'),
            'form_date'     => $this->date('form_date', 'Tanggal'),
            'signer_name'   => $this->text('signer_name', 150, 'Nama penandatangan'),
        ];
        return [$data, mb_substr($data['received_from'] . ' - ' . formatRupiah($amount), 0, 255)];
    }

    private function readOrderForm(): array
    {
        $qty = (array) ($_POST['item_qty'] ?? []);
        $desc = (array) ($_POST['item_description'] ?? []);
        $price = (array) ($_POST['item_price'] ?? []);

        $items = [];
        $rowCount = max(count($qty), count($desc), count($price));
        for ($i = 0; $i < $rowCount; $i++) {
            $d = $this->flat($desc[$i] ?? '');
            $q = parseQtyInput(is_scalar($qty[$i] ?? '') ? $qty[$i] : '');
            $p = parseCurrencyInput(is_scalar($price[$i] ?? '') ? $price[$i] : '');
            if ($d === '' && $q == 0.0 && $p == 0.0) {
                continue; // baris kosong diabaikan
            }
            if ($d === '') {
                throw new InvalidArgumentException('Deskripsi barang wajib diisi pada setiap baris yang terisi.');
            }
            if (mb_strlen($d) > 255) {
                throw new InvalidArgumentException('Deskripsi barang terlalu panjang (maks 255 karakter).');
            }
            if ($q < 0 || $p < 0 || $q > 999999999 || $p > self::MAX_AMOUNT || round($q * $p, 2) > self::MAX_AMOUNT) {
                throw new InvalidArgumentException('Qty atau harga di luar batas yang diizinkan.');
            }
            $items[] = ['qty' => $q, 'description' => $d, 'price' => $p];
        }
        if (empty($items)) {
            throw new InvalidArgumentException('Minimal 1 barang harus diisi.');
        }
        if (count($items) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal ' . self::MAX_ROWS . ' baris barang.');
        }

        $taxPercent = parseQtyInput($_POST['tax_percent'] ?? '');
        if ($taxPercent < 0 || $taxPercent > 100) {
            throw new InvalidArgumentException('Pajak (%) harus antara 0 sampai 100.');
        }

        $payment = $_POST['payment'] ?? '';
        $data = [
            'order_date'   => $this->date('order_date', 'Date'),
            'name'         => $this->text('name', 150, 'Name', true),
            'phone'        => $this->text('phone', 50, 'Phone'),
            'email'        => $this->text('email', 150, 'Email'),
            'address'      => $this->text('address', 500, 'Address', false, true),
            'payment'      => in_array($payment, ['cash', 'transfer'], true) ? $payment : '',
            'notes'        => $this->text('notes', 500, 'Notes', false, true),
            'items'        => $items,
            'tax_percent'  => $taxPercent,
        ];
        return [$data, mb_substr($data['name'], 0, 255)];
    }

    private function readBonSementara(): array
    {
        $desc = (array) ($_POST['item_description'] ?? []);
        $amt = (array) ($_POST['item_amount'] ?? []);

        $items = [];
        $total = 0.0;
        for ($i = 0, $n = max(count($desc), count($amt)); $i < $n; $i++) {
            $d = is_string($desc[$i] ?? '') ? trim(str_replace("\r", '', $desc[$i] ?? '')) : '';
            $a = parseCurrencyInput(is_scalar($amt[$i] ?? '') ? $amt[$i] : '');
            if ($d === '' && $a == 0.0) {
                continue;
            }
            if ($d === '') {
                throw new InvalidArgumentException('Rincian pengeluaran wajib diisi pada setiap baris yang terisi.');
            }
            if (mb_strlen($d) > 500) {
                throw new InvalidArgumentException('Rincian pengeluaran terlalu panjang (maks 500 karakter).');
            }
            if ($a < 0 || $a > self::MAX_AMOUNT) {
                throw new InvalidArgumentException('Nominal rincian di luar batas yang diizinkan.');
            }
            $items[] = ['description' => $d, 'amount' => $a];
            $total += $a;
        }
        if (empty($items)) {
            throw new InvalidArgumentException('Minimal 1 baris rincian pengeluaran harus diisi.');
        }
        if (count($items) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal ' . self::MAX_ROWS . ' baris rincian.');
        }
        if ($total > self::MAX_AMOUNT) {
            throw new InvalidArgumentException('Total pengeluaran melebihi batas yang diizinkan.');
        }

        $data = [
            'name'         => $this->text('name', 150, 'Nama', true),
            'position'     => $this->text('position', 150, 'Jabatan'),
            'purpose'      => $this->text('purpose', 255, 'Keperluan', true),
            'request_date' => $this->date('request_date', 'Tgl. Pengajuan'),
            'city'         => $this->text('city', 100, 'Kota'),
            'approved_by'  => $this->text('approved_by', 150, 'Nama Disetujui'),
            'known_by'     => $this->text('known_by', 150, 'Nama Mengetahui'),
            'made_by'      => $this->text('made_by', 150, 'Nama Dibuat'),
            'items'        => $items,
        ];
        return [$data, mb_substr($data['name'] . ' - ' . formatRupiah($total), 0, 255)];
    }

    private function readTandaTerima(): array
    {
        $doc = (array) ($_POST['td_item'] ?? []);
        $qty = (array) ($_POST['td_qty'] ?? []);
        $note = (array) ($_POST['td_note'] ?? []);

        $items = [];
        for ($i = 0, $n = max(count($doc), count($qty), count($note)); $i < $n; $i++) {
            $row = [
                'document' => $this->flat($doc[$i] ?? ''),
                'qty'      => $this->flat($qty[$i] ?? ''),
                'note'     => $this->flat($note[$i] ?? ''),
            ];
            if ($row['document'] === '' && $row['qty'] === '' && $row['note'] === '') {
                continue; // baris kosong diabaikan
            }
            if ($row['document'] === '') {
                throw new InvalidArgumentException('Jenis Dokumen wajib diisi pada setiap baris yang terisi.');
            }
            if (mb_strlen($row['document']) > 255 || mb_strlen($row['qty']) > 50 || mb_strlen($row['note']) > 255) {
                throw new InvalidArgumentException('Isian dokumen terlalu panjang (Jenis maks 255, Jumlah maks 50, Keterangan maks 255 karakter).');
            }
            $items[] = $row;
        }
        if (empty($items)) {
            throw new InvalidArgumentException('Minimal 1 dokumen harus diisi.');
        }
        if (count($items) > self::MAX_ROWS) {
            throw new InvalidArgumentException('Maksimal ' . self::MAX_ROWS . ' baris dokumen.');
        }

        $data = [
            'to_name' => $this->text('to_name', 200, 'Kepada', true),
            'address' => $this->text('address', 500, 'Alamat', false, true),
            'items'   => $items,
        ];
        return [$data, mb_substr($data['to_name'] . ' (' . count($items) . ' dokumen)', 0, 255)];
    }

    // ---- util baca input ----

    /** Teks satu baris (atau multi-baris bila $multiline). Kosong -> null kecuali $required. */
    private function text(string $key, int $max, string $label, bool $required = false, bool $multiline = false): ?string
    {
        $raw = $_POST[$key] ?? '';
        $value = is_string($raw) ? str_replace("\r", '', trim($raw)) : '';
        if (!$multiline) {
            $value = $this->flat($value);
        }
        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException("{$label} wajib diisi.");
            }
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException("{$label} terlalu panjang (maks {$max} karakter).");
        }
        return $value;
    }

    private function flat($value): string
    {
        return is_string($value) ? trim(preg_replace('/\s*[\r\n]+\s*/', ' ', $value)) : '';
    }

    /** Tanggal Y-m-d; kosong -> hari ini; format salah -> error. */
    private function date(string $key, string $label): string
    {
        $v = trim((string) ($_POST[$key] ?? ''));
        if ($v === '') {
            return date('Y-m-d');
        }
        if (!isValidDateString($v)) {
            throw new InvalidArgumentException("{$label} tidak valid.");
        }
        return $v;
    }

    private function parseIds($idsParam): array
    {
        if (is_string($idsParam)) {
            $idsParam = explode(',', $idsParam);
        }
        if (!is_array($idsParam)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $idsParam), fn($id) => $id > 0)));
    }
}
