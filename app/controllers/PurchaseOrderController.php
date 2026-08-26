<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderItem.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderHistory.php';
require_once ROOT_PATH . '/app/models/Supplier.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/Warehouse.php';
require_once ROOT_PATH . '/app/models/Payment.php';
require_once ROOT_PATH . '/app/models/Signature.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';

class PurchaseOrderController extends Controller
{
    private PurchaseOrder $poModel;
    private PurchaseOrderItem $itemModel;
    private PurchaseOrderHistory $historyModel;
    private Supplier $supplierModel;
    private Project $projectModel;
    private User $userModel;
    private Item $barangModel;
    private ItemCategory $itemCategoryModel;
    private Unit $unitModel;
    private Warehouse $warehouseModel;
    private Payment $paymentModel;
    private Signature $signatureModel;

    public function __construct()
    {
        // Project Manager cuma boleh lihat (index/detail) -- create/edit/delete
        // dijaga per-action di bawah supaya PM otomatis kena 403 kalau mencoba.
        Middleware::requirePermission('purchase_order', 'view');

        $this->poModel      = new PurchaseOrder();
        $this->itemModel    = new PurchaseOrderItem();
        $this->historyModel = new PurchaseOrderHistory();
        $this->supplierModel = new Supplier();
        $this->projectModel  = new Project();
        $this->userModel      = new User();
        $this->barangModel       = new Item();
        $this->itemCategoryModel = new ItemCategory();
        $this->unitModel         = new Unit();
        $this->warehouseModel    = new Warehouse();
        $this->paymentModel      = new Payment();
        $this->signatureModel    = new Signature();
    }

    /**
     * List semua PO + filter status/project/keyword
     */
    public function index()
    {
        $filters = [
            'status'     => $_GET['status'] ?? '',
            'project_id' => $_GET['project_id'] ?? '',
            'keyword'    => $_GET['keyword'] ?? '',
        ];

        $purchaseOrders = $this->poModel->listWithRelations($filters);
        $projects = $this->projectModel->activeList();

        $this->view('purchase_order/list', [
            'pageTitle'      => 'Purchase Order',
            'purchaseOrders' => $purchaseOrders,
            'projects'       => $projects,
            'filters'        => $filters,
            'statusLabels'   => $this->poModel->statusLabels,
            'statusBadgeClass' => $this->poModel->statusBadgeClass,
        ]);
    }

    /**
     * Detail satu PO: item-item + history perubahan
     */
    public function detail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $po = $this->poModel->findWithRelations($id);

        if (!$po) {
            setFlash('error', 'Purchase Order tidak ditemukan.');
            $this->redirect('purchase_order', 'index');
        }

        $items = $this->itemModel->itemsByPo($id);
        $history = $this->historyModel->timelineByPo($id);
        $paymentInfo = $this->paymentModel->poPaymentInfo($id, (float) $po['total_amount']);

        $this->view('purchase_order/detail', [
            'pageTitle' => 'Detail PO',
            'po'        => $po,
            'items'     => $items,
            'history'   => $history,
            'paymentInfo' => $paymentInfo,
            'statusLabels'     => $this->poModel->statusLabels,
            'statusBadgeClass' => $this->poModel->statusBadgeClass,
            'paymentStatusLabels'     => $this->paymentModel->statusLabels,
            'paymentStatusBadgeClass' => $this->paymentModel->statusBadgeClass,
        ]);
    }

    /**
     * Form tambah PO baru
     */
    public function create()
    {
        Middleware::requirePermission('purchase_order', 'create');

        $this->view('purchase_order/form', [
            'pageTitle'   => 'Tambah Purchase Order',
            'mode'        => 'create',
            'po'          => null,
            'items'       => [],
            'poNumber'    => $this->poModel->generatePoNumber(),
            'suppliers'   => $this->supplierModel->activeList(),
            'projects'    => $this->projectModel->activeList(),
            'picUsers'    => $this->userModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'units'          => $this->unitModel->activeList(),
            'warehouses'     => $this->warehouseModel->activeList(),
            'signatures'     => $this->signatureModel->activeList(),
        ]);
    }

    /**
     * Simpan PO baru + item-itemnya (transaksi DB)
     */
    public function store()
    {
        Middleware::requirePermission('purchase_order', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('purchase_order', 'create');
        }
        verifyCsrf();

        $data = $this->collectPoInput();
        $errors = $this->validatePoInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('purchase_order', 'create');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $poId = $this->poModel->create([
                'po_number'   => $this->poModel->generatePoNumber(),
                'supplier_id' => $data['supplier_id'],
                'project_id'  => $data['project_id'],
                'delivery_location_id' => $data['delivery_location_id'] ?: null,
                'po_date'     => $data['po_date'],
                'status'      => $data['status'],
                'notes'       => $data['notes'],
                'pembuat_po'  => $data['pembuat_po'],
                'signature_id' => $data['signature_id'],
                'quote_number' => $data['quote_number'],
                'quote_date'   => $data['quote_date'],
                'created_by'  => currentUserId(),
            ]);

            $this->saveItems($poId, $data['items']);
            $this->poModel->recalculateTotal($poId);

            $this->historyModel->log($poId, 'created', 'Purchase Order dibuat', currentUserId());

            $pdo->commit();

            setFlash('success', 'Purchase Order berhasil dibuat.');
            $this->redirect('purchase_order', 'detail', ['id' => $poId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('PO store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Purchase Order. Silakan coba lagi.');
            $this->redirect('purchase_order', 'create');
        }
    }

    /**
     * Form edit PO
     */
    public function edit()
    {
        Middleware::requirePermission('purchase_order', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $po = $this->poModel->findWithRelations($id);

        if (!$po) {
            setFlash('error', 'Purchase Order tidak ditemukan.');
            $this->redirect('purchase_order', 'index');
        }

        $this->view('purchase_order/form', [
            'pageTitle' => 'Edit Purchase Order',
            'mode'      => 'edit',
            'po'        => $po,
            'items'     => $this->itemModel->itemsByPo($id),
            'poNumber'  => $po['po_number'],
            'suppliers' => $this->supplierModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'picUsers'  => $this->userModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'units'          => $this->unitModel->activeList(),
            'warehouses'     => $this->warehouseModel->activeList(),
            'signatures'     => $this->signatureModel->activeList(),
        ]);
    }

    /**
     * Update PO + replace seluruh item (hapus lama, insert baru)
     */
    public function update()
    {
        Middleware::requirePermission('purchase_order', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('purchase_order', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->poModel->find($id);

        if (!$existing) {
            setFlash('error', 'Purchase Order tidak ditemukan.');
            $this->redirect('purchase_order', 'index');
        }

        $data = $this->collectPoInput();
        $errors = $this->validatePoInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('purchase_order', 'edit', ['id' => $id]);
        }

        // PO yang item-nya sudah punya penerimaan barang TIDAK BOLEH item-nya
        // dihapus/diganti -- FK fk_gri_poi akan menolak DELETE-nya (goods_receipt_items
        // masih menunjuk ke baris itu). Kalau sudah ada penerimaan, kunci daftar item:
        // hanya data umum PO (supplier/project/tanggal/status/catatan) yang diperbarui.
        $itemsLocked = $this->itemModel->hasReceipts($id);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $statusChanged = $existing['status'] !== $data['status'];

            $this->poModel->updateById($id, [
                'supplier_id' => $data['supplier_id'],
                'project_id'  => $data['project_id'],
                'delivery_location_id' => $data['delivery_location_id'] ?: null,
                'po_date'     => $data['po_date'],
                'status'      => $data['status'],
                'notes'       => $data['notes'],
                'pembuat_po'  => $data['pembuat_po'],
                'signature_id' => $data['signature_id'],
                'quote_number' => $data['quote_number'],
                'quote_date'   => $data['quote_date'],
            ]);

            if (!$itemsLocked) {
                $this->itemModel->deleteByPo($id);
                $this->saveItems($id, $data['items']);
                $this->poModel->recalculateTotal($id);
            }

            $this->historyModel->log($id, 'updated', 'Data Purchase Order & item diperbarui', currentUserId());
            if ($statusChanged) {
                $this->historyModel->log(
                    $id,
                    'status_changed',
                    "Status diubah dari '{$existing['status']}' menjadi '{$data['status']}'",
                    currentUserId()
                );
            }

            $pdo->commit();

            if ($itemsLocked) {
                setFlash('success', 'Data Purchase Order diperbarui. Daftar item TIDAK diubah karena PO ini sudah punya penerimaan barang.');
            } else {
                setFlash('success', 'Purchase Order berhasil diperbarui.');
            }
            $this->redirect('purchase_order', 'detail', ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('PO update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Purchase Order.');
            $this->redirect('purchase_order', 'edit', ['id' => $id]);
        }
    }

    /**
     * Soft delete PO
     */
    public function delete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('purchase_order', 'index');
        }
        verifyCsrf();

        Middleware::requirePermission('purchase_order', 'delete');

        $id = (int) ($_POST['id'] ?? 0);
        $po = $this->poModel->find($id);

        if ($po) {
            $this->poModel->deleteById($id);
            $this->historyModel->log($id, 'deleted', 'Purchase Order dihapus (soft delete)', currentUserId());
            setFlash('success', 'Purchase Order berhasil dihapus.');
        } else {
            setFlash('error', 'Purchase Order tidak ditemukan.');
        }

        $this->redirect('purchase_order', 'index');
    }

    /**
     * AJAX: kembalikan HTML satu baris form item baru (dipanggil dari tombol "+ Tambah Item")
     */
    public function ajaxItemRow()
    {
        Middleware::requirePermission('purchase_order', 'create');

        $index = (int) ($_GET['index'] ?? 0);
        $itemCatalog = $this->barangModel->activeList();
        ob_start();
        include ROOT_PATH . '/app/views/purchase_order/_item_row.php';
        $html = ob_get_clean();

        $this->json(['html' => $html]);
    }

    /**
     * Cetak PO -- hanya PO yang dipilih user lewat checkbox di halaman list
     * (requirement #1). ID divalidasi server-side: hanya PO yang benar-benar ada
     * (tidak soft-deleted) yang dicetak; akses modul sendiri sudah dijaga
     * Middleware::requirePermission('purchase_order','view') di constructor.
     */
    public function print()
    {
        $idsParam = $_GET['ids'] ?? [];
        if (is_string($idsParam)) {
            $idsParam = explode(',', $idsParam);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $idsParam), fn($id) => $id > 0)));

        if (empty($ids)) {
            setFlash('error', 'Pilih minimal 1 Purchase Order untuk dicetak.');
            $this->redirect('purchase_order', 'index');
        }

        $purchaseOrders = [];
        foreach ($ids as $id) {
            $po = $this->poModel->findWithRelations($id);
            if ($po) { // ID yang tidak ditemukan/sudah dihapus otomatis diabaikan, bukan error fatal
                $po['items'] = $this->itemModel->itemsByPo($id);
                $purchaseOrders[] = $po;
            }
        }

        if (empty($purchaseOrders)) {
            setFlash('error', 'Purchase Order yang dipilih tidak ditemukan.');
            $this->redirect('purchase_order', 'index');
        }

        $this->view('purchase_order/print', [
            'pageTitle'      => 'Cetak Purchase Order',
            'purchaseOrders' => $purchaseOrders,
            'statusLabels'   => $this->poModel->statusLabels,
            'company'        => (new SystemSetting())->getGroup('company'),
        ]);
    }

    // ================= Helper privat =================

    private function collectPoInput(): array
    {
        $items = [];
        $names = $_POST['item_name'] ?? [];
        $units = $_POST['unit'] ?? [];
        $qtys  = $_POST['qty_order'] ?? [];
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
                'qty_order' => $qty,
                'price'     => $price,
                'subtotal'  => $qty * $price,
            ];
        }

        return [
            'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
            'project_id'  => (int) ($_POST['project_id'] ?? 0),
            'delivery_location_id' => (int) ($_POST['delivery_location_id'] ?? 0),
            'po_date'     => $_POST['po_date'] ?? '',
            'status'      => $_POST['status'] ?? 'draft',
            'notes'       => trim($_POST['notes'] ?? ''),
            'pembuat_po'  => trim($_POST['pembuat_po'] ?? ''),
            'signature_id' => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'quote_number' => trim($_POST['quote_number'] ?? '') ?: null,
            'quote_date'   => trim($_POST['quote_date'] ?? '') ?: null,
            'items'       => $items,
        ];
    }

    private function validatePoInput(array $data): array
    {
        $errors = [];

        if ($data['supplier_id'] <= 0) {
            $errors[] = 'Supplier wajib dipilih.';
        }
        if ($data['project_id'] <= 0) {
            $errors[] = 'Project wajib dipilih.';
        }
        if ($data['pembuat_po'] === '') {
            $errors[] = 'Pembuat PO wajib diisi.';
        }
        if ($data['signature_id'] !== null && !$this->signatureModel->find($data['signature_id'])) {
            $errors[] = 'Tanda tangan yang dipilih tidak valid.';
        }
        if (empty($data['po_date'])) {
            $errors[] = 'Tanggal PO wajib diisi.';
        }
        if (!array_key_exists($data['status'], $this->poModel->statusLabels)) {
            $errors[] = 'Status tidak valid.';
        }
        if (empty($data['items'])) {
            $errors[] = 'Minimal harus ada 1 item barang.';
        }
        foreach ($data['items'] as $item) {
            if ($item['qty_order'] <= 0) {
                $errors[] = "Qty untuk item '{$item['item_name']}' harus lebih dari 0.";
            }
            if ($item['price'] < 0) {
                $errors[] = "Harga untuk item '{$item['item_name']}' tidak boleh negatif.";
            }
        }

        return $errors;
    }

    private function saveItems(int $poId, array $items): void
    {
        foreach ($items as $item) {
            $this->itemModel->create([
                'purchase_order_id' => $poId,
                'item_id'   => $item['item_id'] ?? null,
                'item_name' => $item['item_name'],
                'unit'      => $item['unit'],
                'qty_order' => $item['qty_order'],
                'price'     => $item['price'],
                'subtotal'  => $item['subtotal'],
                'created_by' => currentUserId(),
            ]);
        }
    }
}
