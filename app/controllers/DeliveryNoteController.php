<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/DeliveryNote.php';
require_once ROOT_PATH . '/app/models/StockOut.php';
require_once ROOT_PATH . '/app/models/Client.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Signature.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * DeliveryNoteController -- Surat Jalan.
 * TIDAK membuat transaksi pengeluaran barang baru. Modul ini murni mengelompokkan
 * baris stock_out yang sudah ada (dipilih via checkbox di Pengeluaran Barang)
 * jadi satu dokumen cetak. Lihat migration 2026_08_24_* untuk konteksnya.
 */
class DeliveryNoteController extends Controller
{
    private DeliveryNote $deliveryNoteModel;
    private StockOut $stockOutModel;
    private Client $clientModel;
    private Project $projectModel;
    private Signature $signatureModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('delivery_note', 'view');

        $this->deliveryNoteModel = new DeliveryNote();
        $this->stockOutModel     = new StockOut();
        $this->clientModel       = new Client();
        $this->projectModel      = new Project();
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

        $this->view('delivery_note/list', [
            'pageTitle' => 'Riwayat Surat Jalan',
            'notes'     => $this->deliveryNoteModel->listWithRelations($filters),
            'filters'   => $filters,
        ]);
    }

    /**
     * Form kecil: pilih tujuan (project/client) + kendaraan/driver/penerima/TTD
     * untuk baris stock_out yang sudah dicentang di Pengeluaran Barang.
     */
    public function select()
    {
        Middleware::requirePermission('delivery_note', 'create');

        $ids = $this->parseIds($_GET['ids'] ?? []);
        $rows = $this->validRows($ids);

        if (empty($rows)) {
            setFlash('error', 'Pilih minimal 1 baris Pengeluaran Barang yang belum masuk Surat Jalan manapun.');
            $this->redirect('stock_out', 'index');
        }
        // Daftar ID final (sudah diperluas ke seluruh barang satu dokumen).
        $ids = array_map(fn($r) => (int) $r['id'], $rows);

        // Baris dari pemenuhan Order Penjualan -> tujuan otomatis "Penjualan (Client)" milik order itu.
        [$orderId, $orderError] = $this->resolveSalesOrder($rows);
        if ($orderError !== null) {
            setFlash('error', $orderError);
            $this->redirect('stock_out', 'index');
        }
        $salesOrder = null;
        if ($orderId !== null) {
            require_once ROOT_PATH . '/app/models/SalesOrder.php';
            $salesOrder = (new SalesOrder())->findWithAggregates($orderId);
        }

        $this->view('delivery_note/select', [
            'pageTitle' => 'Buat Surat Jalan',
            'rows'      => $rows,
            'ids'       => $ids,
            'salesOrder' => $salesOrder,
            'clients'   => $this->clientModel->activeList(),
            'projects'  => $this->projectModel->activeList(),
            'signatures' => $this->signatureModel->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('delivery_note', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();

        $ids = $this->parseIds($_POST['ids'] ?? []);
        $rows = $this->validRows($ids);

        if (empty($rows)) {
            setFlash('error', 'Baris Pengeluaran Barang yang dipilih tidak valid atau sudah masuk Surat Jalan lain.');
            $this->redirect('stock_out', 'index');
        }

        // Referensi Order Penjualan DIAMBIL DARI BARIS pengeluaran (bukan dari input form):
        // satu Surat Jalan hanya boleh untuk satu order, dan tidak boleh dicampur dengan barang non-order.
        [$salesOrderId, $orderError] = $this->resolveSalesOrder($rows);
        if ($orderError !== null) {
            setFlash('error', $orderError);
            $this->redirect('stock_out', 'index');
        }

        $destinationType = in_array($_POST['destination_type'] ?? 'project', ['client', 'manual'], true)
            ? $_POST['destination_type']
            : 'project';
        $clientId = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;
        $projectId = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;
        $destinationName = trim($_POST['destination_name'] ?? '');

        if ($destinationType === 'client' && ($clientId === null || !$this->clientModel->find($clientId))) {
            setFlash('error', 'Client tujuan wajib dipilih untuk pengiriman penjualan.');
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        if ($destinationType === 'manual' && $destinationName === '') {
            setFlash('error', 'Nama Tujuan wajib diisi untuk tujuan pengiriman manual.');
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        $deliveryDate = $_POST['delivery_date'] ?: date('Y-m-d');
        if (!isValidDateString($deliveryDate)) {
            setFlash('error', 'Tanggal Surat Jalan tidak valid.');
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        // No. Surat Jalan diketik manual -- wajib, maks 50 karakter, unik (termasuk Tempat Sampah).
        $numberRaw = $_POST['delivery_number'] ?? '';
        $deliveryNumber = is_string($numberRaw) ? trim(preg_replace('/\s+/', ' ', $numberRaw)) : '';
        $numberError = null;
        if ($deliveryNumber === '') {
            $numberError = 'No. Surat Jalan wajib diisi.';
        } elseif (mb_strlen($deliveryNumber) > 50) {
            $numberError = 'No. Surat Jalan maksimal 50 karakter.';
        } elseif ($this->deliveryNoteModel->numberTaken($deliveryNumber)) {
            $numberError = "No. Surat Jalan {$deliveryNumber} sudah dipakai Surat Jalan lain (termasuk yang ada di Tempat Sampah).";
        }
        if ($numberError !== null) {
            setFlash('error', $numberError);
            $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
        }

        $data = [
            'delivery_number'  => $deliveryNumber,
            'delivery_date'    => $deliveryDate,
            'destination_type' => $destinationType,
            'client_id'        => $destinationType === 'client' ? $clientId : null,
            'project_id'       => $destinationType === 'project' ? $projectId : null,
            'sales_order_id'   => $salesOrderId,
            'destination_name' => $destinationName ?: null,
            // Kota tempat serah terima ditandatangani (baris penutup Surat Jalan) --
            // SENGAJA field terpisah dari destination_name/project/client: nama
            // project/client bisa saja bukan nama kota (mis. "PT Pei Hai Phase 2"),
            // itu bug yang diperbaiki di revisi ini. Di-prefill dari lokasi project
            // di form (lihat select.php), tapi tetap bebas diedit manual.
            'city'             => trim($_POST['city'] ?? '') ?: null,
            'vehicle_number'   => trim($_POST['vehicle_number'] ?? '') ?: null,
            'driver_name'      => trim($_POST['driver_name'] ?? '') ?: null,
            'sender_name'      => trim($_POST['sender_name'] ?? '') ?: null,
            'recipient_name'   => trim($_POST['recipient_name'] ?? '') ?: null,
            'signature_id'     => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'            => trim($_POST['notes'] ?? '') ?: null,
            'created_by'       => currentUserId(),
        ];

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $deliveryNoteId = $this->deliveryNoteModel->create($data);

            foreach ($rows as $row) {
                // UPDATE bersyarat (delivery_note_id masih NULL): kalau dua Surat Jalan dibuat bersamaan
                // untuk baris yang sama, hanya satu yang berhasil -- yang lain rollback, tidak ada SJ ganda.
                $claimed = $this->stockOutModel->claimForDeliveryNote((int) $row['id'], $deliveryNoteId);
                if (!$claimed) {
                    throw new DomainException('Sebagian barang yang dipilih baru saja dimasukkan ke Surat Jalan lain. Muat ulang daftar Pengeluaran Barang.');
                }
            }

            $this->activityLog->log(
                currentUserId(),
                'delivery_note',
                'create',
                "Surat Jalan {$data['delivery_number']} dibuat dari " . count($rows) . ' baris Pengeluaran Barang'
                    . ($salesOrderId ? " (Order Penjualan #{$salesOrderId})" : '')
            );
            if ($salesOrderId) {
                require_once ROOT_PATH . '/app/models/SalesOrder.php';
                (new SalesOrder())->addHistory($salesOrderId, currentUserId(), 'delivery_note',
                    "Surat Jalan {$data['delivery_number']} diterbitkan (" . count($rows) . ' baris barang)');
            }

            $pdo->commit();
            setFlash('success', "Surat Jalan {$data['delivery_number']} berhasil dibuat.");
            $this->redirect('delivery_note', 'print', ['id' => $deliveryNoteId]);
        } catch (DomainException $e) {
            $pdo->rollBack();
            setFlash('error', $e->getMessage());
            $this->redirect('stock_out', 'index');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('DeliveryNote store error: ' . $e->getMessage());
            setFlash('error', 'Gagal membuat Surat Jalan. Silakan coba lagi.');
            $this->redirect('stock_out', 'index');
        }
    }

    /**
     * Edit header Surat Jalan (nomor, tanggal, tujuan, kendaraan, driver, dst).
     * Barang di dalamnya TIDAK diubah di sini -- itu tetap hasil pengelompokan
     * Pengeluaran Barang. Izin delivery_note.edit: Super Admin + akun Nissa.
     */
    public function edit()
    {
        Middleware::requirePermission('delivery_note', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $note = $this->deliveryNoteModel->findWithRelations($id);
        if (!$note) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }

        $this->view('delivery_note/edit', [
            'pageTitle'  => 'Edit Surat Jalan',
            'note'       => $note,
            'items'      => $this->deliveryNoteModel->itemsByDeliveryNote($id),
            'clients'    => $this->clientModel->activeList(),
            'projects'   => $this->projectModel->activeList(),
            'signatures' => $this->signatureModel->activeList(),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('delivery_note', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->deliveryNoteModel->find($id);
        if (!$existing) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }

        $destinationType = in_array($_POST['destination_type'] ?? 'project', ['client', 'manual'], true)
            ? $_POST['destination_type']
            : 'project';
        $clientId = !empty($_POST['client_id']) ? (int) $_POST['client_id'] : null;
        $projectId = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;
        $destinationName = trim($_POST['destination_name'] ?? '');

        $error = null;
        $deliveryDate = $_POST['delivery_date'] ?: date('Y-m-d');
        $numberRaw = $_POST['delivery_number'] ?? '';
        $deliveryNumber = is_string($numberRaw) ? trim(preg_replace('/\s+/', ' ', $numberRaw)) : '';

        if ($destinationType === 'client' && ($clientId === null || !$this->clientModel->find($clientId))) {
            $error = 'Client tujuan wajib dipilih untuk pengiriman penjualan.';
        } elseif ($destinationType === 'manual' && $destinationName === '') {
            $error = 'Nama Tujuan wajib diisi untuk tujuan pengiriman manual.';
        } elseif (!isValidDateString($deliveryDate)) {
            $error = 'Tanggal Surat Jalan tidak valid.';
        } elseif ($deliveryNumber === '') {
            $error = 'No. Surat Jalan wajib diisi.';
        } elseif (mb_strlen($deliveryNumber) > 50) {
            $error = 'No. Surat Jalan maksimal 50 karakter.';
        } elseif ($this->deliveryNoteModel->numberTaken($deliveryNumber, $id)) {
            $error = "No. Surat Jalan {$deliveryNumber} sudah dipakai Surat Jalan lain (termasuk yang ada di Tempat Sampah).";
        }
        if ($error !== null) {
            setFlash('error', $error);
            $this->redirect('delivery_note', 'edit', ['id' => $id]);
        }

        $data = [
            'delivery_number'  => $deliveryNumber,
            'delivery_date'    => $deliveryDate,
            'destination_type' => $destinationType,
            'client_id'        => $destinationType === 'client' ? $clientId : null,
            'project_id'       => $destinationType === 'project' ? $projectId : null,
            'destination_name' => $destinationName ?: null,
            'city'             => trim($_POST['city'] ?? '') ?: null,
            'vehicle_number'   => trim($_POST['vehicle_number'] ?? '') ?: null,
            'driver_name'      => trim($_POST['driver_name'] ?? '') ?: null,
            'sender_name'      => trim($_POST['sender_name'] ?? '') ?: null,
            'recipient_name'   => trim($_POST['recipient_name'] ?? '') ?: null,
            'signature_id'     => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'            => trim($_POST['notes'] ?? '') ?: null,
        ];

        try {
            $this->deliveryNoteModel->updateById($id, $data);
            $logMsg = $deliveryNumber !== $existing['delivery_number']
                ? "Surat Jalan {$existing['delivery_number']} diperbarui (nomor diubah menjadi {$deliveryNumber})"
                : "Surat Jalan {$existing['delivery_number']} diperbarui";
            $this->activityLog->log(currentUserId(), 'delivery_note', 'update', $logMsg);
            setFlash('success', "Surat Jalan {$deliveryNumber} berhasil diperbarui.");
            $this->redirect('stock_out', 'index');
        } catch (Throwable $e) {
            error_log('DeliveryNote update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Surat Jalan. Silakan coba lagi.');
            $this->redirect('delivery_note', 'edit', ['id' => $id]);
        }
    }

    public function print()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $note = $this->deliveryNoteModel->findWithRelations($id);

        if (!$note) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('delivery_note', 'index');
        }

        $note['items'] = $this->deliveryNoteModel->itemsByDeliveryNote($id);

        $this->view('delivery_note/print', [
            'pageTitle' => 'Cetak Surat Jalan',
            'documentTitle' => $this->printFileTitle([$note]),
            'notes'     => [$note],
            'company'   => (new SystemSetting())->getGroup('company'),
        ]);
    }

    /**
     * Cetak Terpilih/Cetak Semua dari Riwayat Surat Jalan -- beberapa Surat Jalan
     * sekaligus, template yang sama, page-break antar dokumen (lihat
     * delivery_note/print.php). Tanpa ?ids= sama sekali -> "Cetak Semua" ikut
     * filter tanggal/keyword yang aktif saat itu, BUKAN seluruh tabel.
     */
    public function printMany()
    {
        $idsParam = $_GET['ids'] ?? [];
        $ids = $this->parseIds($idsParam);

        if (!empty($ids)) {
            $noteIds = $ids;
        } else {
            $filters = [
                'keyword'   => trim($_GET['keyword'] ?? ''),
                'date_from' => $_GET['date_from'] ?? '',
                'date_to'   => $_GET['date_to'] ?? '',
            ];
            $noteIds = array_column($this->deliveryNoteModel->listWithRelations($filters), 'id');
        }

        $notes = [];
        foreach ($noteIds as $id) {
            $note = $this->deliveryNoteModel->findWithRelations((int) $id);
            if ($note) {
                $note['items'] = $this->deliveryNoteModel->itemsByDeliveryNote((int) $id);
                $notes[] = $note;
            }
        }

        if (empty($notes)) {
            setFlash('error', 'Surat Jalan yang dipilih tidak ditemukan.');
            $this->redirect('delivery_note', 'index');
        }

        $this->view('delivery_note/print', [
            'pageTitle' => 'Cetak Surat Jalan',
            'documentTitle' => $this->printFileTitle($notes),
            'notes'     => $notes,
            'company'   => (new SystemSetting())->getGroup('company'),
        ]);
    }

    public function delete()
    {
        Middleware::requirePermission('delivery_note', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('delivery_note', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $note = $this->deliveryNoteModel->find($id);

        if (!$note) {
            setFlash('error', 'Surat Jalan tidak ditemukan.');
            $this->redirect('delivery_note', 'index');
        }

        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true
                ? 'Surat Jalan berhasil dihapus. Baris Pengeluaran Barang terkait dikembalikan sebagai belum dikelompokkan.'
                : 'Gagal menghapus Surat Jalan.');

        $this->redirect('delivery_note', 'index');
    }

    /**
     * Hapus 1 Surat Jalan ke Tempat Sampah + lepaskan baris Pengeluaran Barang
     * yang terkelompok di sini. true = sukses, string = alasan skip.
     */
    private function deleteOneRecord(int $id)
    {
        $note = $this->deliveryNoteModel->find($id);
        if (!$note) {
            return 'gagal';
        }
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            // Transaksi pengeluaran barangnya TIDAK dihapus, cuma dilepas dari
            // Surat Jalan supaya bisa dikelompokkan ulang.
            $this->stockOutModel->unlinkDeliveryNote($id);
            $this->deliveryNoteModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'delivery_note', 'delete', "Surat Jalan {$note['delivery_number']} dihapus");
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('DeliveryNote deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua Surat Jalan dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('delivery_note', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('delivery_note', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->deliveryNoteModel->idsByDateRange('delivery_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('delivery_note', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('delivery_note', 'index');
    }

    // ================= Surat Jalan dari Order Penjualan =================
    //
    // Alur satu langkah: pilih Order Penjualan -> isi Qty Dikirim -> simpan. Dalam SATU transaksi database:
    //   1. SalesOrderFulfillment::fulfill() membuat dokumen Pengeluaran Barang & mengurangi stok
    //      (jalur Inventory::debitStock yang sama dengan Pengeluaran Barang biasa -- TIDAK ada pengurangan kedua),
    //   2. Surat Jalan dibuat dan baris pengeluaran tadi langsung "diklaim" olehnya.
    // Gagal di mana pun = rollback total (stok tidak berubah, Surat Jalan tidak ada).

    /** Izin: boleh membuat SJ DAN memproses pengeluaran dari order (Accounting/Super Admin, sama seperti halaman Proses). */
    private function canShipFromOrder(): bool
    {
        return can('delivery_note', 'create') && can('sales_order', 'process');
    }

    private function requireShipFromOrder(): void
    {
        Middleware::requirePermission('delivery_note', 'create');
        Middleware::requirePermission('sales_order', 'process');
    }

    public function createFromOrder()
    {
        $this->requireShipFromOrder();
        require_once ROOT_PATH . '/app/models/SalesOrderFulfillment.php';

        $this->view('delivery_note/from_order', [
            'pageTitle'  => 'Buat Surat Jalan dari Order Penjualan',
            'signatures' => $this->signatureModel->activeList(),
            'token'      => SalesOrderFulfillment::newToken(),
            'presetOrderId' => (int) ($_GET['order_id'] ?? 0),
        ]);
    }

    /** Pencarian Order Penjualan untuk dropdown (maks. 20 baris). */
    public function ajaxOrders()
    {
        if (!$this->canShipFromOrder()) {
            $this->json(['error' => 'Tidak punya akses'], 403);
        }
        require_once ROOT_PATH . '/app/models/SalesOrder.php';
        $rows = (new SalesOrder())->searchForShipment(
            trim((string) ($_GET['q'] ?? '')),
            20,
            !empty($_GET['include_done'])
        );
        $out = [];
        foreach ($rows as $r) {
            $done = (float) $r['remaining_qty'] <= SalesOrder::EPS;
            $out[] = [
                'id'           => (int) $r['id'],
                'order_number' => $r['order_number'],
                'po_number'    => $r['po_number'],
                'client_name'  => $r['client_name'],
                'order_date'   => formatTanggal($r['order_date']),
                'remaining'    => (float) $r['remaining_qty'],
                'shipped'      => (float) $r['shipped_qty'],
                'done'         => $done,
            ];
        }
        $this->json(['orders' => $out]);
    }

    /** Header + barang + progres + stok TERKINI satu order (dipanggil saat order dipilih di form). */
    public function ajaxOrder()
    {
        if (!$this->canShipFromOrder()) {
            $this->json(['error' => 'Tidak punya akses'], 403);
        }
        require_once ROOT_PATH . '/app/models/SalesOrder.php';
        $orders = new SalesOrder();
        $id = (int) ($_GET['id'] ?? 0);
        $order = $orders->findWithAggregates($id);
        if (!$order) {
            $this->json(['error' => 'Order Penjualan tidak ditemukan.'], 404);
        }
        $client = $this->clientModel->find((int) $order['client_id']);
        $items = [];
        foreach ($orders->itemsWithProgress($id) as $it) {
            $remaining = (float) $it['remaining_qty'];
            $stock = (float) $it['stock_qty'];
            $items[] = [
                'id'            => (int) $it['id'],
                'item_code'     => $it['item_code'],
                'item_name'     => $it['item_name'],
                'specification' => (string) ($it['specification'] ?? ''),
                'unit'          => $it['unit'],
                'ordered'       => (float) $it['qty'],
                'shipped'       => (float) $it['fulfilled_qty'],
                'remaining'     => $remaining,
                'stock'         => $stock,
                'max_send'      => round(max(0.0, min($remaining, $stock)), 2),
            ];
        }
        $shipStatus = SalesOrder::shipStatus($order);
        $blocked = null;
        if ($order['status'] === SalesOrder::CANCELLED) {
            $blocked = 'Order ini sudah dibatalkan, tidak bisa dibuatkan Surat Jalan baru.';
        } elseif ($order['status'] !== SalesOrder::ACTIVE) {
            $blocked = 'Order masih berstatus Draft. Purchase harus mengirimkannya ke Accounting terlebih dahulu.';
        } elseif ((float) $order['remaining_qty'] <= SalesOrder::EPS) {
            $blocked = 'Seluruh barang pada order ini sudah terkirim. Tidak tersedia untuk pengiriman lanjutan.';
        }

        $this->json([
            'order' => [
                'id'            => (int) $order['id'],
                'order_number'  => $order['order_number'],
                'order_date'    => formatTanggal($order['order_date']),
                'po_number'     => $order['po_number'],
                'po_date'       => formatTanggal($order['po_date']),
                'client_name'   => $order['client_name'],
                'address'       => (string) (($order['customer_address'] ?? '') !== '' ? $order['customer_address'] : ($client['address'] ?? '')),
                'phone'         => (string) ($order['customer_phone'] ?? ''),
                'notes'         => (string) ($order['notes'] ?? ''),
                'ship_status'   => $shipStatus,
                'ship_label'    => SalesOrder::SHIP_LABELS[$shipStatus],
                'ship_badge'    => SalesOrder::SHIP_BADGES[$shipStatus],
                'ungrouped_rows' => $orders->ungroupedStockOutRows($id),
            ],
            'items'   => $items,
            'blocked' => $blocked,
        ]);
    }

    public function storeFromOrder()
    {
        $this->requireShipFromOrder();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();
        require_once ROOT_PATH . '/app/models/SalesOrder.php';
        require_once ROOT_PATH . '/app/models/SalesOrderFulfillment.php';

        $orderId = (int) ($_POST['sales_order_id'] ?? 0);
        $back = fn() => $this->redirect('delivery_note', 'createFromOrder', $orderId > 0 ? ['order_id' => $orderId] : []);

        $orders = new SalesOrder();
        $order = $orderId > 0 ? $orders->find($orderId) : null;
        if (!$order) {
            setFlash('error', 'Pilih Order Penjualan terlebih dahulu.');
            $this->redirect('delivery_note', 'createFromOrder');
        }

        $deliveryDate = (string) ($_POST['delivery_date'] ?? '');
        if ($deliveryDate === '') {
            $deliveryDate = date('Y-m-d');
        }
        if (!isValidDateString($deliveryDate)) {
            setFlash('error', 'Tanggal Surat Jalan tidak valid.');
            $back();
        }
        // Pengeluaran stok ikut tanggal Surat Jalan -> periode Pengeluaran Barang harus masih terbuka.
        assertPeriodOpen('stock_out', $deliveryDate, 'delivery_note', 'createFromOrder', ['order_id' => $orderId]);

        // No. Surat Jalan diketik manual -- wajib, maks 50 karakter, unik (termasuk Tempat Sampah).
        $numberRaw = $_POST['delivery_number'] ?? '';
        $deliveryNumber = is_string($numberRaw) ? trim(preg_replace('/\s+/', ' ', $numberRaw)) : '';
        $numberError = null;
        if ($deliveryNumber === '') {
            $numberError = 'No. Surat Jalan wajib diisi.';
        } elseif (mb_strlen($deliveryNumber) > 50) {
            $numberError = 'No. Surat Jalan maksimal 50 karakter.';
        } elseif ($this->deliveryNoteModel->numberTaken($deliveryNumber)) {
            $numberError = "No. Surat Jalan {$deliveryNumber} sudah dipakai Surat Jalan lain (termasuk yang ada di Tempat Sampah).";
        }
        if ($numberError !== null) {
            setFlash('error', $numberError);
            $back();
        }

        $lineIds = $_POST['line_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        if (!is_array($lineIds) || !is_array($qtys)) {
            setFlash('error', 'Data pengiriman tidak valid.');
            $back();
        }
        $lines = [];
        foreach ($lineIds as $i => $lineId) {
            $qty = parseQtyInput($qtys[$i] ?? 0);
            if ($qty < 0) {
                setFlash('error', 'Qty Dikirim tidak boleh negatif.');
                $back();
            }
            // inventory_id 0 = otomatis (Stok Kantor dulu, lalu stok terbanyak) -- sama seperti halaman Proses.
            $lines[] = ['order_item_id' => (int) $lineId, 'qty' => $qty, 'inventory_id' => 0];
        }
        $lines = array_values(array_filter($lines, fn($l) => $l['qty'] > SalesOrder::EPS));
        if (!$lines) {
            setFlash('error', 'Isi Qty Dikirim minimal untuk satu barang.');
            $back();
        }

        $client = $this->clientModel->find((int) $order['client_id']);
        $data = [
            'delivery_number'  => $deliveryNumber,
            'delivery_date'    => $deliveryDate,
            'destination_type' => 'client',
            'client_id'        => (int) $order['client_id'],
            'project_id'       => null,
            'sales_order_id'   => $orderId,
            'destination_name' => trim($_POST['destination_name'] ?? '') ?: ($client['client_name'] ?? null),
            'city'             => trim($_POST['city'] ?? '') ?: null,
            'vehicle_number'   => trim($_POST['vehicle_number'] ?? '') ?: null,
            'driver_name'      => trim($_POST['driver_name'] ?? '') ?: null,
            'sender_name'      => trim($_POST['sender_name'] ?? '') ?: null,
            'recipient_name'   => trim($_POST['recipient_name'] ?? '') ?: null,
            'signature_id'     => !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null,
            'notes'            => trim($_POST['notes'] ?? '') ?: null,
            'created_by'       => currentUserId(),
        ];
        $picName = mb_substr($data['sender_name'] ?: currentUserName(), 0, 150);
        $token = (string) ($_POST['token'] ?? '');

        $pdo = getPDO();
        $fulfillModel = new SalesOrderFulfillment();
        try {
            $pdo->beginTransaction();

            // (1) Pengeluaran Barang + pengurangan stok. Order dikunci, sisa pesanan & stok dihitung ulang di bawah kunci,
            //     token sekali-pakai menolak klik ganda / refresh. fulfill() ikut transaksi ini (tidak commit sendiri).
            $res = $fulfillModel->fulfill($orderId, $lines, $deliveryDate, $token, $picName, $data['notes'], (int) currentUserId());

            // (2) Surat Jalan + klaim baris pengeluaran yang baru dibuat.
            $deliveryNoteId = $this->deliveryNoteModel->create($data);
            $rowIds = array_column(getPDO()->query(
                'SELECT id FROM stock_out WHERE sales_order_fulfillment_id = ' . (int) $res['fulfillment_id'] . ' AND deleted_at IS NULL ORDER BY id'
            )->fetchAll(PDO::FETCH_ASSOC), 'id');
            if (!$rowIds || count($rowIds) !== (int) $res['rows']) {
                throw new RuntimeException('Jumlah baris pengeluaran tidak cocok dengan pemenuhan.');
            }
            foreach ($rowIds as $rid) {
                if (!$this->stockOutModel->claimForDeliveryNote((int) $rid, $deliveryNoteId)) {
                    throw new RuntimeException('Baris pengeluaran gagal dikaitkan ke Surat Jalan.');
                }
            }

            $this->activityLog->log(
                currentUserId(), 'delivery_note', 'create',
                "Surat Jalan {$deliveryNumber} dibuat dari Order Penjualan {$order['order_number']} (PO {$order['po_number']}): "
                . "pengeluaran {$res['stock_out_number']}, {$res['qty_total']} unit pada {$res['rows']} baris"
            );
            $orders->addHistory($orderId, currentUserId(), 'fulfill',
                "Surat Jalan {$deliveryNumber} diterbitkan bersama pengeluaran {$res['stock_out_number']}: {$res['qty_total']} unit. Sisa belum terkirim: {$res['remaining']}");
            if ($res['remaining'] <= SalesOrder::EPS) {
                $orders->addHistory($orderId, currentUserId(), 'complete', 'Seluruh barang pada order telah terkirim');
            }

            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            $back();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                setFlash('error', "No. Surat Jalan {$deliveryNumber} baru saja dipakai Surat Jalan lain, atau permintaan ini sudah diproses. Periksa Riwayat Surat Jalan.");
            } else {
                error_log('DeliveryNote storeFromOrder error: ' . $e->getMessage());
                setFlash('error', 'Gagal membuat Surat Jalan. Tidak ada stok yang berubah. Silakan coba lagi.');
            }
            $back();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('DeliveryNote storeFromOrder error: ' . $e->getMessage());
            setFlash('error', 'Gagal membuat Surat Jalan. Tidak ada stok yang berubah. Silakan coba lagi.');
            $back();
        }

        if ($res['remaining'] > SalesOrder::EPS) {
            setFlash('warning', "Surat Jalan {$deliveryNumber} dibuat (pengeluaran {$res['stock_out_number']}). Order masih punya sisa "
                . rtrim(rtrim(number_format($res['remaining'], 2, ',', '.'), '0'), ',') . ' unit yang belum dikirim.');
        } else {
            setFlash('success', "Surat Jalan {$deliveryNumber} dibuat (pengeluaran {$res['stock_out_number']}). Seluruh barang pada order telah terkirim.");
        }
        $this->redirect('delivery_note', 'print', ['id' => $deliveryNoteId]);
    }

    // ================= Helper privat =================

    /**
     * Judul halaman cetak = nama file default saat "Save as PDF" di browser.
     * Satu dokumen: "Surat_Jalan 011_SJ.HME_X_2026" (garis miring diganti garis bawah
     * karena tak boleh ada di nama file). Banyak dokumen: ringkasan jumlahnya.
     */
    private function printFileTitle(array $notes): string
    {
        if (count($notes) === 1) {
            return 'Surat_Jalan ' . str_replace(['/', '\\'], '_', (string) $notes[0]['delivery_number']);
        }
        return 'Surat_Jalan (' . count($notes) . ' dokumen)';
    }

    /**
     * Order Penjualan asal baris pengeluaran. [orderId|null, pesanError|null].
     * Aturan: semua baris dari SATU order yang sama, atau semuanya bukan dari order -- tidak boleh dicampur.
     */
    private function resolveSalesOrder(array $rows): array
    {
        $orderIds = [];
        $plain = 0;
        foreach ($rows as $r) {
            $oid = (int) ($r['sales_order_id'] ?? 0);
            if ($oid > 0) {
                $orderIds[$oid] = true;
            } else {
                $plain++;
            }
        }
        if (count($orderIds) > 1) {
            return [null, 'Satu Surat Jalan hanya boleh berisi barang dari satu Order Penjualan. Pilih pengeluaran dari order yang sama.'];
        }
        if ($orderIds && $plain > 0) {
            return [null, 'Barang dari Order Penjualan tidak boleh digabung dengan pengeluaran non-order dalam satu Surat Jalan.'];
        }
        return [$orderIds ? (int) array_key_first($orderIds) : null, null];
    }

    private function parseIds($idsParam): array
    {
        if (is_string($idsParam)) {
            $idsParam = explode(',', $idsParam);
        }
        return array_values(array_unique(array_filter(array_map('intval', $idsParam), fn($id) => $id > 0)));
    }

    /**
     * Ambil baris stock_out yang valid untuk dikelompokkan: ID ada, belum
     * dihapus, dan belum terpasang ke Surat Jalan manapun (delivery_note_id
     * IS NULL) -- baris yang sudah dipakai TIDAK BOLEH dobel-masuk dokumen lain.
     */
    private function validRows(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $rows = [];
        $seenDocs = [];
        foreach ($ids as $id) {
            $row = $this->stockOutModel->findWithRelations($id);
            if (!$row) {
                continue;
            }
            // Satu dokumen pengeluaran (banyak barang, 1 nomor) = SATU Surat Jalan:
            // mencentang salah satu barangnya otomatis menyertakan barang lain dari
            // dokumen yang sama, jadi tidak mungkin terbit SJ berbeda per barang.
            $docId = (int) ($row['document_id'] ?: $row['id']);
            if (isset($seenDocs[$docId])) {
                continue;
            }
            $seenDocs[$docId] = true;
            foreach ($this->stockOutModel->documentRows($docId) as $docRow) {
                if (empty($docRow['delivery_note_id'])) {
                    $rows[] = $this->stockOutModel->findWithRelations((int) $docRow['id']);
                }
            }
        }
        return $rows;
    }
}
