<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderItem.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderExtraCost.php';
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
    private PurchaseOrderExtraCost $extraCostModel;
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
        $this->extraCostModel = new PurchaseOrderExtraCost();
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

        $paidTotals = $this->paymentModel->paidTotalsByPoIds(array_column($purchaseOrders, 'id'));
        foreach ($purchaseOrders as &$po) {
            $paid = $paidTotals[$po['id']] ?? 0.0;
            $po['payment_status'] = $this->paymentModel->resolveStatus((float) $po['total_amount'], $paid);
        }
        unset($po);

        $this->view('purchase_order/list', [
            'pageTitle'      => 'Purchase Order',
            'purchaseOrders' => $purchaseOrders,
            'projects'       => $projects,
            'filters'        => $filters,
            'statusLabels'   => $this->poModel->statusLabels,
            'statusBadgeClass' => $this->poModel->statusBadgeClass,
            'paymentStatusLabels'     => $this->paymentModel->statusLabels,
            'paymentStatusBadgeClass' => $this->paymentModel->statusBadgeClass,
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
        $extraCosts = $this->extraCostModel->itemsByPo($id);
        $history = $this->historyModel->timelineByPo($id);
        $paymentInfo = $this->paymentModel->poPaymentInfo($id, (float) $po['total_amount']);

        $this->view('purchase_order/detail', [
            'pageTitle' => 'Detail PO',
            'po'        => $po,
            'items'     => $items,
            'extraCosts' => $extraCosts,
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
            'extraCosts'  => [],
            'poNumber'    => $this->poModel->previewPoNumber(),
            'suppliers'   => $this->supplierModel->activeList(),
            'projects'    => $this->projectModel->activeList(),
            'receivers'   => $this->poModel->receiverCandidates(),
            'picUsers'    => $this->userModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'units'          => $this->unitModel->activeList(),
            'warehouses'     => $this->warehouseModel->activeList(),
            'mySignature'    => $this->signatureModel->findByUserId((int) currentUserId()),
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

        assertPeriodOpen('purchase_order', $data['po_date'], 'purchase_order', 'create');

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $poNumber = $this->poModel->generatePoNumber();
            $poId = $this->poModel->create([
                'po_number'   => $poNumber,
                'supplier_id' => $data['supplier_id'],
                'project_id'  => $data['project_id'],
                'receiver_user_id' => $data['receiver_user_id'],
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
            $this->saveExtraCosts($poId, $data['extra_costs']);
            $this->poModel->recalculateTotal($poId);

            $this->historyModel->log($poId, 'created', 'Purchase Order dibuat', currentUserId());

            $pdo->commit();

            // Push notification (best-effort, di luar transaction) -- hanya PO yang
            // memang menunggu approval, bukan yang disimpan sebagai Draft.
            if ($data['status'] === 'waiting_approval') {
                try {
                    require_once ROOT_PATH . '/app/models/SystemSetting.php';
                    if ((new SystemSetting())->getBool('notify_po_belum_diproses', true)) {
                        sendPushToModuleViewers(
                            'purchase_order',
                            'PO Menunggu Approval',
                            "PO {$poNumber} membutuhkan persetujuan Anda.",
                            route('purchase_order', 'detail', ['id' => $poId]),
                            // Tidak ada aksi 'approve' terpisah di modul ini -- status PO
                            // (termasuk approve) diubah lewat update(), yang digerbangi
                            // 'edit'. Pakai itu, BUKAN 'view' -- lihat catatan push_helper.php.
                            'edit'
                        );
                    }
                } catch (Throwable $e) {
                    error_log('Push po_belum_diproses gagal: ' . $e->getMessage());
                }
            }

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
        $this->assertApprovalLock($po);

        $this->view('purchase_order/form', [
            'pageTitle' => 'Edit Purchase Order',
            'mode'      => 'edit',
            'po'        => $po,
            'items'     => $this->itemModel->itemsByPo($id),
            'extraCosts' => $this->extraCostModel->itemsByPo($id),
            'poNumber'  => $po['po_number'],
            'suppliers' => $this->supplierModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'receivers' => $this->poModel->receiverCandidates(),
            'picUsers'  => $this->userModel->activeList(),
            'itemCatalog'    => $this->barangModel->activeList(),
            'itemCategories' => $this->itemCategoryModel->activeList(),
            'units'          => $this->unitModel->activeList(),
            'warehouses'     => $this->warehouseModel->activeList(),
            'mySignature'    => $this->signatureModel->findByUserId((int) currentUserId()),
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
        $this->assertApprovalLock($existing);

        $data = $this->collectPoInput();
        $errors = $this->validatePoInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('purchase_order', 'edit', ['id' => $id]);
        }

        // Transisi ke status 'approved' HARUS lewat tombol/action approve() (supaya
        // approved_by/approved_at tercatat & PO benar-benar terkunci) -- form edit
        // biasa tidak boleh dipakai untuk "menyelundupkan" status approved begitu
        // saja, apalagi oleh user yang tidak punya permission approve.
        if ($data['status'] === 'approved' && $existing['status'] !== 'approved' && !can('purchase_order', 'approve')) {
            setFlash('error', "Ubah status PO ke 'Disetujui' harus lewat tombol Setujui PO di halaman Detail, bukan form edit.");
            $this->redirect('purchase_order', 'edit', ['id' => $id]);
        }

        // Tolak kalau tanggal LAMA atau BARU berada di periode yang sudah ditutup.
        assertPeriodOpen('purchase_order', $existing['po_date'], 'purchase_order', 'edit', ['id' => $id]);
        assertPeriodOpen('purchase_order', $data['po_date'], 'purchase_order', 'edit', ['id' => $id]);

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
                'receiver_user_id' => $data['receiver_user_id'],
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
            }
            // Biaya tambahan TIDAK dikunci oleh $itemsLocked -- tidak direferensikan
            // goods_receipt_items, jadi selalu boleh diganti walau daftar barang sudah terkunci.
            $this->saveExtraCosts($id, $data['extra_costs']);
            $this->poModel->recalculateTotal($id);

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
     * Setujui PO -- action terpisah dari update() biasa (bukan sekadar ganti
     * dropdown status), supaya siapa & kapan approve tercatat (approved_by/at)
     * dan PO otomatis terkunci sesudahnya (lihat assertApprovalLock()). Hanya
     * berlaku dari status 'waiting_approval'.
     */
    public function approve()
    {
        Middleware::requirePermission('purchase_order', 'approve');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('purchase_order', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $po = $this->poModel->find($id);

        if (!$po) {
            setFlash('error', 'Purchase Order tidak ditemukan.');
            $this->redirect('purchase_order', 'index');
        }
        if ($po['status'] !== 'waiting_approval') {
            setFlash('error', "PO hanya bisa disetujui dari status 'Menunggu Approval'.");
            $this->redirect('purchase_order', 'detail', ['id' => $id]);
        }

        $this->poModel->updateById($id, [
            'status'      => 'approved',
            'approved_by' => currentUserId(),
            'approved_at' => date('Y-m-d H:i:s'),
        ]);
        $this->historyModel->log($id, 'status_changed', 'PO disetujui oleh ' . currentUserName(), currentUserId());

        setFlash('success', 'Purchase Order berhasil disetujui. Data PO ini sekarang terkunci (hanya Super Admin yang bisa mengubah).');
        $this->redirect('purchase_order', 'detail', ['id' => $id]);
    }

    /**
     * Soft delete PO
     */
    public function delete()
    {
        Middleware::requirePermission('purchase_order', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('purchase_order', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $po = $this->poModel->find($id);

        if (!$po) {
            setFlash('error', 'Purchase Order tidak ditemukan.');
            $this->redirect('purchase_order', 'index');
        }
        $this->assertApprovalLock($po);

        assertPeriodOpen('purchase_order', $po['po_date'], 'purchase_order', 'index');
        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true ? 'Purchase Order berhasil dihapus.' : 'Gagal menghapus Purchase Order.');

        $this->redirect('purchase_order', 'index');
    }

    /**
     * Hapus 1 PO ke Tempat Sampah (dipakai delete() & rangeDelete()).
     * Return true kalau sukses, atau string alasan skip.
     */
    private function deleteOneRecord(int $id)
    {
        $po = $this->poModel->find($id);
        if (!$po) {
            return 'gagal';
        }
        // Soft-delete ke Tempat Sampah aman walau periode terkunci / masih
        // dirujuk dokumen lain (cuma set deleted_at, tidak melanggar FK). Gerbang
        // Tutup Bulan tetap berlaku untuk hapus PER-BARIS lewat delete().
        try {
            $this->poModel->deleteById($id);
            $this->historyModel->log($id, 'deleted', 'Purchase Order dihapus (soft delete)', currentUserId());
            return true;
        } catch (Throwable $e) {
            error_log('PO deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /**
     * Hapus semua PO dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin.
     */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('purchase_order', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('purchase_order', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->poModel->idsByDateRange('po_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('purchase_order', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
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
                $po['extraCosts'] = $this->extraCostModel->itemsByPo($id);
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

    /**
     * PO yang sudah disetujui (approved_at terisi) terkunci -- tidak bisa
     * diedit/dihapus lagi kecuali oleh Super Admin. Pola sama persis dengan
     * CashController::assertValidationAllowsChange() (Validasi Kas).
     */
    private function assertApprovalLock(array $po): void
    {
        if (!empty($po['approved_at']) && currentUserRole() !== ROLE_SUPER_ADMIN) {
            denyAccess("Purchase Order '{$po['po_number']}' sudah disetujui -- tidak bisa diubah/dihapus. Hubungi Super Admin bila perlu koreksi.");
        }
    }

    private function collectPoInput(): array
    {
        $items = [];
        $names = $_POST['item_name'] ?? [];
        $units = $_POST['unit'] ?? [];
        $qtys  = $_POST['qty_order'] ?? [];
        $prices = $_POST['price'] ?? [];
        $itemIds = $_POST['item_id'] ?? [];
        $discounts = $_POST['discount_percent'] ?? [];
        $ppnEnabled = $_POST['ppn_enabled'] ?? []; // checkbox -- hanya index yang dicentang yang terkirim
        $ppnPercents = $_POST['ppn_percent'] ?? [];
        // Kategori: read-only di form, ikut kategori master Barang (data-category).
        // Diperlakukan seperti unit[] -- string snapshot yang dikirim apa adanya,
        // hanya dipakai untuk kolom Kategori di CETAK PO.
        $categories = $_POST['category'] ?? [];

        foreach ($names as $i => $name) {
            $name = trim($name);
            if ($name === '') {
                continue; // baris kosong diabaikan
            }
            $qty = parseQtyInput($qtys[$i] ?? 0);
            $price = parseCurrencyInput($prices[$i] ?? 0);
            $discountPercent = max(0, min(100, parseQtyInput($discounts[$i] ?? 0)));
            $ppnOn = !empty($ppnEnabled[$i]);
            // PPN mati -> selalu null/0, apa pun yang dikirim klien. PPN aktif ->
            // nilai HARUS angka murni (boleh 1 pemisah desimal); yang tidak lolos
            // ditandai ppn_invalid dan ditolak di validatePoInput() (bukan diam-diam
            // dikoreksi), supaya request manual/DevTools tidak bisa menyelundupkan huruf.
            $ppnRaw = trim((string) ($ppnPercents[$i] ?? ''));
            $ppnInvalid = $ppnOn && $ppnRaw !== '' && !preg_match('/^\d{1,3}([.,]\d{1,4})?$/', $ppnRaw);
            $ppnPercent = ($ppnOn && !$ppnInvalid) ? max(0, parseQtyInput($ppnRaw)) : null;

            $afterDiscount = $qty * $price * (1 - $discountPercent / 100);
            $ppnAmount = $ppnOn ? $afterDiscount * ($ppnPercent / 100) : 0;
            $subtotal = $afterDiscount + $ppnAmount;

            $items[] = [
                'item_id'   => !empty($itemIds[$i]) ? (int) $itemIds[$i] : null,
                'item_name' => $name,
                'category'  => trim($categories[$i] ?? '') ?: null,
                'unit'      => trim($units[$i] ?? ''),
                'qty_order' => $qty,
                'price'     => $price,
                'discount_percent' => $discountPercent,
                'ppn_enabled' => $ppnOn,
                'ppn_percent' => $ppnPercent,
                'ppn_invalid' => $ppnInvalid,
                'subtotal'  => $subtotal,
            ];
        }

        $extraCosts = [];
        $costNames = $_POST['extra_cost_name'] ?? [];
        $costAmounts = $_POST['extra_cost_amount'] ?? [];
        foreach ($costNames as $i => $costName) {
            $costName = trim($costName);
            $amount = parseCurrencyInput($costAmounts[$i] ?? 0);
            if ($costName === '' || $amount == 0) {
                continue; // baris kosong/nol diabaikan
            }
            $extraCosts[] = ['cost_name' => $costName, 'amount' => $amount];
        }

        return [
            'supplier_id' => (int) ($_POST['supplier_id'] ?? 0),
            'project_id'  => (int) ($_POST['project_id'] ?? 0),
            // Penerima Barang: HANYA id user (FK users.id); divalidasi di validatePoInput().
            'receiver_user_id' => (int) ($_POST['receiver_user_id'] ?? 0),
            'delivery_location_id' => (int) ($_POST['delivery_location_id'] ?? 0),
            'po_date'     => $_POST['po_date'] ?? '',
            'status'      => $_POST['status'] ?? 'draft',
            'notes'       => trim($_POST['notes'] ?? ''),
            'pembuat_po'  => trim($_POST['pembuat_po'] ?? ''),
            // Signature TIDAK LAGI dipilih manual (Revisi Kas/Bank) -- otomatis
            // dari tanda tangan pribadi user yang login (Profile > Tanda Tangan
            // Saya). $_POST['signature_id'] sengaja tidak dibaca sama sekali.
            // NULL kalau user belum pernah setup tanda tangan pribadi -- cetak
            // jatuh ke fallback placeholder yang sudah ada (lihat print.php).
            'signature_id' => $this->signatureModel->findByUserId((int) currentUserId())['id'] ?? null,
            'quote_number' => trim($_POST['quote_number'] ?? '') ?: null,
            'quote_date'   => trim($_POST['quote_date'] ?? '') ?: null,
            'items'       => $items,
            'extra_costs' => $extraCosts,
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
        if ($data['receiver_user_id'] <= 0) {
            $errors[] = 'Penerima Barang wajib dipilih.';
        } elseif (!$this->poModel->isReceiverCandidate($data['receiver_user_id'])) {
            // Backend tidak percaya dropdown: hanya user aktif ber-role Project/Purchase yang sah.
            $errors[] = 'Penerima Barang tidak valid.';
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
            if (!empty($item['ppn_invalid'])) {
                $errors[] = "PPN untuk item '{$item['item_name']}' harus berupa angka (0-100).";
            } elseif ($item['ppn_enabled'] && $item['ppn_percent'] > 100) {
                $errors[] = "PPN untuk item '{$item['item_name']}' tidak boleh lebih dari 100%.";
            } elseif ($item['ppn_enabled'] && ($item['ppn_percent'] === null || $item['ppn_percent'] <= 0)) {
                $errors[] = "PPN untuk item '{$item['item_name']}' dicentang tapi persentasenya belum diisi.";
            }
        }
        foreach ($data['extra_costs'] as $cost) {
            if ($cost['amount'] < 0) {
                $errors[] = "Jumlah biaya tambahan '{$cost['cost_name']}' tidak boleh negatif.";
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
                'category'  => $item['category'] ?? null,
                'unit'      => $item['unit'],
                'qty_order' => $item['qty_order'],
                'price'     => $item['price'],
                'discount_percent' => $item['discount_percent'] ?? 0,
                'ppn_enabled' => !empty($item['ppn_enabled']) ? 1 : 0,
                'ppn_percent' => $item['ppn_percent'] ?? null,
                'subtotal'  => $item['subtotal'],
                'created_by' => currentUserId(),
            ]);
        }
    }

    private function saveExtraCosts(int $poId, array $extraCosts): void
    {
        $this->extraCostModel->deleteByPo($poId);
        foreach ($extraCosts as $cost) {
            $this->extraCostModel->create([
                'purchase_order_id' => $poId,
                'cost_name' => $cost['cost_name'],
                'amount'    => $cost['amount'],
            ]);
        }
    }
}
