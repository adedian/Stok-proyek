<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/SalesInvoice.php';
require_once ROOT_PATH . '/app/models/SalesInvoiceItem.php';
require_once ROOT_PATH . '/app/models/Client.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Signature.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/DpPercentage.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/CompanyBankAccount.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/User.php';

/**
 * SalesInvoiceController -- Invoice Keluar (AR, HME menagih ke client).
 * Terpisah dari InvoiceController (AP, invoice masuk dari supplier) -- lihat
 * migration 2026_08_24_sales_invoice_delivery_receipt.sql untuk konteksnya.
 */
class SalesInvoiceController extends Controller
{
    private SalesInvoice $invoiceModel;
    private SalesInvoiceItem $itemModel;
    private Client $clientModel;
    private Project $projectModel;
    private Signature $signatureModel;
    private Unit $unitModel;
    private DpPercentage $dpPercentageModel;
    private ActivityLog $activityLog;
    private Item $itemCatalogModel;
    private ItemCategory $itemCategoryModel;
    private User $userModel;

    public function __construct()
    {
        Middleware::requirePermission('sales_invoice', 'view');

        $this->invoiceModel      = new SalesInvoice();
        $this->itemModel         = new SalesInvoiceItem();
        $this->clientModel       = new Client();
        $this->projectModel      = new Project();
        $this->signatureModel    = new Signature();
        $this->unitModel         = new Unit();
        $this->dpPercentageModel = new DpPercentage();
        $this->activityLog       = new ActivityLog();
        $this->itemCatalogModel  = new Item();
        $this->itemCategoryModel = new ItemCategory();
        $this->userModel         = new User();
    }

    public function index()
    {
        $filters = [
            'keyword'        => trim($_GET['keyword'] ?? ''),
            'client_id'      => $_GET['client_id'] ?? '',
            'invoice_type'   => $_GET['invoice_type'] ?? '',
            'date_from'      => $_GET['date_from'] ?? '',
            'date_to'        => $_GET['date_to'] ?? '',
            'billing_status' => $_GET['billing_status'] ?? '',
        ];

        $invoices = $this->invoiceModel->listWithRelations($filters);

        $this->view('sales_invoice/list', [
            'pageTitle' => 'Invoice Keluar',
            'invoices'  => $invoices,
            'filters'   => $filters,
            'clients'   => $this->clientModel->activeList(),
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('sales_invoice', 'create');

        $this->view('sales_invoice/form', [
            'pageTitle' => 'Tambah Invoice Keluar',
            'mode'      => 'create',
            'invoice'   => null,
            'items'     => [],
            'clients'   => $this->clientModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'signatures' => $this->signatureModel->activeList(),
            'units'     => $this->unitModel->activeList(),
            'dpPercentages' => $this->dpPercentageModel->activeList(),
            'itemCatalog'    => $this->itemCatalogModel->activeList(),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'picUsers'       => $this->userModel->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('sales_invoice', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice', 'create');
        }
        verifyCsrf();

        [$data, $items] = $this->collectInput();
        $errors = $this->validateInput($data, $items);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('sales_invoice', 'create');
        }

        assertPeriodOpen('sales_invoice', $data['invoice_date'], 'sales_invoice', 'create');

        // Generate nomor SEBELUM beginTransaction() -- DocumentNumber::next() punya
        // transaction atomic sendiri (SELECT...FOR UPDATE pada counter), tidak bisa
        // dinested di dalam transaction PDO lain (PDO MySQL tidak dukung nested
        // transaction/savepoint otomatis, beginTransaction() kedua akan throw).
        // Kalau insert invoice di bawah gagal & rollback, nomor yang sudah terpakai
        // TETAP hilang (gap di sequence) -- itu perilaku yang benar untuk numbering
        // dokumen (gap boleh, duplicate TIDAK boleh), bukan bug.
        $invoiceNumber = $this->invoiceModel->generateInvoiceNumber($data['invoice_type'], $data['invoice_date']);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $totals = $this->calculateTotals($items, $data['dp_percentage'], $data['ppn_percent']);

            $invoiceId = $this->invoiceModel->create(array_merge($data, [
                'invoice_number' => $invoiceNumber,
                'subtotal'       => $totals['subtotal'],
                'dp_amount'      => $totals['dp_amount'],
                'ppn_amount'     => $totals['ppn_amount'],
                'total_amount'   => $totals['total'],
                'created_by'     => currentUserId(),
            ]));

            $this->saveItems($invoiceId, $items);

            $this->activityLog->log(currentUserId(), 'sales_invoice', 'create', "Invoice Keluar {$invoiceNumber} dibuat");

            $pdo->commit();
            setFlash('success', 'Invoice Keluar berhasil disimpan.');
            $this->redirect('sales_invoice', 'detail', ['id' => $invoiceId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('SalesInvoice store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Invoice Keluar. Silakan coba lagi.');
            $this->redirect('sales_invoice', 'create');
        }
    }

    public function detail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $invoice = $this->invoiceModel->findWithRelations($id);

        if (!$invoice) {
            setFlash('error', 'Invoice Keluar tidak ditemukan.');
            $this->redirect('sales_invoice', 'index');
        }

        $this->view('sales_invoice/detail', [
            'pageTitle' => 'Detail Invoice Keluar',
            'invoice'   => $invoice,
            'items'     => $this->itemModel->itemsByInvoice($id),
            'isBilled'  => $this->invoiceModel->isBilled($id),
        ]);
    }

    public function edit()
    {
        Middleware::requirePermission('sales_invoice', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $invoice = $this->invoiceModel->findWithRelations($id);

        if (!$invoice) {
            setFlash('error', 'Invoice Keluar tidak ditemukan.');
            $this->redirect('sales_invoice', 'index');
        }

        $this->view('sales_invoice/form', [
            'pageTitle' => 'Edit Invoice Keluar',
            'mode'      => 'edit',
            'invoice'   => $invoice,
            'items'     => $this->itemModel->itemsByInvoice($id),
            'clients'   => $this->clientModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'signatures' => $this->signatureModel->activeList(),
            'units'     => $this->unitModel->activeList(),
            'dpPercentages' => $this->dpPercentagesForEdit($invoice),
            'itemCatalog'    => $this->itemCatalogForEdit($this->itemModel->itemsByInvoice($id)),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'picUsers'       => $this->userModel->activeList(),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('sales_invoice', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->invoiceModel->find($id);

        if (!$existing) {
            setFlash('error', 'Invoice Keluar tidak ditemukan.');
            $this->redirect('sales_invoice', 'index');
        }

        [$data, $items] = $this->collectInput();
        $errors = $this->validateInput($data, $items);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('sales_invoice', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('sales_invoice', $existing['invoice_date'], 'sales_invoice', 'edit', ['id' => $id]);
        assertPeriodOpen('sales_invoice', $data['invoice_date'], 'sales_invoice', 'edit', ['id' => $id]);

        // Jenis Invoice (project/lampu) TIDAK BOLEH berubah lewat edit -- nomor
        // yang sudah tersimpan sudah "mengunci" kodenya (INV.HME vs FKT.HME), jadi
        // apapun yang dikirim browser diabaikan & selalu dipertahankan dari data
        // asli. Field-nya di form.php memang <select disabled> (tidak terkirim),
        // ini lapisan pertahanan kedua supaya tidak bisa dipalsukan lewat request manual.
        $data['invoice_type'] = $existing['invoice_type'];

        // Kalau user TIDAK mengganti pilihan Tagihan DP (id sama dengan yang sudah
        // tersimpan), pertahankan nilai % yang SUDAH tersnapshot di invoice ini --
        // JANGAN ambil ulang dari master, walau baris masternya sudah diedit/diubah
        // persentasenya sejak invoice ini dibuat. Snapshot cuma boleh berubah kalau
        // user benar-benar memilih baris DP yang BERBEDA (poin #7/#8 revisi: invoice
        // lama tidak boleh ikut berubah hanya karena master DP diedit belakangan).
        if ($data['dp_percentage_id'] !== null && $data['dp_percentage_id'] === (int) $existing['dp_percentage_id']) {
            $data['dp_percentage'] = (float) $existing['dp_percentage'];
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $totals = $this->calculateTotals($items, $data['dp_percentage'], $data['ppn_percent']);

            $this->invoiceModel->updateById($id, array_merge($data, [
                'subtotal'     => $totals['subtotal'],
                'dp_amount'    => $totals['dp_amount'],
                'ppn_amount'   => $totals['ppn_amount'],
                'total_amount' => $totals['total'],
            ]));

            $this->itemModel->deleteByInvoice($id);
            $this->saveItems($id, $items);

            $this->activityLog->log(currentUserId(), 'sales_invoice', 'update', "Invoice Keluar {$existing['invoice_number']} diperbarui");

            $pdo->commit();
            setFlash('success', 'Invoice Keluar berhasil diperbarui.');
            $this->redirect('sales_invoice', 'detail', ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('SalesInvoice update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Invoice Keluar.');
            $this->redirect('sales_invoice', 'edit', ['id' => $id]);
        }
    }

    public function delete()
    {
        Middleware::requirePermission('sales_invoice', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $invoice = $this->invoiceModel->find($id);

        if (!$invoice) {
            setFlash('error', 'Invoice Keluar tidak ditemukan.');
            $this->redirect('sales_invoice', 'index');
        }

        if ($this->invoiceModel->isBilled($id)) {
            setFlash('error', 'Invoice ini sudah dipakai di sebuah Tanda Terima, tidak bisa dihapus.');
            $this->redirect('sales_invoice', 'detail', ['id' => $id]);
        }

        assertPeriodOpen('sales_invoice', $invoice['invoice_date'], 'sales_invoice', 'index');
        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true ? 'Invoice Keluar berhasil dihapus.' : 'Gagal menghapus Invoice Keluar.');
        $this->redirect('sales_invoice', 'index');
    }

    /**
     * Hapus 1 Invoice Keluar ke Tempat Sampah. true = sukses, string = alasan skip.
     * Dipakai delete() & rangeDelete().
     */
    private function deleteOneRecord(int $id)
    {
        $invoice = $this->invoiceModel->find($id);
        if (!$invoice) {
            return 'gagal';
        }
        // Soft-delete ke Tempat Sampah aman walau Invoice sudah masuk Tanda
        // Terima / periode terkunci (cuma set deleted_at). Gerbang isBilled &
        // Tutup Bulan tetap berlaku untuk hapus per-baris lewat delete().
        try {
            $this->invoiceModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'sales_invoice', 'delete', "Invoice Keluar {$invoice['invoice_number']} dihapus");
            return true;
        } catch (Throwable $e) {
            error_log('SalesInvoice deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua Invoice Keluar dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('sales_invoice', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->invoiceModel->idsByDateRange('invoice_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('sales_invoice', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('sales_invoice', 'index');
    }

    /**
     * Cetak 1/beberapa/semua Invoice Keluar dengan template yang identik --
     * pola persis PurchaseOrderController::print() (ids[] dari GET, di-refetch
     * & divalidasi server-side, ID yang tidak ditemukan diabaikan diam-diam).
     * Tanpa ?ids= sama sekali -> "Cetak Semua" ikut filter yang aktif saat itu.
     */
    public function print()
    {
        $idsParam = $_GET['ids'] ?? [];
        if (is_string($idsParam)) {
            $idsParam = explode(',', $idsParam);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsParam), fn($id) => $id > 0)));

        if (!empty($ids)) {
            $invoices = [];
            foreach ($ids as $id) {
                $inv = $this->invoiceModel->findWithRelations($id);
                if ($inv) {
                    $invoices[] = $inv;
                }
            }
        } else {
            // "Cetak Semua" -- tetap ikut filter tanggal/keyword aktif, BUKAN seluruh tabel.
            $filters = [
                'keyword'      => trim($_GET['keyword'] ?? ''),
                'client_id'    => $_GET['client_id'] ?? '',
                'invoice_type' => $_GET['invoice_type'] ?? '',
                'date_from'    => $_GET['date_from'] ?? '',
                'date_to'      => $_GET['date_to'] ?? '',
            ];
            $rows = $this->invoiceModel->listWithRelations($filters);
            $invoices = [];
            foreach ($rows as $row) {
                $invoices[] = $this->invoiceModel->findWithRelations((int) $row['id']);
            }
        }

        if (empty($invoices)) {
            setFlash('error', 'Tidak ada Invoice Keluar untuk dicetak.');
            $this->redirect('sales_invoice', 'index');
        }

        foreach ($invoices as &$inv) {
            $inv['items'] = $this->itemModel->itemsByInvoice((int) $inv['id']);
        }

        $this->view('sales_invoice/print', [
            'pageTitle'   => 'Cetak Invoice',
            'invoices'    => $invoices,
            'company'     => (new SystemSetting())->getGroup('company'),
            'bankAccount' => (new CompanyBankAccount())->activeAccount(),
        ]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        // Persentase DP TIDAK BOLEH dipercaya dari input manual -- ambil dari ID
        // yang dipilih user, lalu resolve nilai % dari master (atau dari invoice
        // yang sedang diedit kalau baris masternya sudah dihapus/dinonaktifkan --
        // lihat dpPercentagesForEdit()). $dpPercentageId null/0 => 'dp_percentage'
        // null, ditolak di validateInput() (wajib pilih salah satu).
        $dpPercentageId = !empty($_POST['dp_percentage_id']) ? (int) $_POST['dp_percentage_id'] : null;
        // findAny() (bukan find()) SENGAJA -- kalau invoice diedit tanpa mengubah
        // pilihan DP, dan baris masternya sudah dihapus/dinonaktifkan di antara
        // waktu itu, edit tidak boleh gagal validasi hanya karena hal itu (nilai %
        // tetap diambil dari baris master aslinya, id-nya nyata & tidak bisa
        // dipalsukan ke angka sembarang, jadi tetap aman bukan trust-dari-browser).
        $dpRow = $dpPercentageId ? $this->dpPercentageModel->findAny($dpPercentageId) : null;

        $invoiceType = $_POST['invoice_type'] ?? '';

        $data = [
            'client_id'        => (int) ($_POST['client_id'] ?? 0),
            'project_id'       => !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null,
            // 'project' (INV.HME) vs 'lampu' (FKT.HME) -- lihat SalesInvoice::generateInvoiceNumber().
            'invoice_type'     => in_array($invoiceType, ['project', 'lampu'], true) ? $invoiceType : 'project',
            'invoice_date'     => $_POST['invoice_date'] ?? '',
            'contract_number'  => trim($_POST['contract_number'] ?? '') ?: null,
            'contract_date'    => trim($_POST['contract_date'] ?? '') ?: null,
            'dp_percentage_id' => $dpRow ? $dpPercentageId : null,
            'dp_percentage'    => $dpRow ? (float) $dpRow['percentage'] : null,
            'ppn_percent'      => (float) ($_POST['ppn_percent'] ?? 11),
            'tax_invoice_number' => trim($_POST['tax_invoice_number'] ?? '') ?: null,
            'signature_id'     => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'            => trim($_POST['notes'] ?? '') ?: null,
        ];

        $items = [];
        $itemIds = $_POST['item_id'] ?? [];
        $descriptions = $_POST['description'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $units = $_POST['unit'] ?? [];
        $prices = $_POST['unit_price'] ?? [];

        foreach ($descriptions as $i => $description) {
            $description = trim($description);
            if ($description === '') {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 0);
            $unitPrice = parseCurrencyInput($prices[$i] ?? 0);
            $items[] = [
                // item_id opsional -- baris item invoice BOLEH dari Master Barang
                // (dropdown "Pilih dari Master Barang") ATAU jasa/deskripsi bebas
                // (banyak invoice riil sudah pakai baris jasa freetext, mis. "Jasa
                // konsultasi teknis" -- lihat migration untuk audit datanya), jadi
                // item_id TIDAK boleh diwajibkan di sini.
                'item_id'     => !empty($itemIds[$i]) ? (int) $itemIds[$i] : null,
                'description' => $description,
                'qty'         => $qty,
                'unit'        => trim($units[$i] ?? ''),
                'unit_price'  => $unitPrice,
                'subtotal'    => round($qty * $unitPrice, 2),
            ];
        }

        return [$data, $items];
    }

    private function validateInput(array $data, array $items): array
    {
        $errors = [];

        if ($data['client_id'] <= 0 || !$this->clientModel->find($data['client_id'])) {
            $errors[] = 'Client wajib dipilih.';
        }
        if (empty($data['invoice_date'])) {
            $errors[] = 'Tanggal invoice wajib diisi.';
        }
        if ($data['dp_percentage'] === null) {
            $errors[] = 'Tagihan DP wajib dipilih.';
        }
        if ($data['ppn_percent'] < 0) {
            $errors[] = 'PPN tidak boleh negatif.';
        }
        if (empty($items)) {
            $errors[] = 'Minimal 1 baris item invoice wajib diisi.';
        }
        foreach ($items as $item) {
            if ($item['qty'] <= 0) {
                $errors[] = 'Qty setiap baris item harus lebih dari 0.';
                break;
            }
            if ($item['unit'] === '') {
                $errors[] = 'Satuan setiap baris item wajib diisi.';
                break;
            }
        }

        return $errors;
    }

    /**
     * Rumus Invoice Keluar (revisi Tagihan DP):
     *   Jumlah      = SUM(harga jumlah tiap item)
     *   Tagihan DP  = Jumlah x DP%
     *   PPN         = Tagihan DP x PPN%   (BUKAN dari Jumlah)
     *   Total       = Tagihan DP + PPN
     * SELALU dihitung ulang di backend dari item + persentase yang tersimpan --
     * subtotal/dp_amount/ppn_amount/total dari browser TIDAK PERNAH dipakai
     * langsung (poin #20 revisi), JS di form.php cuma preview UX.
     */
    private function calculateTotals(array $items, float $dpPercent, float $ppnPercent): array
    {
        $subtotal = 0.0;
        foreach ($items as $item) {
            $subtotal += $item['subtotal'];
        }
        $subtotal = round($subtotal, 2);
        $dpAmount = round($subtotal * $dpPercent / 100, 2);
        $ppnAmount = round($dpAmount * $ppnPercent / 100, 2);

        return [
            'subtotal'   => $subtotal,
            'dp_amount'  => $dpAmount,
            'ppn_amount' => $ppnAmount,
            'total'      => round($dpAmount + $ppnAmount, 2),
        ];
    }

    /**
     * Pilihan dropdown "Tagihan DP" untuk form Edit: daftar aktif + (kalau
     * perlu) baris DP yang dipakai invoice ini sendiri walau sudah dihapus/
     * dinonaktifkan di master -- supaya dropdown tetap menampilkan pilihan
     * yang sedang tersimpan tanpa memaksa user mengganti-nya.
     */
    private function dpPercentagesForEdit(array $invoice): array
    {
        $active = $this->dpPercentageModel->activeList();
        $currentId = (int) ($invoice['dp_percentage_id'] ?? 0);

        $activeIds = array_map('intval', array_column($active, 'id'));
        if ($currentId > 0 && !in_array($currentId, $activeIds, true)) {
            $currentRow = $this->dpPercentageModel->findAny($currentId);
            if ($currentRow) {
                $active[] = $currentRow;
            }
        }

        return $active;
    }

    /**
     * Katalog Barang untuk dropdown "Pilih dari Master Barang" di form Edit:
     * daftar aktif + (kalau perlu) barang yang dipakai baris invoice ini sendiri
     * walau sudah dihapus/dinonaktifkan di master -- sama seperti pola
     * dpPercentagesForEdit(), supaya dropdown tetap menampilkan barang yang
     * sedang tersimpan tanpa memaksa user mengganti pilihannya.
     */
    private function itemCatalogForEdit(array $invoiceItems): array
    {
        $active = $this->itemCatalogModel->activeList();
        $activeIds = array_map('intval', array_column($active, 'id'));

        foreach ($invoiceItems as $it) {
            $itemId = (int) ($it['item_id'] ?? 0);
            if ($itemId > 0 && !in_array($itemId, $activeIds, true)) {
                $row = $this->itemCatalogModel->findWithRelations($itemId);
                if ($row) {
                    $active[] = $row;
                    $activeIds[] = $itemId;
                }
            }
        }

        return $active;
    }

    private function saveItems(int $invoiceId, array $items): void
    {
        foreach ($items as $item) {
            $this->itemModel->create(array_merge($item, ['sales_invoice_id' => $invoiceId]));
        }
    }
}
