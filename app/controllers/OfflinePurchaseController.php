<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/OfflinePurchase.php';
require_once ROOT_PATH . '/app/models/OfflinePurchaseItem.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/GoodsReceipt.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class OfflinePurchaseController extends Controller
{
    private OfflinePurchase $purchaseModel;
    private OfflinePurchaseItem $itemModel;
    private Project $projectModel;
    private User $userModel;
    private Item $barangModel;
    private ItemCategory $itemCategoryModel;
    private Unit $unitModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('offline_purchase', 'view');

        $this->purchaseModel = new OfflinePurchase();
        $this->itemModel     = new OfflinePurchaseItem();
        $this->projectModel  = new Project();
        $this->userModel     = new User();
        $this->barangModel   = new Item();
        $this->itemCategoryModel = new ItemCategory();
        $this->unitModel     = new Unit();
        $this->activityLog   = new ActivityLog();
    }

    public function index()
    {
        $filters = [
            'project_id' => $_GET['project_id'] ?? '',
            'status'     => $_GET['status'] ?? '',
            'keyword'    => $_GET['keyword'] ?? '',
        ];

        $purchases = $this->purchaseModel->listWithRelations($filters);
        $projects = $this->projectModel->activeList();

        $this->view('offline_purchase/list', [
            'pageTitle'        => 'Pembelian Offline',
            'purchases'        => $purchases,
            'projects'         => $projects,
            'filters'          => $filters,
            'statusLabels'     => $this->purchaseModel->statusLabels,
            'statusBadgeClass' => $this->purchaseModel->statusBadgeClass,
        ]);
    }

    public function detail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $purchase = $this->purchaseModel->findWithRelations($id);

        if (!$purchase) {
            setFlash('error', 'Data pembelian offline tidak ditemukan.');
            $this->redirect('offline_purchase', 'index');
        }

        $items = $this->itemModel->itemsByPurchase($id);
        $receipts = (new GoodsReceipt())->byOfflinePurchase($id);

        $this->view('offline_purchase/detail', [
            'pageTitle'        => 'Detail Pembelian Offline',
            'purchase'         => $purchase,
            'items'            => $items,
            'receipts'         => $receipts,
            'statusLabels'     => $this->purchaseModel->statusLabels,
            'statusBadgeClass' => $this->purchaseModel->statusBadgeClass,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('offline_purchase', 'create');
        $this->view('offline_purchase/form', [
            'pageTitle'      => 'Tambah Pembelian Offline',
            'mode'           => 'create',
            'purchase'       => null,
            'items'          => [],
            'purchaseNumber' => $this->purchaseModel->previewPurchaseNumber(),
            'projects'       => $this->projectModel->activeList(),
            'picUsers'       => $this->userModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'units'          => $this->unitModel->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('offline_purchase', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offline_purchase', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('offline_purchase', 'create');
        }

        assertPeriodOpen('offline_purchase', $data['purchase_date'], 'offline_purchase', 'create');

        try {
            $proofFile = handleFileUpload('proof_file', 'bukti_pembelian', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);
            $photoFile = handleFileUpload('photo_file', 'foto_barang', ['jpg', 'jpeg', 'png', 'webp'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('offline_purchase', 'create');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $purchaseId = $this->purchaseModel->create([
                'purchase_number' => $this->purchaseModel->generatePurchaseNumber(),
                'project_id'      => $data['project_id'],
                'supplier_name'   => $data['supplier_name'],
                'purchase_date'   => $data['purchase_date'],
                'notes'           => $data['notes'],
                'proof_file'      => $proofFile,
                'photo_file'      => $photoFile,
                'created_by'      => currentUserId(),
            ]);

            $this->saveItems($purchaseId, $data['items']);
            $this->purchaseModel->recalculateTotal($purchaseId);

            $this->activityLog->log(
                currentUserId(),
                'offline_purchase',
                'create',
                "Pembelian offline {$this->purchaseModel->find($purchaseId)['purchase_number']} dari {$data['supplier_name']} dicatat ("
                    . count($data['items']) . ' item)'
            );

            $pdo->commit();

            setFlash('success', 'Pembelian offline berhasil disimpan.');
            $this->redirect('offline_purchase', 'detail', ['id' => $purchaseId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Offline purchase store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan pembelian offline. Silakan coba lagi.');
            $this->redirect('offline_purchase', 'create');
        }
    }

    public function edit()
    {
        Middleware::requirePermission('offline_purchase', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $purchase = $this->purchaseModel->findWithRelations($id);

        if (!$purchase) {
            setFlash('error', 'Data pembelian offline tidak ditemukan.');
            $this->redirect('offline_purchase', 'index');
        }

        $this->view('offline_purchase/form', [
            'pageTitle'      => 'Edit Pembelian Offline',
            'mode'           => 'edit',
            'purchase'       => $purchase,
            'items'          => $this->itemModel->itemsByPurchase($id),
            'purchaseNumber' => $purchase['purchase_number'],
            'projects'       => $this->projectModel->activeList(),
            'picUsers'       => $this->userModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'units'          => $this->unitModel->activeList(),
            'itemsLocked'    => $this->itemModel->hasReceipts($id),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('offline_purchase', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offline_purchase', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->purchaseModel->find($id);

        if (!$existing) {
            setFlash('error', 'Data pembelian offline tidak ditemukan.');
            $this->redirect('offline_purchase', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('offline_purchase', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('offline_purchase', $existing['purchase_date'], 'offline_purchase', 'edit', ['id' => $id]);
        assertPeriodOpen('offline_purchase', $data['purchase_date'], 'offline_purchase', 'edit', ['id' => $id]);

        try {
            $proofFile = handleFileUpload('proof_file', 'bukti_pembelian', ['jpg', 'jpeg', 'png', 'webp', 'pdf'], 5);
            $photoFile = handleFileUpload('photo_file', 'foto_barang', ['jpg', 'jpeg', 'png', 'webp'], 5);
        } catch (RuntimeException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('offline_purchase', 'edit', ['id' => $id]);
        }

        // Pembelian offline yang item-nya sudah punya penerimaan barang TIDAK BOLEH
        // item-nya dihapus/diganti -- FK fk_gri_opi akan menolak DELETE-nya (mirip
        // aturan PurchaseOrderController::update()).
        $itemsLocked = $this->itemModel->hasReceipts($id);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $updateData = [
                'project_id'    => $data['project_id'],
                'supplier_name' => $data['supplier_name'],
                'purchase_date' => $data['purchase_date'],
                'notes'         => $data['notes'],
            ];
            if ($proofFile !== null) {
                $updateData['proof_file'] = $proofFile;
            }
            if ($photoFile !== null) {
                $updateData['photo_file'] = $photoFile;
            }
            $this->purchaseModel->updateById($id, $updateData);

            if (!$itemsLocked) {
                $this->itemModel->deleteByPurchase($id);
                $this->saveItems($id, $data['items']);
                $this->purchaseModel->recalculateTotal($id);
            }

            $this->activityLog->log(
                currentUserId(),
                'offline_purchase',
                'update',
                "Pembelian offline {$existing['purchase_number']} diperbarui"
            );

            $pdo->commit();

            if ($itemsLocked) {
                setFlash('success', 'Data pembelian offline diperbarui. Daftar item TIDAK diubah karena sudah punya penerimaan barang.');
            } else {
                setFlash('success', 'Pembelian offline berhasil diperbarui.');
            }
            $this->redirect('offline_purchase', 'detail', ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Offline purchase update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui pembelian offline.');
            $this->redirect('offline_purchase', 'edit', ['id' => $id]);
        }
    }

    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offline_purchase', 'index');
        }
        verifyCsrf();

        Middleware::requirePermission('offline_purchase', 'delete');

        $id = (int) ($_POST['id'] ?? 0);
        $purchase = $this->purchaseModel->find($id);

        if (!$purchase) {
            setFlash('error', 'Data pembelian offline tidak ditemukan.');
            $this->redirect('offline_purchase', 'index');
        }

        if ($this->itemModel->hasReceipts($id)) {
            setFlash('error', 'Pembelian offline ini sudah punya penerimaan barang dan tidak bisa dihapus.');
            $this->redirect('offline_purchase', 'detail', ['id' => $id]);
        }

        assertPeriodOpen('offline_purchase', $purchase['purchase_date'], 'offline_purchase', 'index');
        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true ? 'Pembelian offline berhasil dihapus.' : 'Gagal menghapus pembelian offline.');

        $this->redirect('offline_purchase', 'index');
    }

    /**
     * Hapus 1 pembelian offline ke Tempat Sampah. true = sukses, string = alasan skip.
     * Dipakai delete() & rangeDelete().
     */
    private function deleteOneRecord(int $id)
    {
        $purchase = $this->purchaseModel->find($id);
        if (!$purchase) {
            return 'gagal';
        }
        // Soft-delete ke Tempat Sampah aman walau sudah ada penerimaan / periode
        // terkunci (cuma set deleted_at). Gerbang hasReceipts & Tutup Bulan tetap
        // berlaku untuk hapus per-baris lewat delete().
        try {
            $this->purchaseModel->deleteById($id);
            $this->activityLog->log(
                currentUserId(),
                'offline_purchase',
                'delete',
                "Pembelian offline {$purchase['purchase_number']} dihapus"
            );
            return true;
        } catch (Throwable $e) {
            error_log('OfflinePurchase deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua pembelian offline dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('offline_purchase', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('offline_purchase', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->purchaseModel->idsByDateRange('purchase_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('offline_purchase', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('offline_purchase', 'index');
    }

    /**
     * AJAX: kembalikan HTML satu baris form item baru (dipanggil dari tombol "+ Tambah Barang")
     */
    public function ajaxItemRow()
    {
        Middleware::requirePermission('offline_purchase', 'create');

        $index = (int) ($_GET['index'] ?? 0);
        $itemCatalog = $this->barangModel->activeList();
        ob_start();
        include ROOT_PATH . '/app/views/offline_purchase/_item_row.php';
        $html = ob_get_clean();

        $this->json(['html' => $html]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        $items = [];
        $names = $_POST['item_name'] ?? [];
        $units = $_POST['unit'] ?? [];
        $qtys  = $_POST['qty'] ?? [];
        $prices = $_POST['price'] ?? [];
        $itemIds = $_POST['item_id'] ?? [];

        foreach ($names as $i => $name) {
            $name = trim($name);
            if ($name === '') {
                continue; // baris kosong diabaikan
            }
            $qty = (float) ($qtys[$i] ?? 0);
            $price = parseCurrencyInput($prices[$i] ?? 0);
            $items[] = [
                'item_id'   => !empty($itemIds[$i]) ? (int) $itemIds[$i] : null,
                'item_name' => $name,
                'unit'      => trim($units[$i] ?? ''),
                'qty'       => $qty,
                'price'     => $price,
                'subtotal'  => $qty * $price,
            ];
        }

        return [
            'project_id'    => (int) ($_POST['project_id'] ?? 0),
            'supplier_name' => trim($_POST['supplier_name'] ?? ''),
            'purchase_date' => $_POST['purchase_date'] ?? '',
            'notes'         => trim($_POST['notes'] ?? ''),
            'items'         => $items,
        ];
    }

    private function validateInput(array $data): array
    {
        $errors = [];

        if ($data['project_id'] <= 0) {
            $errors[] = 'Project wajib dipilih.';
        }
        if ($data['supplier_name'] === '') {
            $errors[] = 'Nama supplier wajib diisi.';
        }
        if (empty($data['purchase_date'])) {
            $errors[] = 'Tanggal pembelian wajib diisi.';
        }
        if (empty($data['items'])) {
            $errors[] = 'Minimal harus ada 1 item barang.';
        }
        foreach ($data['items'] as $item) {
            if ($item['unit'] === '') {
                $errors[] = "Satuan untuk item '{$item['item_name']}' wajib diisi.";
            }
            if ($item['qty'] <= 0) {
                $errors[] = "Qty untuk item '{$item['item_name']}' harus lebih dari 0.";
            }
            if ($item['price'] <= 0) {
                $errors[] = "Harga untuk item '{$item['item_name']}' harus lebih dari 0.";
            }
        }

        return $errors;
    }

    private function saveItems(int $purchaseId, array $items): void
    {
        foreach ($items as $item) {
            $this->itemModel->create([
                'offline_purchase_id' => $purchaseId,
                'item_id'   => $item['item_id'] ?? null,
                'item_name' => $item['item_name'],
                'unit'      => $item['unit'],
                'qty'       => $item['qty'],
                'price'     => $item['price'],
                'subtotal'  => $item['subtotal'],
                'created_by' => currentUserId(),
            ]);
        }
    }
}
