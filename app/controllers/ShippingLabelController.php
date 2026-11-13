<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/ShippingLabel.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * ShippingLabelController -- Label Pengiriman.
 * Form alamat "Kepada" + "Pengirim" (nama, alamat, kota, kode pos, no. telp)
 * yang dicetak sebagai dua kotak bertumpuk. Berdiri sendiri: tanpa nomor
 * dokumen, tanpa efek ke stok/kas/PO.
 */
class ShippingLabelController extends Controller
{
    private ShippingLabel $labelModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('shipping_label', 'view');

        $this->labelModel  = new ShippingLabel();
        $this->activityLog = new ActivityLog();
    }

    /** Batas panjang tiap kolom -- sama dengan DDL (migrasi 2026_11_14_a). */
    private const MAX_LEN = ['name' => 150, 'address' => 255, 'city' => 150, 'postal_code' => 20, 'phone' => 50];

    private function listFilters(): array
    {
        $from = $_GET['date_from'] ?? '';
        $to   = $_GET['date_to'] ?? '';
        return [
            'keyword'   => trim((string) ($_GET['keyword'] ?? '')),
            'date_from' => isValidDateString($from) ? $from : '',
            'date_to'   => isValidDateString($to) ? $to : '',
        ];
    }

    public function index()
    {
        $filters = $this->listFilters();
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->labelModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $rows = $this->labelModel->listPaginated($filters, $pg['perPage'], $pg['offset']);

        $this->view('shipping_label/list', [
            'pageTitle'  => 'Label Pengiriman',
            'rows'       => $rows,
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter($filters)),
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('shipping_label', 'create');

        $this->view('shipping_label/form', [
            'pageTitle' => 'Tambah Label Pengiriman',
            'mode'      => 'create',
            'label'     => [],
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('shipping_label', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('shipping_label', 'index');
        }
        verifyCsrf();

        [$data, $error] = $this->readInput();
        if ($error !== null) {
            setFlash('error', $error);
            $this->redirect('shipping_label', 'create');
        }

        $data['created_by'] = currentUserId();

        try {
            $id = $this->labelModel->create($data);
            $this->activityLog->log(currentUserId(), 'shipping_label', 'create',
                "Label Pengiriman untuk {$data['to_name']} dibuat");
            setFlash('success', 'Label Pengiriman berhasil disimpan.');
            $this->redirect('shipping_label', 'print', ['id' => $id]);
        } catch (PDOException $e) {
            error_log('ShippingLabel store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Label Pengiriman. Silakan coba lagi.');
            $this->redirect('shipping_label', 'create');
        }
    }

    public function edit()
    {
        Middleware::requirePermission('shipping_label', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $label = $this->labelModel->find($id);
        if (!$label) {
            setFlash('error', 'Label Pengiriman tidak ditemukan.');
            $this->redirect('shipping_label', 'index');
        }

        $this->view('shipping_label/form', [
            'pageTitle' => 'Edit Label Pengiriman',
            'mode'      => 'edit',
            'label'     => $label,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('shipping_label', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('shipping_label', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->labelModel->find($id);
        if (!$existing) {
            setFlash('error', 'Label Pengiriman tidak ditemukan.');
            $this->redirect('shipping_label', 'index');
        }

        [$data, $error] = $this->readInput();
        if ($error !== null) {
            setFlash('error', $error);
            $this->redirect('shipping_label', 'edit', ['id' => $id]);
        }

        try {
            $this->labelModel->updateById($id, $data);
            $this->activityLog->log(currentUserId(), 'shipping_label', 'update',
                "Label Pengiriman untuk {$data['to_name']} diperbarui");
            setFlash('success', 'Label Pengiriman berhasil diperbarui.');
            $this->redirect('shipping_label', 'print', ['id' => $id]);
        } catch (PDOException $e) {
            error_log('ShippingLabel update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Label Pengiriman.');
            $this->redirect('shipping_label', 'edit', ['id' => $id]);
        }
    }

    /** Cetak 1 label. */
    public function print()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $label = $this->labelModel->find($id);
        if (!$label) {
            setFlash('error', 'Label Pengiriman tidak ditemukan.');
            $this->redirect('shipping_label', 'index');
        }

        $this->view('shipping_label/print', [
            'pageTitle' => 'Cetak Label Pengiriman',
            'labels'    => [$label],
        ]);
    }

    /**
     * Cetak Terpilih / Cetak Semua. Tanpa ?ids= -> "Cetak Semua" mengikuti
     * filter pencarian/tanggal yang aktif, bukan seluruh tabel.
     */
    public function printMany()
    {
        $ids = $this->parseIds($_GET['ids'] ?? []);

        $labels = [];
        if (!empty($ids)) {
            foreach ($ids as $id) {
                $label = $this->labelModel->find($id);
                if ($label) {
                    $labels[] = $label;
                }
            }
        } else {
            $labels = $this->labelModel->listAll($this->listFilters());
        }

        if (empty($labels)) {
            setFlash('error', 'Label Pengiriman yang dipilih tidak ditemukan.');
            $this->redirect('shipping_label', 'index');
        }

        $this->view('shipping_label/print', [
            'pageTitle' => 'Cetak Label Pengiriman',
            'labels'    => $labels,
        ]);
    }

    public function delete()
    {
        Middleware::requirePermission('shipping_label', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('shipping_label', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $label = $this->labelModel->find($id);
        if (!$label) {
            setFlash('error', 'Label Pengiriman tidak ditemukan.');
            $this->redirect('shipping_label', 'index');
        }

        try {
            $this->labelModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'shipping_label', 'delete',
                "Label Pengiriman untuk {$label['to_name']} dihapus");
            setFlash('success', 'Label Pengiriman berhasil dihapus.');
        } catch (Throwable $e) {
            error_log('ShippingLabel delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus Label Pengiriman.');
        }
        $this->redirect('shipping_label', 'index');
    }

    /**
     * Baca & validasi isian form. Nama + alamat WAJIB untuk Kepada dan Pengirim;
     * kota, kode pos, no. telp opsional (label tetap tercetak tanpa baris kosong).
     * @return array{0:array,1:?string} [data siap simpan, pesan error|null]
     */
    private function readInput(): array
    {
        $data = [];
        $titles = ['to' => 'Kepada', 'from' => 'Pengirim'];
        $fieldNames = ['name' => 'Nama', 'address' => 'Alamat', 'city' => 'Kota', 'postal_code' => 'Kode Pos', 'phone' => 'No. Telp'];

        foreach (ShippingLabel::PARTIES as $party) {
            foreach (ShippingLabel::FIELDS as $field) {
                $key = "{$party}_{$field}";
                $raw = $_POST[$key] ?? '';
                $value = is_string($raw) ? trim(preg_replace('/\s*[\r\n]+\s*/', ' ', $raw)) : '';

                if (mb_strlen($value) > self::MAX_LEN[$field]) {
                    return [[], "{$fieldNames[$field]} ({$titles[$party]}) terlalu panjang (maks " . self::MAX_LEN[$field] . ' karakter).'];
                }
                if ($value === '' && in_array($field, ['name', 'address'], true)) {
                    return [[], "{$fieldNames[$field]} ({$titles[$party]}) wajib diisi."];
                }
                $data[$key] = $value === '' ? null : $value;
            }
        }

        return [$data, null];
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
