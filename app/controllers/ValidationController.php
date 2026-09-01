<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/GoodsReceiptItem.php';
require_once ROOT_PATH . '/app/models/PurchaseOrderHistory.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Inventory.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/OfflinePurchase.php';

class ValidationController extends Controller
{
    private GoodsReceiptItem $itemModel;
    private PurchaseOrderHistory $historyModel;
    private Project $projectModel;
    private Inventory $inventoryModel;
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
        ]);
    }

    /**
     * Simpan hasil validasi satu item (dipanggil dari modal di halaman list)
     */
    public function validateItem()
    {
        Middleware::requirePermission('validation', 'validate');

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

        if (!array_key_exists($status, $this->itemModel->statusLabels)) {
            setFlash('error', 'Status validasi tidak valid.');
            $this->redirect('validation', 'index');
        }

        if ($status !== 'sesuai' && $notes === '') {
            setFlash('error', 'Catatan wajib diisi jika status bukan "Sesuai".');
            $this->redirect('validation', 'index');
        }

        // Validasi mengubah stok -> ikut kunci periode 'validation' (tanggal = tgl penerimaan).
        assertPeriodOpen('validation', (string) ($item['receipt_date'] ?? ''), 'validation', 'index');

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $this->itemModel->validateItem($id, $status, $notes, currentUserId());

            // ROOT FIX bug "barang lain tapi stok tetap Aman": stok baru dikreditkan
            // ke inventory begitu item DIVALIDASI dengan hasil yang valid (sesuai/kurang/
            // lebih), bukan saat penerimaan disimpan. stock_posted_at menjaga supaya
            // kredit/reverse ini idempotent walau item divalidasi ulang berkali-kali
            // (mis. validator awalnya salah pilih status, lalu dikoreksi).
            $isNowValid = in_array($status, self::STOCK_VALID_STATUSES, true);
            $projectId = $item['project_id'] !== null ? (int) $item['project_id'] : null;

            if (!empty($item['cash_transaction_item_id'])) {
                // Alur Pembelian Kas: Kas SUDAH kredit qty penuh (cash_qty) saat
                // transaksi Kas disimpan. Validasi hanya menerapkan DELTA supaya
                // stok akhir = qty fisik yang diterima (0 kalau "barang lain").
                // stock_delta_applied menyimpan delta yang sudah dipasang -> idempoten
                // walau divalidasi ulang berkali-kali.
                $cashQty     = (float) ($item['cash_qty'] ?? 0);
                $finalWanted = $isNowValid ? (float) $item['qty_received'] : 0.0;
                $targetDelta = $finalWanted - $cashQty;
                $prevDelta   = $item['stock_delta_applied'] !== null ? (float) $item['stock_delta_applied'] : 0.0;
                $adjust      = $targetDelta - $prevDelta;

                if ($adjust > 0.00001) {
                    $this->inventoryModel->creditStock(
                        $item['item_name'],
                        $item['unit'],
                        $projectId,
                        $adjust,
                        'goods_receipt_validation',
                        (int) $item['goods_receipt_id'],
                        date('Y-m-d'),
                        currentUserId(),
                        $item['stock_scope']
                    );
                } elseif ($adjust < -0.00001) {
                    $this->inventoryModel->reverseCredit(
                        $item['item_name'],
                        $item['unit'],
                        $projectId,
                        -$adjust,
                        'goods_receipt_validation',
                        (int) $item['goods_receipt_id'],
                        currentUserId(),
                        $item['stock_scope']
                    );
                }
                $this->itemModel->markStockDeltaApplied($id, $targetDelta);
            } else {
                $wasPosted = !empty($item['stock_posted_at']);

                if ($isNowValid && !$wasPosted) {
                    $this->inventoryModel->creditStock(
                        $item['item_name'],
                        $item['unit'],
                        $projectId,
                        (float) $item['qty_received'],
                        'goods_receipt_validation',
                        (int) $item['goods_receipt_id'],
                        date('Y-m-d'),
                        currentUserId(),
                        $item['stock_scope']
                    );
                    $this->itemModel->markStockPosted($id, true);
                } elseif (!$isNowValid && $wasPosted) {
                    $this->inventoryModel->reverseCredit(
                        $item['item_name'],
                        $item['unit'],
                        $projectId,
                        (float) $item['qty_received'],
                        'goods_receipt_validation',
                        (int) $item['goods_receipt_id'],
                        currentUserId(),
                        $item['stock_scope']
                    );
                    $this->itemModel->markStockPosted($id, false);
                }
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
            $this->redirect('validation', 'index');
        }

        setFlash('success', 'Validasi berhasil disimpan.');
        $this->redirect('validation', 'index');
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
