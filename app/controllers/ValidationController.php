<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/GoodsReceiptItem.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderHistory.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Inventory.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/OfflinePurchase.php';

class ValidationController extends Controller
{
    private GoodsReceiptItem $itemModel;
    private PurchaseOrderHistory $historyModel;
    private Project $projectModel;
    private Inventory $inventoryModel;
    private Item $itemMasterModel;
    private PurchaseOrder $poModel;
    private OfflinePurchase $offlinePurchaseModel;

    // Status hasil validasi yang boleh dianggap stok valid/tersedia.
    private const STOCK_VALID_STATUSES = ['sesuai', 'kurang', 'lebih'];

    public function __construct()
    {
        Middleware::requirePermission('validation', 'view');

        $this->itemModel      = new GoodsReceiptItem();
        $this->historyModel   = new PurchaseOrderHistory();
        $this->projectModel   = new Project();
        $this->inventoryModel = new Inventory();
        $this->itemMasterModel = new Item();
        $this->poModel        = new PurchaseOrder();
        $this->offlinePurchaseModel = new OfflinePurchase();
    }

    /**
     * Halaman utama validasi: semua item penerimaan barang, dengan filter
     * status & sudah/belum divalidasi. Ada notifikasi jumlah selisih di atas.
     */
    public function index()
    {
        $filters = [
            'validated' => $_GET['validated'] ?? 'no', // default: tampilkan yang belum divalidasi
            'status'    => $_GET['status'] ?? '',
            'keyword'   => $_GET['keyword'] ?? '',
        ];

        $items = $this->itemModel->listForValidation($filters);
        $pendingCount = $this->itemModel->countPendingSelisih();

        $this->view('validation/list', [
            'pageTitle'        => 'Validasi Barang Datang',
            'items'            => $items,
            'filters'          => $filters,
            'pendingCount'     => $pendingCount,
            'statusLabels'     => $this->itemModel->statusLabels,
            'statusBadgeClass' => $this->itemModel->statusBadgeClass,
            'canValidateItem'  => $this->canValidateItemFn(),
        ]);
    }

    /**
     * Penentu tombol "Validasi" per item untuk view: izin penuh, ATAU validasi mandiri
     * Lampu (cuma tampilan -- validateItem() tetap mengecek ulang di backend).
     */
    private function canValidateItemFn(): callable
    {
        $full = can('validation', 'validate');
        return fn(array $item): bool => $full || $this->itemModel->canSelfValidateLamp($item);
    }

    /**
     * Simpan hasil validasi satu item (dipanggil dari modal di halaman list)
     */
    public function validateItem()
    {
        // Gerbang: izin Validasi penuh, ATAU izin Validasi Mandiri Lampu (per-akun) --
        // yang terakhir dicek ulang per item di bawah (kategori Lampu + scope + belum divalidasi).
        $fullValidate = can('validation', 'validate');
        if (!$fullValidate && !can('validation', 'validate_lamp')) {
            Middleware::requirePermission('validation', 'validate'); // -> 403 standar
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('validation', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $status = $_POST['comparison_status'] ?? '';
        $notes = trim($_POST['validation_notes'] ?? '');

        $item = $this->itemModel->findFullById($id);
        if (!$item) {
            setFlash('error', 'Item penerimaan barang tidak ditemukan.');
            $this->redirect('validation', 'index');
        }

        // Jalur mandiri: backend memutuskan sendiri (bukan tombol di frontend).
        $lampSelf = !$fullValidate;
        if ($lampSelf && !$this->itemModel->canSelfValidateLamp($item)) {
            $msg = !empty($item['validated_at'])
                ? 'Item ini sudah divalidasi dan tidak bisa divalidasi ulang.'
                : 'Anda tidak berhak memvalidasi item ini (hanya barang Lampu pada penerimaan Anda sendiri).';
            denyAccess($msg);
        }
        // Setelah validasi dari halaman Detail Penerimaan, kembali ke sana (bukan daftar Validasi).
        $returnToReceipt = ($_POST['return'] ?? '') === 'receipt';
        $afterRedirect = function () use ($returnToReceipt, $item) {
            if ($returnToReceipt) {
                $this->redirect('goods_receipt', 'detail', ['id' => (int) $item['goods_receipt_id']]);
            }
            $this->redirect('validation', 'index');
        };

        if (!array_key_exists($status, $this->itemModel->statusLabels)) {
            setFlash('error', 'Status validasi tidak valid.');
            $afterRedirect();
        }

        if ($status !== 'sesuai' && $notes === '') {
            setFlash('error', 'Catatan wajib diisi jika status bukan "Sesuai".');
            $afterRedirect();
        }

        // Validasi mengubah stok -> ikut kunci periode 'validation' (tanggal = tgl penerimaan).
        assertPeriodOpen('validation', (string) ($item['receipt_date'] ?? ''), $returnToReceipt ? 'goods_receipt' : 'validation', $returnToReceipt ? 'detail' : 'index', $returnToReceipt ? ['id' => (int) $item['goods_receipt_id']] : []);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // Jalur mandiri: kunci baris & pastikan BELUM divalidasi (cegah klik ganda /
            // validator lain yang sudah memproses duluan -> tak ada validasi/stok ganda).
            if ($lampSelf) {
                $state = $this->itemModel->lockValidationState($id);
                if (!$state || !empty($state['validated_at'])) {
                    $pdo->rollBack();
                    setFlash('error', 'Item ini sudah divalidasi dan tidak bisa divalidasi ulang.');
                    $afterRedirect();
                }
            }

            $this->itemModel->validateItem($id, $status, $notes, currentUserId());

            // ROOT FIX bug "barang lain tapi stok tetap Aman": stok baru dikreditkan
            // ke inventory begitu item DIVALIDASI dengan hasil yang valid (sesuai/kurang/
            // lebih), bukan saat penerimaan disimpan. stock_posted_at menjaga supaya
            // kredit/reverse ini idempotent walau item divalidasi ulang berkali-kali
            // (mis. validator awalnya salah pilih status, lalu dikoreksi).
            $wasPosted = !empty($item['stock_posted_at']);
            $isNowValid = in_array($status, self::STOCK_VALID_STATUSES, true);

            if ($isNowValid && !$wasPosted) {
                // Jenis Stok = ikut master Barang (cocokkan nama); barang bebas /
                // "Barang Lain" tanpa master -> pakai pilihan Jenis Stok di header
                // Penerimaan (gr.stock_type), fallback terakhir dari stock_scope.
                $stockType = $this->itemMasterModel->stockTypeByName($item['item_name'])
                    ?? ($item['stock_type']
                        ?? (($item['stock_scope'] ?? 'proyek') === 'kantor' ? 'inventory_kantor' : 'stok_proyek'));

                $this->inventoryModel->creditStock(
                    $item['item_name'],
                    $item['unit'],
                    $item['project_id'] !== null ? (int) $item['project_id'] : null,
                    (float) $item['qty_received'],
                    'goods_receipt_validation',
                    (int) $item['goods_receipt_id'],
                    date('Y-m-d'),
                    currentUserId(),
                    $item['stock_scope'],
                    $stockType
                );
                $this->itemModel->markStockPosted($id, true);
            } elseif (!$isNowValid && $wasPosted) {
                $this->inventoryModel->reverseCredit(
                    $item['item_name'],
                    $item['unit'],
                    $item['project_id'] !== null ? (int) $item['project_id'] : null,
                    (float) $item['qty_received'],
                    'goods_receipt_validation',
                    (int) $item['goods_receipt_id'],
                    currentUserId(),
                    $item['stock_scope']
                );
                $this->itemModel->markStockPosted($id, false);
            }

            if ($item['purchase_order_id']) {
                $this->poModel->refreshReceiptStatus((int) $item['purchase_order_id']);

                $this->historyModel->log(
                    (int) $item['purchase_order_id'],
                    'validation',
                    "Item '{$item['item_name']}' pada {$item['receipt_number']} divalidasi: "
                        . $this->itemModel->statusLabels[$status]
                        . ($notes ? " ({$notes})" : ''),
                    currentUserId()
                );
            } elseif ($item['offline_purchase_id']) {
                $this->offlinePurchaseModel->refreshReceiptStatus((int) $item['offline_purchase_id']);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Validation error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan validasi. Silakan coba lagi.');
            $afterRedirect();
        }

        if ($lampSelf) {
            require_once ROOT_PATH . '/app/models/ActivityLog.php';
            (new ActivityLog())->log(
                currentUserId(),
                'goods_receipt',
                'validate',
                (currentUserName() ?: 'User') . " melakukan validasi mandiri penerimaan barang kategori Lampu: "
                    . "{$item['item_name']} pada {$item['receipt_number']} ("
                    . $this->itemModel->statusLabels[$status] . ')'
            );
        }

        setFlash('success', 'Validasi berhasil disimpan.');
        $afterRedirect();
    }

    /**
     * Section "Validasi Belum Sesuai": semua transaksi yang masih bermasalah
     * (belum divalidasi ATAU hasil validasinya bukan Sesuai) -- item ini tidak
     * boleh hilang dari list sampai benar-benar selesai & sesuai.
     */
    public function problem()
    {
        $filters = [
            'problem' => true,
            'status'  => $_GET['status'] ?? '',
            'keyword' => $_GET['keyword'] ?? '',
        ];

        $items = $this->itemModel->listForValidation($filters);

        $this->view('validation/list', [
            'pageTitle'        => 'Validasi Belum Sesuai',
            'items'            => $items,
            'filters'          => array_merge($filters, ['validated' => '']),
            'pendingCount'     => $this->itemModel->countPendingSelisih(),
            'statusLabels'     => $this->itemModel->statusLabels,
            'statusBadgeClass' => $this->itemModel->statusBadgeClass,
            'canValidateItem'  => $this->canValidateItemFn(),
            'isProblemView'    => true,
        ]);
    }

    /**
     * Section "Validasi Sesuai": semua item penerimaan barang yang SUDAH divalidasi
     * dengan hasil 'sesuai'. Terpisah dari list utama (yang defaultnya menampilkan
     * item belum divalidasi) dan dari problem() (yang menampilkan belum sesuai/belum
     * divalidasi) -- supaya barang yang sudah dikonfirmasi benar tidak tercampur
     * dengan yang masih butuh tindak lanjut.
     */
    public function approved()
    {
        $filters = [
            'validated' => 'yes',
            'status'    => 'sesuai',
            'keyword'   => $_GET['keyword'] ?? '',
        ];

        $items = $this->itemModel->listForValidation($filters);

        $this->view('validation/approved', [
            'pageTitle'        => 'Validasi Sesuai',
            'items'            => $items,
            'filters'          => $filters,
            'statusLabels'     => $this->itemModel->statusLabels,
            'statusBadgeClass' => $this->itemModel->statusBadgeClass,
        ]);
    }

    /**
     * Laporan selisih: hanya item kurang/lebih/barang_lain
     */
    public function report()
    {
        $filters = [
            'project_id' => $_GET['project_id'] ?? '',
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
        ];

        $items = $this->itemModel->selisihReport($filters);
        $projects = $this->projectModel->activeList();

        $this->view('validation/report', [
            'pageTitle'        => 'Laporan Selisih Barang',
            'items'            => $items,
            'filters'          => $filters,
            'projects'         => $projects,
            'statusLabels'     => $this->itemModel->statusLabels,
            'statusBadgeClass' => $this->itemModel->statusBadgeClass,
        ]);
    }

}
