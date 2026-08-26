<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/CollectionReceipt.php';
require_once ROOT_PATH . '/app/models/CollectionReceiptItem.php';
require_once ROOT_PATH . '/app/models/SalesInvoice.php';
require_once ROOT_PATH . '/app/models/DeliveryNote.php';
require_once ROOT_PATH . '/app/models/Signature.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * CollectionReceiptController -- Tanda Terima (tanda terima penagihan).
 * Sesuai template Draft Tanda Terima.pdf: daftar No. Invoice/Faktur Pajak/
 * No. Surat Jalan/Total dari Invoice Keluar (sales_invoices) yang sudah ada --
 * BUKAN daftar barang dari Pengeluaran Barang.
 */
class CollectionReceiptController extends Controller
{
    private CollectionReceipt $receiptModel;
    private CollectionReceiptItem $receiptItemModel;
    private SalesInvoice $invoiceModel;
    private DeliveryNote $deliveryNoteModel;
    private Signature $signatureModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('collection_receipt', 'view');

        $this->receiptModel      = new CollectionReceipt();
        $this->receiptItemModel  = new CollectionReceiptItem();
        $this->invoiceModel      = new SalesInvoice();
        $this->deliveryNoteModel = new DeliveryNote();
        $this->signatureModel    = new Signature();
        $this->activityLog       = new ActivityLog();
    }

    public function index()
    {
        $filters = [
            'keyword'   => trim($_GET['keyword'] ?? ''),
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
        ];

        $this->view('collection_receipt/list', [
            'pageTitle' => 'Tanda Terima',
            'receipts'  => $this->receiptModel->listWithRelations($filters),
            'filters'   => $filters,
        ]);
    }

    /**
     * Form konfirmasi: pilih Surat Jalan opsional per invoice + penerima/TTD,
     * untuk Invoice Keluar yang sudah dicentang di halaman Invoice Keluar.
     */
    public function select()
    {
        Middleware::requirePermission('collection_receipt', 'create');

        $ids = $this->parseIds($_GET['ids'] ?? []);
        [$invoices, $error] = $this->validInvoices($ids);

        if ($error !== null) {
            setFlash('error', $error);
            $this->redirect('sales_invoice', 'index');
        }

        $client = null;
        if (!empty($invoices)) {
            $client = ['id' => $invoices[0]['client_id'], 'name' => $invoices[0]['client_name']];
        }

        $this->view('collection_receipt/select', [
            'pageTitle'    => 'Buat Tanda Terima',
            'invoices'     => $invoices,
            'client'       => $client,
            'deliveryNotes' => $this->deliveryNoteModel->listWithRelations([]),
            'signatures'   => $this->signatureModel->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('collection_receipt', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_invoice', 'index');
        }
        verifyCsrf();

        $ids = $this->parseIds($_POST['invoice_ids'] ?? []);
        [$invoices, $error] = $this->validInvoices($ids);

        if ($error !== null) {
            setFlash('error', $error);
            $this->redirect('sales_invoice', 'index');
        }

        $deliveryNoteIds = $_POST['delivery_note_id'] ?? [];
        $receiptDate = $_POST['receipt_date'] ?: date('Y-m-d');

        $data = [
            'receipt_number' => $this->receiptModel->generateReceiptNumber($receiptDate),
            'client_id'      => $invoices[0]['client_id'],
            'receipt_date'   => $receiptDate,
            'recipient_name' => trim($_POST['recipient_name'] ?? '') ?: null,
            'signature_id'   => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'          => trim($_POST['notes'] ?? '') ?: null,
            'created_by'     => currentUserId(),
        ];

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $receiptId = $this->receiptModel->create($data);

            foreach ($invoices as $inv) {
                $dnId = !empty($deliveryNoteIds[$inv['id']]) ? (int) $deliveryNoteIds[$inv['id']] : null;
                $this->receiptItemModel->create([
                    'collection_receipt_id' => $receiptId,
                    'sales_invoice_id'      => $inv['id'],
                    'delivery_note_id'      => $dnId,
                    'total_amount'          => $inv['total_amount'],
                ]);
            }

            $this->activityLog->log(
                currentUserId(),
                'collection_receipt',
                'create',
                "Tanda Terima {$data['receipt_number']} dibuat untuk " . count($invoices) . ' invoice'
            );

            $pdo->commit();
            setFlash('success', "Tanda Terima {$data['receipt_number']} berhasil dibuat.");
            $this->redirect('collection_receipt', 'print', ['id' => $receiptId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('CollectionReceipt store error: ' . $e->getMessage());
            setFlash('error', 'Gagal membuat Tanda Terima. Silakan coba lagi.');
            $this->redirect('sales_invoice', 'index');
        }
    }

    /**
     * Edit Tanda Terima yang sudah ada: field header (tanggal/penerima/TTD/
     * catatan) + baris invoice yang termasuk di dalamnya (bisa tambah/lepas
     * invoice, atau ganti Surat Jalan per baris). Nomor & Client TETAP
     * (immutable) -- Client tidak boleh diganti karena semua baris invoice
     * memang milik 1 client yang sama sejak dibuat; ganti client berarti
     * bikin dokumen baru, bukan edit. Nomor tidak pernah berubah setelah terbit.
     */
    public function edit()
    {
        Middleware::requirePermission('collection_receipt', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receiptModel->findWithRelations($id);

        if (!$receipt) {
            setFlash('error', 'Tanda Terima tidak ditemukan.');
            $this->redirect('collection_receipt', 'index');
        }

        $currentItems = $this->receiptItemModel->itemsByReceipt($id);
        $currentInvoiceIds = array_column($currentItems, 'sales_invoice_id');

        // Invoice yang boleh dipilih: milik client yang sama, belum ditagih di
        // TANDA TERIMA LAIN (invoice yang sudah termasuk di receipt ini sendiri
        // tetap muncul, lihat availableForClient()).
        $availableInvoices = $this->invoiceModel->availableForClient((int) $receipt['client_id'], $id);

        $this->view('collection_receipt/edit', [
            'pageTitle'        => 'Edit Tanda Terima',
            'receipt'          => $receipt,
            'currentItems'     => $currentItems,
            'currentInvoiceIds' => $currentInvoiceIds,
            'availableInvoices' => $availableInvoices,
            'deliveryNotes'    => $this->deliveryNoteModel->listWithRelations([]),
            'signatures'       => $this->signatureModel->activeList(),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('collection_receipt', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('collection_receipt', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->receiptModel->find($id);
        if (!$existing) {
            setFlash('error', 'Tanda Terima tidak ditemukan.');
            $this->redirect('collection_receipt', 'index');
        }

        $invoiceIds = $this->parseIds($_POST['invoice_ids'] ?? []);
        if (empty($invoiceIds)) {
            setFlash('error', 'Minimal 1 invoice harus tetap ada di Tanda Terima.');
            $this->redirect('collection_receipt', 'edit', ['id' => $id]);
        }

        // Invoice boleh dipilih kalau: milik client yang sama DENGAN receipt ini
        // (client tidak berubah), dan belum ditagih di receipt LAIN.
        $eligible = $this->invoiceModel->availableForClient((int) $existing['client_id'], $id);
        $eligibleById = [];
        foreach ($eligible as $inv) {
            $eligibleById[(int) $inv['id']] = $inv;
        }

        $invoices = [];
        foreach ($invoiceIds as $invId) {
            if (isset($eligibleById[$invId])) {
                $invoices[] = $eligibleById[$invId];
            }
        }

        if (empty($invoices)) {
            setFlash('error', 'Invoice yang dipilih tidak valid untuk Tanda Terima ini.');
            $this->redirect('collection_receipt', 'edit', ['id' => $id]);
        }

        $deliveryNoteIds = $_POST['delivery_note_id'] ?? [];

        $updateData = [
            'receipt_date'   => $_POST['receipt_date'] ?: $existing['receipt_date'],
            'recipient_name' => trim($_POST['recipient_name'] ?? '') ?: null,
            'signature_id'   => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'          => trim($_POST['notes'] ?? '') ?: null,
        ];

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $this->receiptModel->updateById($id, $updateData);

            // Delete-lalu-recreate baris item -- pola yang sama dengan
            // SalesInvoiceController::update(), lebih sederhana & aman daripada
            // diff manual, dan collection_receipt_items tidak dirujuk tabel lain
            // (CASCADE dari collection_receipt_id) jadi aman dihapus-ulang.
            $this->receiptItemModel->deleteByReceipt($id);
            foreach ($invoices as $inv) {
                $dnId = !empty($deliveryNoteIds[$inv['id']]) ? (int) $deliveryNoteIds[$inv['id']] : null;
                $this->receiptItemModel->create([
                    'collection_receipt_id' => $id,
                    'sales_invoice_id'      => $inv['id'],
                    'delivery_note_id'      => $dnId,
                    'total_amount'          => $inv['total_amount'],
                ]);
            }

            $this->activityLog->log(
                currentUserId(),
                'collection_receipt',
                'update',
                "Tanda Terima {$existing['receipt_number']} diperbarui (" . count($invoices) . ' invoice)'
            );

            $pdo->commit();
            setFlash('success', "Tanda Terima {$existing['receipt_number']} berhasil diperbarui.");
            $this->redirect('collection_receipt', 'print', ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('CollectionReceipt update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Tanda Terima.');
            $this->redirect('collection_receipt', 'edit', ['id' => $id]);
        }
    }

    public function print()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receiptModel->findWithRelations($id);

        if (!$receipt) {
            setFlash('error', 'Tanda Terima tidak ditemukan.');
            $this->redirect('collection_receipt', 'index');
        }

        $receipt['items'] = $this->receiptItemModel->itemsByReceipt($id);

        $this->view('collection_receipt/print', [
            'pageTitle' => 'Cetak Tanda Terima',
            'receipts'  => [$receipt],
            'company'   => (new SystemSetting())->getGroup('company'),
        ]);
    }

    /**
     * Cetak Terpilih/Cetak Semua dari daftar Tanda Terima. Tanpa ?ids= sama
     * sekali -> "Cetak Semua" ikut filter tanggal/keyword aktif, bukan seluruh tabel.
     */
    public function printMany()
    {
        $idsParam = $_GET['ids'] ?? [];
        $ids = $this->parseIds($idsParam);

        if (!empty($ids)) {
            $receiptIds = $ids;
        } else {
            $filters = [
                'keyword'   => trim($_GET['keyword'] ?? ''),
                'date_from' => $_GET['date_from'] ?? '',
                'date_to'   => $_GET['date_to'] ?? '',
            ];
            $receiptIds = array_column($this->receiptModel->listWithRelations($filters), 'id');
        }

        $receipts = [];
        foreach ($receiptIds as $id) {
            $receipt = $this->receiptModel->findWithRelations((int) $id);
            if ($receipt) {
                $receipt['items'] = $this->receiptItemModel->itemsByReceipt((int) $id);
                $receipts[] = $receipt;
            }
        }

        if (empty($receipts)) {
            setFlash('error', 'Tanda Terima yang dipilih tidak ditemukan.');
            $this->redirect('collection_receipt', 'index');
        }

        $this->view('collection_receipt/print', [
            'pageTitle' => 'Cetak Tanda Terima',
            'receipts'  => $receipts,
            'company'   => (new SystemSetting())->getGroup('company'),
        ]);
    }

    public function delete()
    {
        Middleware::requirePermission('collection_receipt', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('collection_receipt', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $receipt = $this->receiptModel->find($id);

        if (!$receipt) {
            setFlash('error', 'Tanda Terima tidak ditemukan.');
            $this->redirect('collection_receipt', 'index');
        }

        // collection_receipt_items ikut terhapus via ON DELETE CASCADE -- invoice
        // yang tadinya masuk tanda terima ini otomatis bisa dipilih lagi (isBilled()).
        $this->receiptModel->deleteById($id);
        $this->activityLog->log(currentUserId(), 'collection_receipt', 'delete', "Tanda Terima {$receipt['receipt_number']} dihapus");
        setFlash('success', 'Tanda Terima berhasil dihapus.');
        $this->redirect('collection_receipt', 'index');
    }

    // ================= Helper privat =================

    private function parseIds($idsParam): array
    {
        if (is_string($idsParam)) {
            $idsParam = explode(',', $idsParam);
        }
        return array_values(array_unique(array_filter(array_map('intval', $idsParam), fn($id) => $id > 0)));
    }

    /**
     * Validasi invoice yang dipilih: harus ada, belum pernah masuk Tanda Terima
     * lain (isBilled()), dan SEMUA milik client yang SAMA (satu Tanda Terima =
     * satu Customer, sesuai template).
     */
    private function validInvoices(array $ids): array
    {
        if (empty($ids)) {
            return [[], 'Pilih minimal 1 Invoice Keluar untuk dibuatkan Tanda Terima.'];
        }

        $invoices = [];
        foreach ($ids as $id) {
            $inv = $this->invoiceModel->findWithRelations($id);
            if ($inv && !$this->invoiceModel->isBilled($id)) {
                $invoices[] = $inv;
            }
        }

        if (empty($invoices)) {
            return [[], 'Invoice yang dipilih tidak valid atau sudah tercatat di Tanda Terima lain.'];
        }

        $clientId = $invoices[0]['client_id'];
        foreach ($invoices as $inv) {
            if ((int) $inv['client_id'] !== (int) $clientId) {
                return [[], 'Semua invoice yang dipilih harus dari Client yang sama (satu Tanda Terima = satu Customer).'];
            }
        }

        return [$invoices, null];
    }
}
