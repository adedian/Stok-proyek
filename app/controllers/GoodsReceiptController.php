<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/GoodsReceipt.php';
require_once ROOT_PATH . '/app/models/GoodsReceiptItem.php';
require_once ROOT_PATH . '/app/models/DeliveryDocument.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderHistory.php';
require_once ROOT_PATH . '/app/models/OfflinePurchase.php';
require_once ROOT_PATH . '/app/models/Inventory.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/Signature.php';

class GoodsReceiptController extends Controller
{
    private GoodsReceipt $receiptModel;
    private GoodsReceiptItem $receiptItemModel;
    private DeliveryDocument $documentModel;
    private PurchaseOrder $poModel;
    private PurchaseOrderHistory $historyModel;
    private OfflinePurchase $offlinePurchaseModel;
    private Inventory $inventoryModel;
    private Project $projectModel;
    private ActivityLog $activityLog;
    private Unit $unitModel;
    private Signature $signatureModel;

    public function __construct()
    {
        Middleware::requirePermission('goods_receipt', 'view');

        $this->receiptModel     = new GoodsReceipt();
        $this->receiptItemModel = new GoodsReceiptItem();
        $this->documentModel    = new DeliveryDocument();
        $this->poModel          = new PurchaseOrder();
        $this->historyModel     = new PurchaseOrderHistory();
        $this->offlinePurchaseModel = new OfflinePurchase();
        $this->inventoryModel   = new Inventory();
        $this->projectModel     = new Project();
        $this->activityLog      = new ActivityLog();
        $this->unitModel        = new Unit();
        $this->signatureModel   = new Signature();
    }

    public function index()
    {
        $filters = [
            'keyword'     => $_GET['keyword'] ?? '',
            'po_id'       => $_GET['po_id'] ?? '',
            'stock_scope' => $_GET['stock_scope'] ?? '',
            'date_from'   => $_GET['date_from'] ?? '',
            'date_to'     => $_GET['date_to'] ?? '',
        ];

        $receipts = $this->receiptModel->listWithRelations($filters);

        $this->view('goods_receipt/list', [
            'pageTitle' => 'Penerimaan Barang',
            'receipts'  => $receipts,
            'filters'   => $filters,
        ]);
    }

    public function detail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receiptModel->findWithRelations($id);

        if (!$receipt) {
            setFlash('error', 'Data penerimaan barang tidak ditemukan.');
            $this->redirect('goods_receipt', 'index');
        }

        $items = $this->receiptItemModel->itemsByReceipt($id);
        $documents = $this->documentModel->byReceipt($id);

        $this->view('goods_receipt/detail', [
            'pageTitle' => 'Detail Penerimaan Barang',
            'receipt'   => $receipt,
            'items'     => $items,
            'documents' => $documents,
            'statusLabels'     => $this->receiptItemModel->statusLabels,
            'statusBadgeClass' => $this->receiptItemModel->statusBadgeClass,
        ]);
    }

    /**
     * Form tambah penerimaan. Kalau po_id sudah dipilih, langsung tampilkan daftar
     * item PO tsb beserta sisa qty yang belum diterima.
     */
    public function create()
    {
        Middleware::requirePermission('goods_receipt', 'create');
        $poId = (int) ($_GET['po_id'] ?? 0);
        $offlinePurchaseId = (int) ($_GET['offline_purchase_id'] ?? 0);
        $selectedPo = $poId ? $this->poModel->findWithRelations($poId) : null;
        $poItems = $poId ? $this->receiptItemModel->poItemsForReceipt($poId) : [];
        $selectedOfflinePurchase = $offlinePurchaseId ? $this->offlinePurchaseModel->findWithRelations($offlinePurchaseId) : null;
        $offlineItems = $offlinePurchaseId ? $this->receiptItemModel->offlineItemsForReceipt($offlinePurchaseId) : [];
        $defaultReceiptType = $offlinePurchaseId ? 'offline_purchase' : 'purchase_order';

        $this->view('goods_receipt/form', [
            'pageTitle'      => 'Tambah Penerimaan Barang',
            'mode'           => 'create',
            'receipt'        => $offlinePurchaseId ? ['receipt_type' => 'offline_purchase'] : null,
            'receiptNumber'  => $this->receiptModel->previewReceiptNumber(),
            'poList'         => $this->poModel->receivablePoList(),
            'selectedPo'     => $selectedPo,
            'poItems'        => $poItems,
            'offlinePurchaseList' => $this->offlinePurchaseModel->receivableList(),
            'selectedOfflinePurchase' => $selectedOfflinePurchase,
            'offlineItems'   => $offlineItems,
            'defaultReceiptType' => $defaultReceiptType,
            'projects'       => $this->projectModel->activeList(),
            'units'          => $this->unitModel->activeList(),
            'mismatchItems'  => [],
            'documents'      => [],
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('goods_receipt', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('goods_receipt', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('goods_receipt', 'create', ['po_id' => $data['purchase_order_id']]);
        }

        assertPeriodOpen('goods_receipt', $data['receipt_date'], 'goods_receipt', 'create', ['po_id' => $data['purchase_order_id']]);

        try {
            $photoGoods = handleFileUpload('photo_goods', 'foto_barang', ['jpg', 'jpeg', 'png', 'webp'], 5);
            // Upload Invoice pada Penerimaan Barang -- extension+MIME+size+secure
            // filename semua sudah divalidasi di handleFileUpload() (lihat
            // upload_helper.php). Dilakukan SEBELUM beginTransaction(): kalau upload
            // gagal, penerimaan tidak boleh tersimpan setengah (tidak ada row yang
            // sempat dibuat sama sekali).
            $invoiceFile = handleFileUpload('invoice_file', 'invoice_penerimaan', ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('goods_receipt', 'create', ['po_id' => $data['purchase_order_id']]);
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $receiptId = $this->receiptModel->create([
                'purchase_order_id'   => $data['purchase_order_id'] ?: null,
                'offline_purchase_id' => $data['offline_purchase_id'] ?: null,
                'receipt_type'      => $data['receipt_type'],
                'stock_scope'       => $data['stock_scope'],
                'project_id'        => $data['project_id'] ?: null,
                'source_detail'     => $data['source_detail'],
                'receipt_number'    => $this->receiptModel->generateReceiptNumber(),
                'receipt_date'      => $data['receipt_date'],
                'received_by'       => null,
                'receiver_name'     => $data['receiver_name'],
                'photo_goods'       => $photoGoods,
                'invoice_file'      => $invoiceFile,
                'notes'             => $data['notes'],
                'created_by'        => currentUserId(),
            ]);

            $this->saveReceiptItems($receiptId, $data);
            $this->saveDeliveryDocuments($receiptId);

            if ($data['purchase_order_id']) {
                $this->poModel->refreshReceiptStatus((int) $data['purchase_order_id']);
                $this->historyModel->log(
                    (int) $data['purchase_order_id'],
                    'goods_received',
                    "Penerimaan barang {$this->receiptModel->find($receiptId)['receipt_number']} dicatat",
                    currentUserId()
                );
            } elseif ($data['offline_purchase_id']) {
                $this->offlinePurchaseModel->refreshReceiptStatus((int) $data['offline_purchase_id']);
                $this->activityLog->log(
                    currentUserId(),
                    'goods_receipt',
                    'create',
                    "Penerimaan barang dari Pembelian Offline {$this->receiptModel->find($receiptId)['receipt_number']} dicatat"
                );
            } else {
                $this->activityLog->log(
                    currentUserId(),
                    'goods_receipt',
                    'create',
                    "Penerimaan barang dari Pemakai {$this->receiptModel->find($receiptId)['receipt_number']} dicatat"
                );
            }

            $pdo->commit();

            setFlash('success', 'Penerimaan barang berhasil disimpan.');
            $this->redirect('goods_receipt', 'detail', ['id' => $receiptId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Goods receipt store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan penerimaan barang. Silakan coba lagi.');
            $this->redirect('goods_receipt', 'create', ['po_id' => $data['purchase_order_id']]);
        }
    }

    public function edit()
    {
        Middleware::requirePermission('goods_receipt', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receiptModel->findWithRelations($id);

        if (!$receipt) {
            setFlash('error', 'Data penerimaan barang tidak ditemukan.');
            $this->redirect('goods_receipt', 'index');
        }
        if ($receipt['receipt_type'] === 'pemakai') {
            setFlash('error', 'Penerimaan dari Pemakai tidak bisa diedit. Hapus dan catat ulang jika ada kesalahan.');
            $this->redirect('goods_receipt', 'detail', ['id' => $id]);
        }

        $isOffline = $receipt['receipt_type'] === 'offline_purchase';
        $poItems = [];
        $offlineItems = [];
        if ($isOffline) {
            $offlineItems = $this->receiptItemModel->offlineItemsForReceipt((int) $receipt['offline_purchase_id'], $id);
        } else {
            $poItems = $this->receiptItemModel->poItemsForReceipt((int) $receipt['purchase_order_id'], $id);
        }
        $receiptItems = $this->receiptItemModel->itemsByReceipt($id);

        // Gabungkan qty yang sudah diinput di receipt ini ke daftar item sumber, supaya form pre-filled
        $receiptQtyMap = [];
        foreach ($receiptItems as $ri) {
            if ($isOffline && $ri['offline_purchase_item_id'] !== null) {
                $receiptQtyMap[$ri['offline_purchase_item_id']] = (float) $ri['qty_received'];
            } elseif (!$isOffline && $ri['purchase_order_item_id'] !== null) {
                $receiptQtyMap[$ri['purchase_order_item_id']] = (float) $ri['qty_received'];
            }
        }
        foreach ($poItems as &$pi) {
            $pi['qty_received_current'] = $receiptQtyMap[$pi['po_item_id']] ?? 0;
        }
        unset($pi);
        foreach ($offlineItems as &$oi) {
            $oi['qty_received_current'] = $receiptQtyMap[$oi['offline_item_id']] ?? 0;
        }
        unset($oi);

        // Baris "Barang Tidak Sesuai" (purchase_order_item_id & offline_purchase_item_id NULL)
        // perlu di-preload supaya tidak hilang saat form ini disubmit ulang
        // (update() hapus+insert ulang semua item).
        $mismatchItems = array_values(array_filter(
            $receiptItems,
            fn($ri) => $ri['purchase_order_item_id'] === null && $ri['offline_purchase_item_id'] === null
        ));

        $this->view('goods_receipt/form', [
            'pageTitle'      => 'Edit Penerimaan Barang',
            'mode'           => 'edit',
            'receipt'        => $receipt,
            'receiptNumber'  => $receipt['receipt_number'],
            'poList'         => $this->poModel->receivablePoList(),
            'selectedPo'     => $receipt,
            'poItems'        => $poItems,
            'offlinePurchaseList' => $this->offlinePurchaseModel->receivableList(),
            'selectedOfflinePurchase' => $receipt,
            'offlineItems'   => $offlineItems,
            'defaultReceiptType' => $receipt['receipt_type'],
            'projects'       => $this->projectModel->activeList(),
            'units'          => $this->unitModel->activeList(),
            'mismatchItems'  => $mismatchItems,
            'documents'      => $this->documentModel->byReceipt($id),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('goods_receipt', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('goods_receipt', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->receiptModel->find($id);

        if (!$existing) {
            setFlash('error', 'Data penerimaan barang tidak ditemukan.');
            $this->redirect('goods_receipt', 'index');
        }

        if ($existing['receipt_type'] === 'pemakai') {
            setFlash('error', 'Penerimaan dari Pemakai tidak bisa diedit.');
            $this->redirect('goods_receipt', 'detail', ['id' => $id]);
        }

        $data = $this->collectInput();
        // Sumber/PO/Pembelian Offline & tipe/kategori tidak boleh diganti saat edit -- gunakan data asli
        $data['purchase_order_id']   = (int) $existing['purchase_order_id'];
        $data['offline_purchase_id'] = (int) $existing['offline_purchase_id'];
        $data['receipt_type']  = $existing['receipt_type'];
        $data['stock_scope']   = $existing['stock_scope'];
        $data['project_id']    = $existing['project_id'];
        $data['source_detail'] = $existing['source_detail'];
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('goods_receipt', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('goods_receipt', $existing['receipt_date'], 'goods_receipt', 'edit', ['id' => $id]);
        assertPeriodOpen('goods_receipt', $data['receipt_date'], 'goods_receipt', 'edit', ['id' => $id]);

        try {
            $photoGoods = handleFileUpload('photo_goods', 'foto_barang', ['jpg', 'jpeg', 'png', 'webp'], 5);
            $invoiceFile = handleFileUpload('invoice_file', 'invoice_penerimaan', ['pdf', 'jpg', 'jpeg', 'png', 'webp'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('goods_receipt', 'edit', ['id' => $id]);
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $po = $data['purchase_order_id'] ? $this->poModel->find($data['purchase_order_id']) : null;
            $offlinePurchase = $data['offline_purchase_id'] ? $this->offlinePurchaseModel->find($data['offline_purchase_id']) : null;
            $effectiveProjectId = $po ? (int) $po['project_id']
                : ($offlinePurchase ? (int) $offlinePurchase['project_id'] : (int) ($data['project_id'] ?? 0));

            // Batalkan kredit stok lama HANYA untuk item yang benar-benar sudah
            // pernah diposting (stock_posted_at terisi -- lihat ValidationController).
            // Item yang mengubah data penerimaannya otomatis butuh divalidasi ulang.
            $oldItems = $this->receiptItemModel->itemsByReceipt($id);
            foreach ($oldItems as $oldItem) {
                if (empty($oldItem['stock_posted_at'])) {
                    continue;
                }
                $this->inventoryModel->reverseCredit(
                    $oldItem['item_name'],
                    $oldItem['unit'],
                    $effectiveProjectId ?: null,
                    (float) $oldItem['qty_received'],
                    'goods_receipt',
                    $id,
                    currentUserId(),
                    $data['stock_scope']
                );
            }

            $updateData = [
                'receipt_date'  => $data['receipt_date'],
                'receiver_name' => $data['receiver_name'],
                'notes'         => $data['notes'],
            ];
            if ($photoGoods !== null) {
                $updateData['photo_goods'] = $photoGoods;
            }
            if ($invoiceFile !== null) {
                $updateData['invoice_file'] = $invoiceFile;
            }
            $this->receiptModel->updateById($id, $updateData);

            $this->receiptItemModel->deleteByReceipt($id);
            $this->saveReceiptItems($id, $data);
            $this->saveDeliveryDocuments($id);

            if ($data['purchase_order_id']) {
                $this->poModel->refreshReceiptStatus((int) $data['purchase_order_id']);
                $this->historyModel->log(
                    (int) $data['purchase_order_id'],
                    'goods_receipt_updated',
                    "Penerimaan barang {$existing['receipt_number']} diperbarui",
                    currentUserId()
                );
            } elseif ($data['offline_purchase_id']) {
                $this->offlinePurchaseModel->refreshReceiptStatus((int) $data['offline_purchase_id']);
                $this->activityLog->log(
                    currentUserId(),
                    'goods_receipt',
                    'update',
                    "Penerimaan barang dari Pembelian Offline {$existing['receipt_number']} diperbarui"
                );
            }

            $pdo->commit();

            setFlash('success', 'Penerimaan barang berhasil diperbarui.');
            $this->redirect('goods_receipt', 'detail', ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Goods receipt update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui penerimaan barang.');
            $this->redirect('goods_receipt', 'edit', ['id' => $id]);
        }
    }

    public function delete()
    {
        Middleware::requirePermission('goods_receipt', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('goods_receipt', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $receipt = $this->receiptModel->find($id);

        if (!$receipt) {
            setFlash('error', 'Data penerimaan barang tidak ditemukan.');
            $this->redirect('goods_receipt', 'index');
        }

        assertPeriodOpen('goods_receipt', $receipt['receipt_date'], 'goods_receipt', 'index');

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // findAny() dipakai (bukan find()) -- PO/Pembelian Offline induk bisa saja sudah
            // di-soft-delete duluan, tapi project_id historisnya tetap dibutuhkan supaya
            // koreksi stok benar.
            $po = $receipt['purchase_order_id'] ? $this->poModel->findAny((int) $receipt['purchase_order_id']) : null;
            $offlinePurchase = $receipt['offline_purchase_id'] ? $this->offlinePurchaseModel->find((int) $receipt['offline_purchase_id']) : null;
            $effectiveProjectId = $po ? (int) $po['project_id']
                : ($offlinePurchase ? (int) $offlinePurchase['project_id'] : (int) ($receipt['project_id'] ?? 0));
            $items = $this->receiptItemModel->itemsByReceipt($id);

            // Hanya reverse item yang benar-benar sudah pernah diposting ke stok
            // (stock_posted_at terisi) -- item yang belum/tidak lolos validasi
            // memang tidak pernah masuk stok, jadi tidak ada yang perlu dikoreksi.
            foreach ($items as $item) {
                if (empty($item['stock_posted_at'])) {
                    continue;
                }
                $this->inventoryModel->reverseCredit(
                    $item['item_name'],
                    $item['unit'],
                    $effectiveProjectId ?: null,
                    (float) $item['qty_received'],
                    'goods_receipt',
                    $id,
                    currentUserId(),
                    $receipt['stock_scope'] ?? 'proyek'
                );
            }

            $this->receiptModel->deleteById($id);
            if ($receipt['purchase_order_id']) {
                $this->poModel->refreshReceiptStatus((int) $receipt['purchase_order_id']);
                $this->historyModel->log(
                    (int) $receipt['purchase_order_id'],
                    'goods_receipt_deleted',
                    "Penerimaan barang {$receipt['receipt_number']} dihapus",
                    currentUserId()
                );
            } elseif ($receipt['offline_purchase_id']) {
                $this->offlinePurchaseModel->refreshReceiptStatus((int) $receipt['offline_purchase_id']);
                $this->activityLog->log(
                    currentUserId(),
                    'goods_receipt',
                    'delete',
                    "Penerimaan barang (Pembelian Offline) {$receipt['receipt_number']} dihapus"
                );
            } else {
                $this->activityLog->log(
                    currentUserId(),
                    'goods_receipt',
                    'delete',
                    "Penerimaan barang (Pemakai) {$receipt['receipt_number']} dihapus"
                );
            }

            $pdo->commit();
            setFlash('success', 'Penerimaan barang berhasil dihapus.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Goods receipt delete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menghapus penerimaan barang.');
        }

        $this->redirect('goods_receipt', 'index');
    }

    /**
     * Cetak dokumen penerimaan barang (requirement #7, terutama untuk alur "by User"
     * / Pemakai) -- dilengkapi tanda tangan "Diterima Oleh" dari Master Tanda Tangan.
     */
    public function print()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $receipt = $this->receiptModel->findWithRelations($id);

        if (!$receipt) {
            setFlash('error', 'Data penerimaan barang tidak ditemukan.');
            $this->redirect('goods_receipt', 'index');
        }

        $items = $this->receiptItemModel->itemsByReceipt($id);

        // Cocokkan nama penerima dengan Master Tanda Tangan aktif (kalau ada) supaya
        // gambar tanda tangannya ikut tampil -- tidak wajib, tetap tercetak walau kosong.
        $receiverSignature = null;
        $receiverName = $receipt['received_by_name'] ?? '';
        foreach ($this->signatureModel->activeList() as $sig) {
            if ($receiverName !== '' && mb_strtolower(trim($sig['name'])) === mb_strtolower(trim($receiverName))) {
                $receiverSignature = $sig;
                break;
            }
        }

        $this->view('goods_receipt/print', [
            'pageTitle'         => 'Cetak Penerimaan Barang',
            'receipt'           => $receipt,
            'items'             => $items,
            'receiverSignature' => $receiverSignature,
            'statusLabels'      => $this->receiptItemModel->statusLabels,
        ]);
    }

    /**
     * AJAX: ambil daftar item PO (sisa qty) untuk PO tertentu -- dipanggil saat
     * user memilih PO di dropdown form tambah penerimaan.
     */
    public function ajaxPoItems()
    {
        $poId = (int) ($_GET['po_id'] ?? 0);
        $po = $this->poModel->find($poId);

        if (!$po) {
            $this->json(['error' => 'PO tidak ditemukan'], 404);
        }

        $items = $this->receiptItemModel->poItemsForReceipt($poId);

        ob_start();
        foreach ($items as $index => $poItem) {
            include ROOT_PATH . '/app/views/goods_receipt/_item_row.php';
        }
        $html = ob_get_clean();

        $this->json(['html' => $html, 'count' => count($items)]);
    }

    /**
     * AJAX: ambil daftar item Pembelian Offline (sisa qty) untuk pembelian tertentu --
     * dipanggil saat user memilih Pembelian Offline di dropdown form tambah penerimaan.
     * Meniru persis ajaxPoItems().
     */
    public function ajaxOfflinePurchaseItems()
    {
        $offlinePurchaseId = (int) ($_GET['offline_purchase_id'] ?? 0);
        $offlinePurchase = $this->offlinePurchaseModel->find($offlinePurchaseId);

        if (!$offlinePurchase) {
            $this->json(['error' => 'Pembelian offline tidak ditemukan'], 404);
        }

        $items = $this->receiptItemModel->offlineItemsForReceipt($offlinePurchaseId);

        ob_start();
        foreach ($items as $index => $offlineItem) {
            include ROOT_PATH . '/app/views/goods_receipt/_offline_item_row.php';
        }
        $html = ob_get_clean();

        $this->json(['html' => $html, 'count' => count($items)]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        $receiptType = $_POST['receipt_type'] ?? 'purchase_order';
        if (!in_array($receiptType, ['purchase_order', 'pemakai', 'offline_purchase'], true)) {
            $receiptType = 'purchase_order';
        }

        if ($receiptType === 'purchase_order') {
            $items = $this->collectPoItems();
        } elseif ($receiptType === 'offline_purchase') {
            $items = $this->collectOfflineItems();
        } else {
            $items = $this->collectPemakaiItems();
        }
        $isSourced = $receiptType === 'purchase_order' || $receiptType === 'offline_purchase';
        $mismatchItems = $isSourced ? $this->collectMismatchItems() : [];

        $stockScope = $receiptType === 'pemakai'
            ? (($_POST['stock_scope'] ?? 'proyek') === 'kantor' ? 'kantor' : 'proyek')
            : 'proyek'; // penerimaan dari PO/Pembelian Offline selalu terikat project

        return [
            'purchase_order_id'   => (int) ($_POST['purchase_order_id'] ?? 0),
            'offline_purchase_id' => (int) ($_POST['offline_purchase_id'] ?? 0),
            'receipt_type'      => $receiptType,
            'stock_scope'       => $stockScope,
            'project_id'        => (int) ($_POST['project_id'] ?? 0),
            'source_detail'     => trim($_POST['source_detail'] ?? ''),
            'receipt_date'      => $_POST['receipt_date'] ?? '',
            'receiver_name'     => trim($_POST['receiver_name'] ?? ''),
            'notes'             => trim($_POST['notes'] ?? ''),
            'items'             => $items,
            'mismatchItems'     => $mismatchItems,
        ];
    }

    /**
     * Item dari alur PO: qty_received[] dipasangkan dengan po_item_id[] (murni
     * perbandingan qty terhadap barang PO yang sama).
     */
    private function collectPoItems(): array
    {
        $poItemIds = $_POST['po_item_id'] ?? [];
        $qtys = $_POST['qty_received'] ?? [];

        $items = [];
        foreach ($poItemIds as $i => $poItemId) {
            $poItemId = (int) $poItemId;
            $qty = (float) ($qtys[$i] ?? 0);
            if ($poItemId <= 0 || $qty <= 0) {
                continue; // baris tanpa input qty diabaikan (barang tsb belum datang di penerimaan ini)
            }
            $items[] = ['po_item_id' => $poItemId, 'qty_received' => $qty];
        }
        return $items;
    }

    /**
     * Item dari alur Pembelian Offline: qty_received[] dipasangkan dengan
     * offline_item_id[] -- meniru persis collectPoItems().
     */
    private function collectOfflineItems(): array
    {
        $offlineItemIds = $_POST['offline_item_id'] ?? [];
        $qtys = $_POST['qty_received'] ?? [];

        $items = [];
        foreach ($offlineItemIds as $i => $offlineItemId) {
            $offlineItemId = (int) $offlineItemId;
            $qty = (float) ($qtys[$i] ?? 0);
            if ($offlineItemId <= 0 || $qty <= 0) {
                continue;
            }
            $items[] = ['offline_item_id' => $offlineItemId, 'qty_received' => $qty];
        }
        return $items;
    }

    /**
     * Barang yang datang tapi TIDAK sesuai/tidak tercantum di PO ini sama sekali
     * (requirement #3) -- baris terpisah dari tabel item PO, bukan override inline,
     * supaya perbandingan qty PO tetap murni per barang PO. Selalu ditandai
     * Barang Lain; tetap terhubung ke PO lewat goods_receipt_id -> purchase_order_id.
     */
    private function collectMismatchItems(): array
    {
        $names = $_POST['mismatch_item_name'] ?? [];
        $units = $_POST['mismatch_unit'] ?? [];
        $qtys  = $_POST['mismatch_qty'] ?? [];

        $items = [];
        foreach ($names as $i => $name) {
            $name = trim($name);
            $qty = (float) ($qtys[$i] ?? 0);
            if ($name === '' || $qty <= 0) {
                continue;
            }
            $items[] = [
                'actual_item_name' => $name,
                'actual_unit'      => trim($units[$i] ?? ''),
                'qty_received'     => $qty,
            ];
        }
        return $items;
    }

    /**
     * Item dari alur Penerimaan dari Pemakai/Internal: tidak ada PO untuk dibandingkan,
     * barang & qty diinput langsung oleh gudang (requirement #9).
     */
    private function collectPemakaiItems(): array
    {
        $names = $_POST['pemakai_item_name'] ?? [];
        $units = $_POST['pemakai_unit'] ?? [];
        $qtys  = $_POST['pemakai_qty'] ?? [];

        $items = [];
        foreach ($names as $i => $name) {
            $name = trim($name);
            $qty = (float) ($qtys[$i] ?? 0);
            if ($name === '' || $qty <= 0) {
                continue;
            }
            $items[] = [
                'actual_item_name' => $name,
                'actual_unit'      => trim($units[$i] ?? ''),
                'qty_received'     => $qty,
            ];
        }
        return $items;
    }

    private function validateInput(array $data, ?int $excludeReceiptId = null): array
    {
        $errors = [];

        if (empty($data['receipt_date'])) {
            $errors[] = 'Tanggal penerimaan wajib diisi.';
        }
        if ($data['receiver_name'] === '') {
            $errors[] = 'Nama penerima wajib diisi.';
        }

        if ($data['receipt_type'] === 'purchase_order') {
            if ($data['purchase_order_id'] <= 0) {
                $errors[] = 'Purchase Order wajib dipilih.';
            }
            if (empty($data['items']) && empty($data['mismatchItems'])) {
                $errors[] = 'Minimal harus ada 1 item dengan qty diterima lebih dari 0 (item PO atau barang tidak sesuai).';
            }
        } elseif ($data['receipt_type'] === 'offline_purchase') {
            if ($data['offline_purchase_id'] <= 0) {
                $errors[] = 'Pembelian Offline wajib dipilih.';
            }
            if (empty($data['items']) && empty($data['mismatchItems'])) {
                $errors[] = 'Minimal harus ada 1 item dengan qty diterima lebih dari 0 (item Pembelian Offline atau barang tidak sesuai).';
            }
        } else {
            if ($data['project_id'] <= 0 && $data['stock_scope'] === 'proyek') {
                $errors[] = 'Project wajib dipilih untuk penerimaan kategori Stok Proyek.';
            }
            if ($data['source_detail'] === '') {
                $errors[] = 'Nama pemakai/departemen sumber barang wajib diisi.';
            }
            if (empty($data['items'])) {
                $errors[] = 'Minimal harus ada 1 item barang dengan qty lebih dari 0.';
            }
        }

        return $errors;
    }

    /**
     * Simpan item-item penerimaan + tentukan status banding otomatis (sesuai/kurang/
     * lebih) untuk alur PO, atau langsung 'sesuai' untuk alur Pemakai (tidak ada PO
     * untuk dibandingkan). PENTING: stok TIDAK dikreditkan di sini -- stok baru masuk
     * ke inventory saat item ini DIVALIDASI (lihat ValidationController::validateItem()),
     * supaya barang yang belum/tidak lolos validasi tidak pernah dianggap stok valid.
     */
    private function saveReceiptItems(int $receiptId, array $data): void
    {
        if ($data['receipt_type'] === 'purchase_order') {
            $poItemDetails = $this->receiptItemModel->poItemsForReceipt((int) $data['purchase_order_id'], $receiptId);
            $detailByPoItemId = [];
            foreach ($poItemDetails as $detail) {
                $detailByPoItemId[$detail['po_item_id']] = $detail;
            }

            foreach ($data['items'] as $item) {
                $detail = $detailByPoItemId[$item['po_item_id']] ?? null;
                if (!$detail) {
                    continue;
                }

                $totalAfter = $detail['qty_received_before'] + $item['qty_received'];
                $status = $this->receiptItemModel->resolveComparisonStatus($detail['qty_order'], $totalAfter);

                $this->receiptItemModel->create([
                    'goods_receipt_id'       => $receiptId,
                    'purchase_order_item_id' => $item['po_item_id'],
                    'is_item_mismatch'       => 0,
                    'qty_received'           => $item['qty_received'],
                    'comparison_status'      => $status,
                    'created_by'             => currentUserId(),
                ]);
            }

            $this->saveMismatchItems($receiptId, $data['mismatchItems'] ?? []);
        } elseif ($data['receipt_type'] === 'offline_purchase') {
            $offlineItemDetails = $this->receiptItemModel->offlineItemsForReceipt((int) $data['offline_purchase_id'], $receiptId);
            $detailByOfflineItemId = [];
            foreach ($offlineItemDetails as $detail) {
                $detailByOfflineItemId[$detail['offline_item_id']] = $detail;
            }

            foreach ($data['items'] as $item) {
                $detail = $detailByOfflineItemId[$item['offline_item_id']] ?? null;
                if (!$detail) {
                    continue;
                }

                $totalAfter = $detail['qty_received_before'] + $item['qty_received'];
                $status = $this->receiptItemModel->resolveComparisonStatus($detail['qty_order'], $totalAfter);

                $this->receiptItemModel->create([
                    'goods_receipt_id'         => $receiptId,
                    'offline_purchase_item_id' => $item['offline_item_id'],
                    'is_item_mismatch'         => 0,
                    'qty_received'             => $item['qty_received'],
                    'comparison_status'        => $status,
                    'created_by'               => currentUserId(),
                ]);
            }

            $this->saveMismatchItems($receiptId, $data['mismatchItems'] ?? []);
        } else {
            foreach ($data['items'] as $item) {
                $this->receiptItemModel->create([
                    'goods_receipt_id'  => $receiptId,
                    'actual_item_name'  => $item['actual_item_name'],
                    'actual_unit'       => $item['actual_unit'],
                    'is_item_mismatch'  => 0,
                    'qty_received'      => $item['qty_received'],
                    'comparison_status' => 'sesuai', // tidak ada PO/Pembelian Offline untuk dibandingkan
                    'created_by'        => currentUserId(),
                ]);
            }
        }
    }

    /**
     * Barang yang datang tapi tidak sesuai/tidak tercantum di PO atau Pembelian
     * Offline sama sekali -- baris terpisah, tidak terikat ke item manapun,
     * selalu ditandai Barang Lain. Dipakai bareng oleh alur PO & Pembelian Offline.
     */
    private function saveMismatchItems(int $receiptId, array $mismatchItems): void
    {
        foreach ($mismatchItems as $item) {
            $this->receiptItemModel->create([
                'goods_receipt_id'       => $receiptId,
                'purchase_order_item_id' => null,
                'offline_purchase_item_id' => null,
                'actual_item_name'       => $item['actual_item_name'],
                'actual_unit'            => $item['actual_unit'],
                'is_item_mismatch'       => 1,
                'qty_received'           => $item['qty_received'],
                'comparison_status'      => 'barang_lain',
                'created_by'             => currentUserId(),
            ]);
        }
    }

    /**
     * Upload multi-foto surat jalan (bisa lebih dari 1 file)
     */
    private function saveDeliveryDocuments(int $receiptId): void
    {
        if (empty($_FILES['delivery_documents']) || empty($_FILES['delivery_documents']['name'][0])) {
            return;
        }

        $files = $_FILES['delivery_documents'];
        $count = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $singleFile = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];

            // Pakai $_FILES sementara supaya bisa memanfaatkan handleFileUpload() yang sudah ada
            $originalFiles = $_FILES['delivery_documents'] ?? null;
            $_FILES['delivery_documents_single'] = $singleFile;

            try {
                $path = handleFileUpload('delivery_documents_single', 'surat_jalan', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);
                if ($path) {
                    $this->documentModel->create([
                        'goods_receipt_id' => $receiptId,
                        'document_number'  => trim($_POST['document_number'] ?? '') ?: null,
                        'file_path'        => $path,
                        'created_by'       => currentUserId(),
                    ]);
                }
            } catch (RuntimeException $e) {
                error_log("Gagal upload surat jalan index {$i}: " . $e->getMessage());
            }

            unset($_FILES['delivery_documents_single']);
            if ($originalFiles !== null) {
                $_FILES['delivery_documents'] = $originalFiles;
            }
        }
    }
}
