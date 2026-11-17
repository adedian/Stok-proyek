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
            'bulkReason'       => $this->bulkReasonFn(),
            'validationHistory' => $this->itemModel->validationHistoryFor(array_column($items, 'id')),
            'bulkReturn'       => 'index',
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
     * Penentu checkbox "Pilih" per item untuk view: mengembalikan null bila item BOLEH ikut Validasi
     * Sesuai Semua, atau string alasan (tooltip) bila checkbox harus dinonaktifkan. Cuma tampilan --
     * bulkValidate() mengecek ulang semuanya di backend.
     */
    private function bulkReasonFn(): callable
    {
        $canValidate = $this->canValidateItemFn();
        return function (array $item) use ($canValidate): ?string {
            if ($reason = $this->itemModel->bulkIneligibleReason($item)) {
                return $reason;
            }
            if (!$canValidate($item)) {
                return 'Anda tidak berwenang memvalidasi barang ini';
            }
            if (isPeriodClosed('validation', (string) ($item['receipt_date'] ?? ''))) {
                return 'Periode sudah ditutup';
            }
            return null;
        };
    }

    /**
     * Inti validasi SATU item -- dipakai bersama oleh validateItem() (satuan) dan bulkValidate()
     * (massal). WAJIB dipanggil di dalam transaksi pemanggil. Menyimpan hasil + riwayat, mengkredit/
     * membalik stok, menyegarkan status penerimaan PO/Offline dan mencatat riwayat PO.
     * $item = hasil findFullById() SEBELUM validasi (dipakai membaca status lama & kunci stok).
     */
    private function applyValidation(array $item, string $status, string $notes, string $mode, ?string $batchId): void
    {
        $id = (int) $item['id'];

        // Riwayat dicatat dengan status LAMA item (sebelum diubah) -- append-only.
        $this->itemModel->logValidation($item, $status, $notes, (int) currentUserId(), $mode, $batchId);
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
                    . ($notes ? " ({$notes})" : '')
                    . ($mode === 'bulk' ? ' [validasi massal]' : ''),
                currentUserId()
            );
        } elseif ($item['offline_purchase_id']) {
            $this->offlinePurchaseModel->refreshReceiptStatus((int) $item['offline_purchase_id']);
        }
    }

    /**
     * VALIDASI SEMUA (massal): memvalidasi "Sesuai" barang-barang yang DICENTANG user.
     *
     * Aturan keras (semuanya dicek di backend -- checkbox/modal di frontend hanya UX):
     *  - hanya id yang dikirim (centang) yang diproses; tidak pernah "semua yang tampil";
     *  - tiap item dikunci (FOR UPDATE) & dibaca ulang: sudah divalidasi / ada selisih / penerimaan
     *    dihapus / periode ditutup / user tak berwenang -> DILEWATI, tidak dipaksa;
     *  - satu transaksi: bila ada error sistem (mis. stok), SEMUA dibatalkan;
     *  - tiap item mencatat riwayat validasi (batch_id sama) + pengguna & waktu.
     */
    public function bulkValidate()
    {
        $fullValidate = can('validation', 'validate');
        if (!$fullValidate && !can('validation', 'validate_lamp')) {
            Middleware::requirePermission('validation', 'validate'); // -> 403 standar
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('validation', 'index');
        }
        verifyCsrf();

        $back = (($_POST['return'] ?? '') === 'problem') ? 'problem' : 'index';

        if (($_POST['mode'] ?? '') !== 'sesuai') {
            setFlash('error', 'Jenis validasi massal tidak dikenal.');
            $this->redirect('validation', $back);
        }

        $rawIds = $_POST['ids'] ?? [];
        $ids = [];
        if (is_array($rawIds)) {
            foreach ($rawIds as $v) {
                if (is_scalar($v) && ctype_digit((string) $v) && (int) $v > 0) {
                    $ids[(int) $v] = true;
                }
            }
        }
        $ids = array_keys($ids);
        sort($ids); // urutan kunci konsisten -> tidak saling deadlock bila dua orang memvalidasi bersamaan
        if (empty($ids)) {
            setFlash('error', 'Belum ada barang yang dipilih. Centang barang yang akan divalidasi terlebih dahulu.');
            $this->redirect('validation', $back);
        }
        if (count($ids) > 300) {
            setFlash('error', 'Terlalu banyak barang dalam satu kali validasi (maksimal 300). Kurangi pilihan Anda.');
            $this->redirect('validation', $back);
        }

        $batchId = date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $done = [];
        $skipped = []; // alasan => jumlah

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            foreach ($ids as $id) {
                $state = $this->itemModel->lockValidationState($id); // kunci baris item
                $item = $state ? $this->itemModel->findFullById($id) : null;
                if (!$item) {
                    $skipped['Item tidak ditemukan'] = ($skipped['Item tidak ditemukan'] ?? 0) + 1;
                    continue;
                }

                $reason = $this->itemModel->bulkIneligibleReason($item);
                if ($reason === null && !$fullValidate && !$this->itemModel->canSelfValidateLamp($item)) {
                    $reason = 'Anda tidak berwenang memvalidasi barang ini';
                }
                if ($reason === null && isPeriodClosed('validation', (string) ($item['receipt_date'] ?? ''))) {
                    $reason = 'Periode sudah ditutup';
                }
                if ($reason !== null) {
                    $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;
                    continue;
                }

                $this->applyValidation($item, 'sesuai', '', 'bulk', $batchId);
                $done[] = $item;
            }

            if (empty($done)) {
                $pdo->rollBack();
                setFlash('warning', 'Tidak ada barang yang divalidasi. ' . $this->skipSummary($skipped));
                $this->redirect('validation', $back);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Bulk validation error: ' . $e->getMessage());
            setFlash('error', $e instanceof InsufficientStockException
                ? $e->getMessage()
                : 'Validasi massal gagal dan dibatalkan seluruhnya. Tidak ada barang yang berubah. Silakan coba lagi.');
            $this->redirect('validation', $back);
        }

        require_once ROOT_PATH . '/app/models/ActivityLog.php';
        $names = array_map(fn($i) => $i['item_name'] . ' (' . $i['receipt_number'] . ')', array_slice($done, 0, 10));
        (new ActivityLog())->log(
            currentUserId(),
            'validation',
            'validate',
            (currentUserName() ?: 'User') . ' melakukan validasi massal "Sesuai" ' . count($done) . ' barang [batch ' . $batchId . ']: '
                . implode(', ', $names) . (count($done) > 10 ? ', dst.' : '')
        );

        $msg = count($done) . ' barang berhasil divalidasi Sesuai.';
        if (!empty($skipped)) {
            $msg .= ' ' . $this->skipSummary($skipped);
        }
        setFlash('success', $msg);
        $this->redirect('validation', $back);
    }

    /** "3 barang dilewati: 2 Sudah divalidasi, 1 Ada selisih ..." */
    private function skipSummary(array $skipped): string
    {
        if (empty($skipped)) {
            return '';
        }
        $parts = [];
        foreach ($skipped as $reason => $n) {
            $parts[] = "{$n} {$reason}";
        }
        return array_sum($skipped) . ' barang dilewati (tidak diproses): ' . implode('; ', $parts) . '.';
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

            $this->applyValidation($item, $status, $notes, 'single', null);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Validation error: ' . $e->getMessage());
            setFlash('error', $e instanceof InsufficientStockException
                ? $e->getMessage()
                : 'Gagal menyimpan validasi. Silakan coba lagi.');
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
            'bulkReason'       => $this->bulkReasonFn(),
            'validationHistory' => $this->itemModel->validationHistoryFor(array_column($items, 'id')),
            'bulkReturn'       => 'problem',
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
