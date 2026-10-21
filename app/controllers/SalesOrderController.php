<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/SalesOrder.php';
require_once ROOT_PATH . '/app/models/SalesOrderFulfillment.php';
require_once ROOT_PATH . '/app/models/SalesInvoice.php';
require_once ROOT_PATH . '/app/models/SalesInvoiceItem.php';
require_once ROOT_PATH . '/app/models/SalesInvoiceTerm.php';
require_once ROOT_PATH . '/app/models/Client.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/Signature.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Order Penjualan.
 *
 * Alur: Purchase input order (PO Customer) -> Accounting memeriksa stok & MEMPROSES pemenuhan
 * (Pengeluaran Barang yang sah, lihat SalesOrderFulfillment) -> Invoice Keluar -> Surat Jalan.
 * Menyimpan order TIDAK mengubah stok dan TIDAK membuat invoice/surat jalan.
 *
 * Keamanan: setiap action memanggil Middleware::requirePermission() sendiri (frontend hanya
 * menyembunyikan tombol). Semua POST memakai CSRF. Aturan bisnis (sisa pesanan, stok, status)
 * divalidasi ulang di server DI DALAM transaksi dengan kunci baris order.
 */
class SalesOrderController extends Controller
{
    private SalesOrder $orderModel;
    private SalesOrderFulfillment $fulfillModel;
    private Client $clientModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('sales_order', 'view');
        $this->orderModel = new SalesOrder();
        $this->fulfillModel = new SalesOrderFulfillment();
        $this->clientModel = new Client();
        $this->activityLog = new ActivityLog();
    }

    // =================== Daftar ===================

    public function index()
    {
        $filters = [
            'keyword'      => trim((string) ($_GET['keyword'] ?? '')),
            'client_id'    => (int) ($_GET['client_id'] ?? 0),
            'status'       => trim((string) ($_GET['status'] ?? '')),
            'stock_status' => trim((string) ($_GET['stock_status'] ?? '')),
            'date_from'    => trim((string) ($_GET['date_from'] ?? '')),
            'date_to'      => trim((string) ($_GET['date_to'] ?? '')),
        ];
        // Tanggal filter yang tidak valid diabaikan (bukan error 500).
        foreach (['date_from', 'date_to'] as $k) {
            if ($filters[$k] !== '' && !isValidDateString($filters[$k])) {
                $filters[$k] = '';
            }
        }

        $total = $this->orderModel->countFiltered($filters);
        $pg = paginationInfo($total, (int) ($_GET['page'] ?? 1));
        $rows = $this->orderModel->listPaginated($filters, $pg['perPage'], $pg['offset']);

        $this->view('sales_order/list', [
            'pageTitle'  => 'Order Penjualan',
            'rows'       => $rows,
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter(['module' => 'sales_order'] + $filters)),
            'clients'    => $this->clientModel->activeList(),
        ]);
    }

    // =================== Tambah ===================

    public function create()
    {
        Middleware::requirePermission('sales_order', 'create');

        $this->view('sales_order/form', [
            'pageTitle'   => 'Tambah Order Penjualan',
            'mode'        => 'create',
            'order'       => null,
            'items'       => [],
            'lockInfo'    => [],
            'orderNumber' => $this->orderModel->previewNumber(),
            'clients'     => $this->clientModel->activeList(),
            'itemCatalog' => $this->orderModel->itemCatalog(),
            'defaultPpn'  => $this->defaultPpn(),
            'isProcessed' => false,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('sales_order', 'create');
        $this->requirePost();

        [$data, $lines] = $this->collectInput();
        $errors = $this->validateInput($data, $lines, null);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('sales_order', 'create');
        }

        $status = (($_POST['save_mode'] ?? '') === 'draft') ? SalesOrder::DRAFT : SalesOrder::ACTIVE;
        $totals = SalesOrder::calcTotals($lines, $data['ppn_percent']);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $number = $this->orderModel->nextNumber($data['order_date']);
            $id = $this->orderModel->create(array_merge($data, $totals, [
                'order_number' => $number,
                'status'       => $status,
                'created_by'   => (int) currentUserId(),
            ]));
            foreach ($lines as $line) {
                $this->orderModel->insertItem($id, $line);
            }
            $this->orderModel->addHistory($id, currentUserId(), 'create', "Order {$number} dibuat (PO Customer {$data['po_number']})"
                . ($status === SalesOrder::DRAFT ? ' sebagai Draft' : ''));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SalesOrder store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan Order Penjualan. Silakan coba lagi.');
            $this->redirect('sales_order', 'create');
        }

        $this->activityLog->log(currentUserId(), 'sales_order', 'create',
            "Order Penjualan {$number} dibuat (PO Customer {$data['po_number']}, " . count($lines) . ' barang)');

        $shortages = $this->shortageMessages($this->orderModel->itemsWithProgress($id));
        if ($shortages && $status === SalesOrder::ACTIVE) {
            setFlash('warning', "Order {$number} tersimpan. Stok barang tidak mencukupi. " . implode(' ', array_slice($shortages, 0, 3))
                . (count($shortages) > 3 ? ' (dan ' . (count($shortages) - 3) . ' barang lain -- lihat detail order.)' : '')
                . ' Pesanan tetap tersimpan dan dapat diproses setelah stok tersedia.');
        } else {
            setFlash('success', "Order Penjualan {$number} berhasil disimpan" . ($status === SalesOrder::DRAFT ? ' sebagai Draft.' : '.'));
        }
        $this->redirect('sales_order', 'detail', ['id' => $id]);
    }

    // =================== Detail ===================

    public function detail()
    {
        $order = $this->loadOrder((int) ($_GET['id'] ?? 0));
        $id = (int) $order['id'];
        $items = $this->orderModel->itemsWithProgress($id);

        $this->view('sales_order/detail', [
            'pageTitle'     => 'Detail Order Penjualan',
            'order'         => $order,
            'items'         => $items,
            'shortages'     => ($order['status'] === SalesOrder::CANCELLED) ? [] : $this->shortageMessages($items),
            'fulfillments'  => $this->orderModel->fulfillmentsWithRows($id),
            'invoices'      => $this->orderModel->invoicesByOrder($id),
            'deliveryNotes' => $this->orderModel->deliveryNotesByOrder($id),
            'history'       => $this->orderModel->history($id),
            'canEditNow'    => $this->canEditOrder($order),
        ]);
    }

    // =================== Edit ===================

    public function edit()
    {
        Middleware::requirePermission('sales_order', 'edit');
        $order = $this->loadOrder((int) ($_GET['id'] ?? 0));
        $why = $this->editBlockReason($order);
        if ($why !== null) {
            setFlash('error', $why);
            $this->redirect('sales_order', 'detail', ['id' => $order['id']]);
        }

        $id = (int) $order['id'];
        $items = $this->orderModel->itemsWithProgress($id);
        $this->view('sales_order/form', [
            'pageTitle'   => 'Edit Order Penjualan',
            'mode'        => 'edit',
            'order'       => $order,
            'items'       => $items,
            'orderNumber' => $order['order_number'],
            'clients'     => $this->clientModel->activeList(),
            'itemCatalog' => $this->orderModel->itemCatalog(array_column($items, 'item_id')),
            'defaultPpn'  => $this->defaultPpn(),
            'isProcessed' => $this->isProcessed($id),
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('sales_order', 'edit');
        $this->requirePost();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->orderModel->find($id);
        if (!$existing) {
            setFlash('error', 'Order Penjualan tidak ditemukan.');
            $this->redirect('sales_order', 'index');
        }
        $why = $this->editBlockReason($existing);
        if ($why !== null) {
            setFlash('error', $why);
            $this->redirect('sales_order', 'detail', ['id' => $id]);
        }

        [$data, $lines] = $this->collectInput();
        $processed = $this->isProcessed($id);
        if ($processed) {
            $data['client_id'] = (int) $existing['client_id']; // sudah ada pemenuhan/invoice -> customer terkunci
        }
        $errors = $this->validateInput($data, $lines, $id);
        if ($errors) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('sales_order', 'edit', ['id' => $id]);
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $locked = $this->orderModel->lockForUpdate($id);
            // Status/pemrosesan bisa berubah antara form dibuka dan disimpan -> cek ulang di bawah kunci.
            $why = $this->editBlockReason($locked);
            if ($why !== null) {
                throw new DomainException($why);
            }
            $processed = $this->isProcessed($id);
            if ($processed) {
                $data['client_id'] = (int) $locked['client_id'];
            }

            $oldItems = $this->orderModel->itemsWithProgress($id);
            $oldById = [];
            foreach ($oldItems as $oi) {
                $oldById[(int) $oi['id']] = $oi;
            }

            // Cocokkan baris terkirim dengan baris lama (line_id) + terapkan kunci bila order sudah diproses.
            $keepIds = [];
            foreach ($lines as &$line) {
                $lid = (int) $line['line_id'];
                if ($lid > 0) {
                    if (!isset($oldById[$lid])) {
                        throw new DomainException('Ada baris barang yang bukan bagian dari order ini.');
                    }
                    if (isset($keepIds[$lid])) {
                        throw new DomainException('Baris barang terkirim ganda.');
                    }
                    $keepIds[$lid] = true;
                    $old = $oldById[$lid];
                    $touched = (float) $old['fulfilled_qty'] > SalesOrder::EPS || (float) $old['invoiced_qty'] > SalesOrder::EPS;
                    if ($touched) {
                        if ((int) $line['item_id'] !== (int) $old['item_id']) {
                            throw new DomainException("Barang '{$old['item_name']}' sudah dipenuhi/ditagih -- barangnya tidak bisa diganti.");
                        }
                        $floor = max((float) $old['fulfilled_qty'], (float) $old['invoiced_qty']);
                        if ($line['qty'] + SalesOrder::EPS < $floor) {
                            throw new DomainException("Qty '{$old['item_name']}' tidak boleh di bawah jumlah yang sudah dipenuhi/ditagih (" . $this->fmt($floor) . ' ' . $old['unit'] . ').');
                        }
                    }
                    if ((float) $old['invoiced_qty'] > SalesOrder::EPS
                        && (abs($line['unit_price'] - (float) $old['unit_price']) > 0.004
                            || abs($line['discount_percent'] - (float) $old['discount_percent']) > 0.004)) {
                        throw new DomainException("Harga/diskon '{$old['item_name']}' tidak bisa diubah karena sudah ditagih di Invoice Keluar.");
                    }
                    if ((int) $line['item_id'] === (int) $old['item_id']) {
                        // Barang sama: pertahankan snapshot kode/nama/spesifikasi/satuan yang tersimpan.
                        $line['item_code'] = $old['item_code'];
                        $line['item_name'] = $old['item_name'];
                        $line['specification'] = $old['specification'];
                        $line['unit'] = $old['unit'];
                    }
                }
            }
            unset($line);
            foreach ($oldById as $oid => $old) {
                if (!isset($keepIds[$oid])
                    && ((float) $old['fulfilled_qty'] > SalesOrder::EPS || (float) $old['invoiced_qty'] > SalesOrder::EPS)) {
                    throw new DomainException("Barang '{$old['item_name']}' sudah dipenuhi/ditagih -- barisnya tidak bisa dihapus.");
                }
            }

            $totals = SalesOrder::calcTotals($lines, $data['ppn_percent']);
            $this->orderModel->updateById($id, array_merge($data, $totals));

            foreach ($lines as $line) {
                if ((int) $line['line_id'] > 0) {
                    $this->orderModel->updateItem((int) $line['line_id'], $line);
                } else {
                    $this->orderModel->insertItem($id, $line);
                }
            }
            foreach ($oldById as $oid => $old) {
                if (!isset($keepIds[$oid])) {
                    $this->orderModel->deleteItem($oid);
                }
            }

            $summary = $this->diffSummary($existing, $oldItems, $data, $lines);
            $this->orderModel->addHistory($id, currentUserId(), 'update', $summary);
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            $this->redirect('sales_order', 'edit', ['id' => $id]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SalesOrder update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui Order Penjualan.');
            $this->redirect('sales_order', 'edit', ['id' => $id]);
        }

        $this->activityLog->log(currentUserId(), 'sales_order', 'update', "Order Penjualan {$existing['order_number']} diperbarui: {$summary}");
        setFlash('success', "Order {$existing['order_number']} berhasil diperbarui.");
        $this->redirect('sales_order', 'detail', ['id' => $id]);
    }

    // =================== Kirim ke Accounting / Batalkan ===================

    /** Draft -> Menunggu Proses (muncul untuk diproses Accounting). */
    public function release()
    {
        Middleware::requirePermission('sales_order', 'edit');
        $this->requirePost();
        $id = (int) ($_POST['id'] ?? 0);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $order = $this->orderModel->lockForUpdate($id);
            if (!$order) {
                throw new DomainException('Order Penjualan tidak ditemukan.');
            }
            if ($order['status'] !== SalesOrder::DRAFT) {
                throw new DomainException('Hanya order berstatus Draft yang bisa dikirim ke Accounting.');
            }
            $this->orderModel->updateById($id, ['status' => SalesOrder::ACTIVE]);
            $this->orderModel->addHistory($id, currentUserId(), 'release', 'Draft dikirim ke Accounting (Menunggu Proses)');
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            $this->redirect('sales_order', 'detail', ['id' => $id]);
        }

        $this->activityLog->log(currentUserId(), 'sales_order', 'update', "Order Penjualan {$order['order_number']} dikirim ke Accounting");
        setFlash('success', "Order {$order['order_number']} dikirim ke Accounting.");
        $this->redirect('sales_order', 'detail', ['id' => $id]);
    }

    public function cancel()
    {
        Middleware::requirePermission('sales_order', 'cancel');
        $this->requirePost();
        $id = (int) ($_POST['id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 255) {
            setFlash('error', 'Alasan pembatalan wajib diisi (maks. 255 karakter).');
            $this->redirect('sales_order', 'detail', ['id' => $id]);
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $order = $this->orderModel->lockForUpdate($id);
            if (!$order) {
                throw new DomainException('Order Penjualan tidak ditemukan.');
            }
            if ($order['status'] === SalesOrder::CANCELLED) {
                throw new DomainException('Order ini sudah dibatalkan.');
            }
            if ($this->orderModel->activeFulfillmentCount($id) > 0) {
                throw new DomainException('Order sudah memiliki pemenuhan barang. Koreksi (batalkan) pemenuhannya terlebih dahulu -- hanya Super Admin yang bisa.');
            }
            if ($this->orderModel->invoiceCount($id) > 0) {
                throw new DomainException('Order sudah memiliki Invoice Keluar. Batalkan invoicenya terlebih dahulu.');
            }
            $this->orderModel->updateById($id, [
                'status'        => SalesOrder::CANCELLED,
                'cancel_reason' => $reason,
                'cancelled_at'  => date('Y-m-d H:i:s'),
                'cancelled_by'  => (int) currentUserId(),
            ]);
            $this->orderModel->addHistory($id, currentUserId(), 'cancel', "Dibatalkan. Alasan: {$reason}");
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            $this->redirect('sales_order', 'detail', ['id' => $id]);
        }

        $this->activityLog->log(currentUserId(), 'sales_order', 'cancel', "Order Penjualan {$order['order_number']} dibatalkan. Alasan: {$reason}");
        setFlash('success', "Order {$order['order_number']} dibatalkan.");
        $this->redirect('sales_order', 'detail', ['id' => $id]);
    }

    // =================== Proses Pemenuhan (Accounting) ===================

    public function process()
    {
        Middleware::requirePermission('sales_order', 'process');
        $order = $this->loadOrder((int) ($_GET['id'] ?? 0));
        $id = (int) $order['id'];
        $this->assertProcessable($order);

        // "Stok diperiksa": catat pemeriksaan (riwayat hanya saat pertama kali, supaya tidak berisik).
        $first = empty($order['stock_checked_at']);
        $this->orderModel->updateById($id, ['stock_checked_at' => date('Y-m-d H:i:s'), 'stock_checked_by' => (int) currentUserId()]);
        if ($first) {
            $this->orderModel->addHistory($id, currentUserId(), 'check_stock', 'Accounting memeriksa stok & membuka halaman Proses');
            $this->activityLog->log(currentUserId(), 'sales_order', 'check_stock', "Stok Order Penjualan {$order['order_number']} diperiksa");
        }

        $items = $this->orderModel->itemsWithProgress($id);
        $buckets = [];
        foreach ($items as $it) {
            if ((float) $it['remaining_qty'] > SalesOrder::EPS) {
                $buckets[(int) $it['id']] = $this->orderModel->stockBuckets($this->masterName($it));
            }
        }

        $this->view('sales_order/process', [
            'pageTitle' => 'Proses Pemenuhan Order',
            'order'     => $this->orderModel->findWithAggregates($id),
            'items'     => $items,
            'buckets'   => $buckets,
            'token'     => SalesOrderFulfillment::newToken(),
        ]);
    }

    public function processStore()
    {
        Middleware::requirePermission('sales_order', 'process');
        $this->requirePost();
        $id = (int) ($_POST['id'] ?? 0);
        $order = $this->loadOrder($id);
        $this->assertProcessable($order);

        $date = (string) ($_POST['fulfillment_date'] ?? '');
        if (!isValidDateString($date)) {
            setFlash('error', 'Tanggal pengeluaran wajib diisi dengan tanggal yang valid.');
            $this->redirect('sales_order', 'process', ['id' => $id]);
        }
        assertPeriodOpen('stock_out', $date, 'sales_order', 'process', ['id' => $id]);

        $lineIds = $_POST['line_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $sources = $_POST['source'] ?? [];
        if (!is_array($lineIds) || !is_array($qtys) || !is_array($sources)) {
            setFlash('error', 'Data pemenuhan tidak valid.');
            $this->redirect('sales_order', 'process', ['id' => $id]);
        }
        $lines = [];
        foreach ($lineIds as $i => $lineId) {
            $lines[] = [
                'order_item_id' => (int) $lineId,
                'qty'           => parseQtyInput($qtys[$i] ?? 0),
                'inventory_id'  => (int) ($sources[$i] ?? 0),
            ];
        }
        foreach ($lines as $l) {
            if ($l['qty'] < 0) {
                setFlash('error', 'Jumlah pemenuhan tidak boleh negatif.');
                $this->redirect('sales_order', 'process', ['id' => $id]);
            }
        }

        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;
        $pic = trim((string) ($_POST['pic_name'] ?? '')) ?: currentUserName();
        $token = (string) ($_POST['token'] ?? '');

        try {
            $res = $this->fulfillModel->fulfill($id, $lines, $date, $token, mb_substr($pic, 0, 150), $notes, (int) currentUserId());
        } catch (DomainException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('sales_order', 'process', ['id' => $id]);
        } catch (Throwable $e) {
            error_log('SalesOrder fulfill error: ' . $e->getMessage());
            setFlash('error', 'Gagal memproses pemenuhan. Tidak ada stok yang berubah. Silakan coba lagi.');
            $this->redirect('sales_order', 'process', ['id' => $id]);
        }

        $this->orderModel->addHistory($id, currentUserId(), 'fulfill',
            "Pemenuhan {$res['stock_out_number']}: {$res['qty_total']} unit pada {$res['rows']} baris pengeluaran. Sisa belum terpenuhi: {$res['remaining']}");
        $this->activityLog->log(currentUserId(), 'sales_order', 'process',
            "Order Penjualan {$order['order_number']} diproses: pengeluaran {$res['stock_out_number']} ({$res['qty_total']} unit)");
        if ($res['remaining'] > SalesOrder::EPS) {
            setFlash('warning', "Pesanan berhasil diproses sebagian (dokumen {$res['stock_out_number']}). Sisa barang yang belum terpenuhi: " . $this->fmt($res['remaining']) . ' unit.');
        } else {
            $this->orderModel->addHistory($id, currentUserId(), 'complete', 'Seluruh barang pada order telah terpenuhi');
            setFlash('success', "Pemenuhan {$res['stock_out_number']} berhasil. Seluruh barang pada order ini telah terpenuhi.");
        }
        $this->redirect('sales_order', 'detail', ['id' => $id]);
    }

    /** Koreksi pemenuhan (Super Admin) -- stok dikembalikan, dokumen pengeluaran dipindah ke Tempat Sampah. */
    public function reverse()
    {
        Middleware::requirePermission('sales_order', 'reverse');
        $this->requirePost();
        $fulfillmentId = (int) ($_POST['fulfillment_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $ful = (new SalesOrderFulfillment())->find($fulfillmentId);
        if (!$ful) {
            setFlash('error', 'Pemenuhan tidak ditemukan.');
            $this->redirect('sales_order', 'index');
        }
        $orderId = (int) $ful['sales_order_id'];
        if ($reason === '' || mb_strlen($reason) > 255) {
            setFlash('error', 'Alasan koreksi wajib diisi (maks. 255 karakter).');
            $this->redirect('sales_order', 'detail', ['id' => $orderId]);
        }
        assertPeriodOpen('stock_out', (string) $ful['fulfillment_date'], 'sales_order', 'detail', ['id' => $orderId]);

        try {
            $res = $this->fulfillModel->reverse($fulfillmentId, $reason, (int) currentUserId());
        } catch (DomainException $e) {
            setFlash('error', $e->getMessage());
            $this->redirect('sales_order', 'detail', ['id' => $orderId]);
        } catch (Throwable $e) {
            error_log('SalesOrder reverse error: ' . $e->getMessage());
            setFlash('error', 'Gagal mengoreksi pemenuhan. Tidak ada stok yang berubah.');
            $this->redirect('sales_order', 'detail', ['id' => $orderId]);
        }

        $orderNo = (string) ($this->orderModel->find($orderId)['order_number'] ?? '');
        $this->orderModel->addHistory($orderId, currentUserId(), 'reverse', "Pemenuhan {$res['stock_out_number']} dikoreksi, stok dikembalikan. Alasan: {$reason}");
        $this->activityLog->log(currentUserId(), 'sales_order', 'reverse', "Pemenuhan {$res['stock_out_number']} pada Order {$orderNo} dikoreksi: {$reason}");
        setFlash('success', "Pemenuhan {$res['stock_out_number']} dikoreksi. Stok sudah dikembalikan dan barang kembali menjadi sisa pesanan.");
        $this->redirect('sales_order', 'detail', ['id' => $orderId]);
    }

    // =================== Invoice Keluar dari Order ===================

    public function invoice()
    {
        Middleware::requirePermission('sales_order', 'process');
        $order = $this->loadOrder((int) ($_GET['id'] ?? 0));
        $id = (int) $order['id'];
        $this->assertProcessable($order);

        $items = array_values(array_filter(
            $this->orderModel->itemsWithProgress($id),
            fn($it) => (float) $it['invoiceable_qty'] > SalesOrder::EPS
        ));
        if (!$items) {
            setFlash('error', 'Belum ada barang yang sudah dipenuhi dan belum ditagih. Invoice hanya bisa diterbitkan sesuai jumlah barang yang benar-benar sudah dikeluarkan.');
            $this->redirect('sales_order', 'detail', ['id' => $id]);
        }

        $this->view('sales_order/invoice_form', [
            'pageTitle'  => 'Terbitkan Invoice Keluar',
            'order'      => $order,
            'items'      => $items,
            'projects'   => (new Project())->activeList(),
            'signatures' => (new Signature())->activeList(),
        ]);
    }

    public function invoiceStore()
    {
        Middleware::requirePermission('sales_order', 'process');
        $this->requirePost();
        $id = (int) ($_POST['id'] ?? 0);
        $order = $this->loadOrder($id);
        $this->assertProcessable($order);

        $number = trim((string) preg_replace('/\s+/', ' ', is_string($_POST['invoice_number'] ?? '') ? $_POST['invoice_number'] : ''));
        $date = (string) ($_POST['invoice_date'] ?? '');
        $tempoRaw = trim((string) (is_string($_POST['tempo'] ?? '') ? $_POST['tempo'] : ''));
        $tempo = ($tempoRaw !== '' && ctype_digit($tempoRaw) && strlen($tempoRaw) <= 4 && (int) $tempoRaw <= INPUT_MAX_TEMPO_DAYS) ? (int) $tempoRaw : null;
        $ppn = (float) ($_POST['ppn_percent'] ?? $order['ppn_percent']);
        $invoiceType = in_array($_POST['invoice_type'] ?? '', ['project', 'lampu'], true) ? $_POST['invoice_type'] : 'project';
        $kind = (($_POST['document_kind'] ?? '') === 'proforma') ? 'proforma' : 'invoice';
        $projectId = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;
        $signatureId = !empty($_POST['signature_id']) ? (int) $_POST['signature_id'] : null;
        $taxNo = trim((string) ($_POST['tax_invoice_number'] ?? '')) ?: null;
        $notes = trim((string) ($_POST['notes'] ?? '')) ?: null;

        $errors = [];
        $invModel = new SalesInvoice();
        if ($number === '') {
            $errors[] = 'No. Invoice wajib diisi.';
        } elseif (mb_strlen($number) > 50) {
            $errors[] = 'No. Invoice maksimal 50 karakter.';
        } elseif ($invModel->numberTaken($number)) {
            $errors[] = "No. Invoice {$number} sudah dipakai invoice lain (termasuk yang ada di Tempat Sampah).";
        }
        if (!isValidDateString($date)) {
            $errors[] = 'Tanggal invoice wajib diisi dengan tanggal yang valid.';
        }
        if ($tempoRaw !== '' && $tempo === null) {
            $errors[] = 'Tempo harus berupa angka hari (0 sampai ' . INPUT_MAX_TEMPO_DAYS . ').';
        }
        if ($ppn < 0 || $ppn > 100) {
            $errors[] = 'PPN harus antara 0 dan 100 persen.';
        }
        if ($projectId !== null && !(new Project())->find($projectId)) {
            $errors[] = 'Project tidak ditemukan.';
        }

        $lineIds = $_POST['line_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $want = [];
        if (!is_array($lineIds) || !is_array($qtys)) {
            $errors[] = 'Data barang tidak valid.';
        } else {
            foreach ($lineIds as $i => $lineId) {
                $q = round(parseQtyInput($qtys[$i] ?? 0), 2);
                if ($q < 0) {
                    $errors[] = 'Jumlah tagihan tidak boleh negatif.';
                } elseif ($q > SalesOrder::EPS) {
                    if (isset($want[(int) $lineId])) {
                        $errors[] = 'Ada baris barang yang terkirim dua kali.';
                    }
                    $want[(int) $lineId] = $q;
                }
            }
        }
        if (!$want) {
            $errors[] = 'Isi jumlah tagihan minimal untuk satu barang.';
        }

        // Termin: label + persentase (rumus hitung di SalesInvoice::calculateTotals()).
        $due = null;
        if ($tempo !== null && isValidDateString($date)) {
            $due = (new DateTime($date))->modify("+{$tempo} days")->format('Y-m-d');
        }
        $terms = [];
        $labels = $_POST['term_label'] ?? [];
        $pcts = $_POST['term_percentage'] ?? [];
        if (is_array($labels) && is_array($pcts)) {
            foreach ($labels as $i => $label) {
                $label = trim((string) $label);
                $pct = (float) ($pcts[$i] ?? 0);
                if ($label === '' && $pct <= 0) {
                    continue;
                }
                $terms[] = ['dp_percentage_id' => null, 'label' => $label !== '' ? $label : 'Termin', 'percentage' => $pct, 'due_date' => $due];
            }
        }
        if (!$terms) {
            $errors[] = 'Minimal 1 baris termin tagihan wajib diisi.';
        }
        $sum = 0.0;
        foreach ($terms as $t) {
            if ($t['percentage'] <= 0) {
                $errors[] = 'Persentase setiap baris termin harus lebih dari 0%.';
                break;
            }
            $sum += $t['percentage'];
        }
        if ($sum > 100.01) {
            $errors[] = 'Total persentase seluruh termin tidak boleh melebihi 100%.';
        }

        if ($errors) {
            setFlash('error', implode(' ', array_values(array_unique($errors))));
            $this->redirect('sales_order', 'invoice', ['id' => $id]);
        }
        assertPeriodOpen('sales_invoice', $date, 'sales_order', 'invoice', ['id' => $id]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $locked = $this->orderModel->lockForUpdate($id);
            if ($locked['status'] !== SalesOrder::ACTIVE) {
                throw new DomainException('Order tidak lagi aktif.');
            }
            $invItems = [];
            foreach ($want as $lineId => $qty) {
                $line = $this->db()->fetchOne("SELECT * FROM sales_order_items WHERE id = :i AND sales_order_id = :o", ['i' => $lineId, 'o' => $id]);
                if (!$line) {
                    throw new DomainException('Ada baris barang yang bukan bagian dari order ini.');
                }
                // Batas = yang SUDAH DIKELUARKAN dikurangi yang SUDAH DITAGIH -- dihitung ulang di bawah kunci order.
                $invoiceable = round($this->orderModel->fulfilledQty($lineId) - $this->orderModel->invoicedQty($lineId), 2);
                if ($qty > $invoiceable + SalesOrder::EPS) {
                    throw new DomainException(
                        "Jumlah tagihan '{$line['item_name']}' (" . $this->fmt($qty) . ") melebihi barang yang sudah dikeluarkan dan belum ditagih ("
                        . $this->fmt(max(0, $invoiceable)) . " {$line['unit']})."
                    );
                }
                $desc = $line['item_name'] . (trim((string) $line['specification']) !== '' ? ' - ' . preg_replace('/\s+/', ' ', trim($line['specification'])) : '');
                $invItems[] = [
                    'sales_order_item_id' => $lineId,
                    'item_id'             => (int) $line['item_id'],
                    'description'         => mb_substr($desc, 0, 255),
                    'qty'                 => $qty,
                    'unit'                => $line['unit'],
                    'unit_price'          => (float) $line['net_price'],
                    'subtotal'            => round($qty * (float) $line['net_price'], 2),
                ];
            }

            $totals = SalesInvoice::calculateTotals($invItems, $terms, $ppn);
            $invoiceId = $invModel->create([
                'invoice_number'     => $number,
                'invoice_type'       => $invoiceType,
                'document_kind'      => $kind,
                'client_id'          => (int) $locked['client_id'],
                'project_id'         => $projectId,
                'sales_order_id'     => $id,
                'invoice_date'       => $date,
                'tempo'              => $tempo,
                'jatuh_tempo'        => $due,
                // PO Customer dicantumkan sebagai rujukan kontrak di dokumen invoice.
                'contract_number'    => $locked['po_number'],
                'contract_date'      => $locked['po_date'],
                'ppn_percent'        => $ppn,
                'subtotal'           => $totals['subtotal'],
                'dp_percentage_id'   => $totals['dp_percentage_id'],
                'dp_percentage'      => $totals['dp_percentage'],
                'dp_amount'          => $totals['dp_amount'],
                'ppn_amount'         => $totals['ppn_amount'],
                'total_amount'       => $totals['total'],
                'tax_invoice_number' => $taxNo,
                'signature_id'       => $signatureId,
                'notes'              => $notes,
                'created_by'         => (int) currentUserId(),
            ]);
            $itemModel = new SalesInvoiceItem();
            foreach ($invItems as $row) {
                $itemModel->create($row + ['sales_invoice_id' => $invoiceId]);
            }
            $termModel = new SalesInvoiceTerm();
            foreach ($totals['terms'] as $term) {
                $termModel->create($term + ['sales_invoice_id' => $invoiceId]);
            }
            $qtySum = array_sum(array_column($invItems, 'qty'));
            $this->orderModel->addHistory($id, currentUserId(), 'invoice', "Invoice {$number} diterbitkan ({$this->fmt($qtySum)} unit, total " . formatRupiah($totals['total']) . ')');
            $pdo->commit();
        } catch (DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('error', $e->getMessage());
            $this->redirect('sales_order', 'invoice', ['id' => $id]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SalesOrder invoiceStore error: ' . $e->getMessage());
            setFlash('error', 'Gagal menerbitkan Invoice Keluar. Silakan coba lagi.');
            $this->redirect('sales_order', 'invoice', ['id' => $id]);
        }

        $this->activityLog->log(currentUserId(), 'sales_invoice', 'create', "Invoice Keluar {$number} dibuat dari Order Penjualan {$order['order_number']}");
        setFlash('success', "Invoice {$number} berhasil diterbitkan dari Order {$order['order_number']}.");
        $this->redirect('sales_invoice', 'detail', ['id' => $invoiceId]);
    }

    /** Batalkan Invoice yang diterbitkan dari order (selama belum masuk Tanda Terima) -> jumlahnya bisa ditagih ulang. */
    public function voidInvoice()
    {
        Middleware::requirePermission('sales_order', 'process');
        $this->requirePost();
        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        $invModel = new SalesInvoice();
        $invoice = $invModel->find($invoiceId);
        if (!$invoice || empty($invoice['sales_order_id'])) {
            setFlash('error', 'Invoice dari Order Penjualan tidak ditemukan.');
            $this->redirect('sales_order', 'index');
        }
        $orderId = (int) $invoice['sales_order_id'];
        if ($invModel->isBilled($invoiceId)) {
            setFlash('error', 'Invoice ini sudah dipakai di sebuah Tanda Terima, tidak bisa dibatalkan.');
            $this->redirect('sales_order', 'detail', ['id' => $orderId]);
        }
        assertPeriodOpen('sales_invoice', (string) $invoice['invoice_date'], 'sales_order', 'detail', ['id' => $orderId]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $this->orderModel->lockForUpdate($orderId);
            $invModel->deleteById($invoiceId);
            $this->orderModel->addHistory($orderId, currentUserId(), 'void_invoice', "Invoice {$invoice['invoice_number']} dibatalkan (dipindah ke Tempat Sampah)");
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SalesOrder voidInvoice error: ' . $e->getMessage());
            setFlash('error', 'Gagal membatalkan invoice.');
            $this->redirect('sales_order', 'detail', ['id' => $orderId]);
        }
        $this->activityLog->log(currentUserId(), 'sales_invoice', 'delete', "Invoice Keluar {$invoice['invoice_number']} dibatalkan dari Order Penjualan");
        setFlash('success', "Invoice {$invoice['invoice_number']} dibatalkan. Jumlahnya kembali bisa ditagih.");
        $this->redirect('sales_order', 'detail', ['id' => $orderId]);
    }

    // =================== Surat Jalan dari Order ===================

    /** Arahkan ke form Surat Jalan yang SUDAH ADA untuk baris pengeluaran pemenuhan ini (tanpa modul duplikat). */
    public function deliveryNote()
    {
        Middleware::requirePermission('sales_order', 'process');
        Middleware::requirePermission('delivery_note', 'create');
        $fulfillmentId = (int) ($_GET['fulfillment_id'] ?? 0);
        $ful = $this->fulfillModel->find($fulfillmentId);
        if (!$ful || $ful['status'] !== 'aktif') {
            setFlash('error', 'Pemenuhan tidak ditemukan atau sudah dikoreksi.');
            $this->redirect('sales_order', 'index');
        }
        $ids = array_column($this->db()->fetchAll(
            "SELECT id FROM stock_out WHERE sales_order_fulfillment_id = :f AND deleted_at IS NULL AND delivery_note_id IS NULL ORDER BY id",
            ['f' => $fulfillmentId]
        ), 'id');
        if (!$ids) {
            setFlash('error', 'Seluruh barang pada pemenuhan ini sudah masuk Surat Jalan.');
            $this->redirect('sales_order', 'detail', ['id' => $ful['sales_order_id']]);
        }
        $this->redirect('delivery_note', 'select', ['ids' => implode(',', $ids)]);
    }

    // =================== Cetak ===================

    public function print()
    {
        Middleware::requirePermission('sales_order', 'print');
        $order = $this->loadOrder((int) ($_GET['id'] ?? 0));
        $id = (int) $order['id'];
        $this->view('sales_order/print', [
            'pageTitle'     => 'Cetak Order Penjualan',
            'documentTitle' => 'Order_Penjualan ' . str_replace(['/', '\\'], '_', (string) $order['order_number']),
            'order'         => $order,
            'items'         => $this->orderModel->itemsWithProgress($id),
            'company'       => (new SystemSetting())->getGroup('company'),
        ]);
    }

    // =================== AJAX ===================

    /** Stok TERKINI satu barang master (dipanggil form saat barang dipilih). */
    public function ajaxStock()
    {
        $info = $this->orderModel->itemStockInfo((int) ($_GET['item_id'] ?? 0));
        if (!$info) {
            $this->json(['error' => 'Barang tidak ditemukan'], 404);
        }
        $this->json([
            'item_code'     => $info['item_code'],
            'item_name'     => $info['item_name'],
            'specification' => (string) $info['specification'],
            'unit'          => $info['unit_name'],
            'stock_qty'     => (float) $info['stock_qty'],
        ]);
    }

    // =================== Helper privat ===================

    private function db(): Database
    {
        static $db = null;
        return $db ??= new Database();
    }

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('sales_order', 'index');
        }
        verifyCsrf();
    }

    private function loadOrder(int $id): array
    {
        $order = $id > 0 ? $this->orderModel->findWithAggregates($id) : null;
        if (!$order) {
            setFlash('error', 'Order Penjualan tidak ditemukan.');
            $this->redirect('sales_order', 'index');
        }
        return $order;
    }

    private function assertProcessable(array $order): void
    {
        if ($order['status'] === SalesOrder::CANCELLED) {
            setFlash('error', 'Order sudah dibatalkan.');
            $this->redirect('sales_order', 'detail', ['id' => $order['id']]);
        }
        if ($order['status'] === SalesOrder::DRAFT) {
            setFlash('error', 'Order masih Draft. Purchase harus mengirimkannya ke Accounting lebih dulu.');
            $this->redirect('sales_order', 'detail', ['id' => $order['id']]);
        }
    }

    /** Sudah ada pemenuhan aktif atau invoice -> order dianggap "sudah diproses Accounting". */
    private function isProcessed(int $orderId): bool
    {
        return $this->orderModel->activeFulfillmentCount($orderId) > 0 || $this->orderModel->invoiceCount($orderId) > 0;
    }

    /** null = boleh edit. Selain itu alasan penolakan. Order yang sudah diproses hanya boleh diedit Super Admin. */
    private function editBlockReason(array $order): ?string
    {
        if ($order['status'] === SalesOrder::CANCELLED) {
            return 'Order yang sudah dibatalkan tidak bisa diubah.';
        }
        if ($this->isProcessed((int) $order['id']) && currentUserRole() !== ROLE_SUPER_ADMIN) {
            return 'Order sudah diproses Accounting (ada pemenuhan/invoice) sehingga tidak bisa diubah. Hubungi Super Admin bila perlu koreksi.';
        }
        return null;
    }

    private function canEditOrder(array $order): bool
    {
        return can('sales_order', 'edit') && $this->editBlockReason($order) === null;
    }

    private function masterName(array $itemRow): string
    {
        $row = $this->db()->fetchOne("SELECT item_name FROM items WHERE id = :id", ['id' => $itemRow['item_id']]);
        return (string) ($row['item_name'] ?? $itemRow['item_name']);
    }

    private function defaultPpn(): float
    {
        return 11.0;
    }

    private function fmt($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, ',', '.'), '0'), ',') ?: '0';
    }

    /** Kalimat peringatan stok per barang yang masih kurang (spec bagian 9). */
    private function shortageMessages(array $items): array
    {
        $out = [];
        foreach ($items as $it) {
            $remaining = (float) $it['remaining_qty'];
            if ($remaining <= SalesOrder::EPS) {
                continue;
            }
            $stock = (float) $it['stock_qty'];
            if ($stock <= SalesOrder::EPS) {
                $out[] = "Barang {$it['item_name']} belum tersedia (kekurangan " . $this->fmt($remaining) . " {$it['unit']}).";
            } elseif ($stock + SalesOrder::EPS < $remaining) {
                $out[] = "Barang {$it['item_name']} dipesan " . $this->fmt($remaining) . " {$it['unit']}. Stok tersedia "
                    . $this->fmt($stock) . " {$it['unit']}. Kekurangan " . $this->fmt($remaining - $stock) . " {$it['unit']}.";
            }
        }
        return $out;
    }

    private function collectInput(): array
    {
        $str = fn($k, $max = 255) => mb_substr(trim((string) (is_string($_POST[$k] ?? '') ? $_POST[$k] : '')), 0, $max);

        $data = [
            'order_date'       => is_string($_POST['order_date'] ?? '') ? (string) ($_POST['order_date'] ?? '') : '',
            'client_id'        => (int) ($_POST['client_id'] ?? 0),
            'customer_address' => trim((string) (is_string($_POST['customer_address'] ?? '') ? $_POST['customer_address'] : '')) ?: null,
            'customer_phone'   => $str('customer_phone', 30) ?: null,
            'po_number'        => preg_replace('/\s+/', ' ', $str('po_number', 100)),
            'po_date'          => is_string($_POST['po_date'] ?? '') ? (string) ($_POST['po_date'] ?? '') : '',
            'ppn_percent'      => (float) ($_POST['ppn_percent'] ?? 0),
            'notes'            => trim((string) (is_string($_POST['notes'] ?? '') ? $_POST['notes'] : '')) ?: null,
        ];

        $lines = [];
        $itemIds = $_POST['item_id'] ?? [];
        $lineIds = $_POST['line_id'] ?? [];
        $qtys = $_POST['qty'] ?? [];
        $prices = $_POST['unit_price'] ?? [];
        $discs = $_POST['discount_percent'] ?? [];
        if (is_array($itemIds) && is_array($qtys) && is_array($prices) && is_array($discs)) {
            foreach ($itemIds as $i => $itemId) {
                $itemId = (int) $itemId;
                if ($itemId <= 0) {
                    continue; // baris kosong diabaikan
                }
                $qty = round(parseQtyInput($qtys[$i] ?? 0), 2);
                $price = parseCurrencyInput($prices[$i] ?? 0);
                $disc = (float) ($discs[$i] ?? 0);
                $calc = SalesOrder::calcLine($qty, $price, $disc);
                $lines[] = [
                    'line_id'          => is_array($lineIds) ? (int) ($lineIds[$i] ?? 0) : 0,
                    'item_id'          => $itemId,
                    'qty'              => $qty,
                    'unit_price'       => $price,
                    'raw_discount'     => $disc,
                    'discount_percent' => $calc['discount_percent'],
                    'net_price'        => $calc['net_price'],
                    'subtotal'         => $calc['subtotal'],
                ];
            }
        }
        return [$data, $lines];
    }

    /** Validasi + isi snapshot kode/nama/spesifikasi/satuan dari Master Barang ke $lines. */
    private function validateInput(array $data, array &$lines, ?int $excludeId): array
    {
        $errors = [];
        if (!isValidDateString($data['order_date'])) {
            $errors[] = 'Tanggal Order Penjualan wajib diisi dengan tanggal yang valid.';
        }
        if ($data['client_id'] <= 0 || !$this->clientModel->find($data['client_id'])) {
            $errors[] = 'Customer wajib dipilih.';
        }
        if ($data['po_number'] === '') {
            $errors[] = 'Nomor PO Customer wajib diisi.';
        }
        if (!isValidDateString($data['po_date'])) {
            $errors[] = 'Tanggal PO Customer wajib diisi dengan tanggal yang valid.';
        }
        if ($data['ppn_percent'] < 0 || $data['ppn_percent'] > 100) {
            $errors[] = 'PPN harus antara 0 dan 100 persen.';
        }
        if ($data['po_number'] !== '' && $data['client_id'] > 0) {
            $dup = $this->orderModel->poTaken($data['client_id'], $data['po_number'], $excludeId);
            if ($dup) {
                $errors[] = "Nomor PO Customer {$data['po_number']} untuk customer ini sudah dipakai di Order {$dup['order_number']}.";
            }
        }

        if (!$lines) {
            $errors[] = 'Minimal 1 barang wajib diisi.';
        }
        $seen = [];
        foreach ($lines as &$line) {
            $item = $this->orderModel->itemStockInfo($line['item_id']);
            if (!$item) {
                $errors[] = 'Ada barang yang tidak ditemukan di Master Barang.';
                continue;
            }
            $line['item_code'] = $item['item_code'];
            $line['item_name'] = $item['item_name'];
            $line['specification'] = (string) $item['specification'];
            $line['unit'] = $item['unit_name'];

            if ($line['qty'] <= 0) {
                $errors[] = "Jumlah pesanan '{$item['item_name']}' harus lebih dari 0.";
            }
            if ($line['qty'] > INPUT_MAX_QTY || $line['unit_price'] > INPUT_MAX_PRICE || $line['subtotal'] > INPUT_MAX_LINE_TOTAL) {
                $errors[] = "Qty/harga/total '{$item['item_name']}' melebihi batas wajar.";
            }
            if ($line['unit_price'] < 0) {
                $errors[] = "Harga '{$item['item_name']}' tidak boleh negatif.";
            }
            if ($line['raw_discount'] < 0 || $line['raw_discount'] > 100) {
                $errors[] = "Diskon '{$item['item_name']}' harus antara 0 dan 100 persen.";
            }
            if (isset($seen[$line['item_id']])) {
                $errors[] = "Barang '{$item['item_name']}' dipilih lebih dari sekali -- gabungkan jadi satu baris.";
            }
            $seen[$line['item_id']] = true;
        }
        unset($line);

        return array_values(array_unique($errors));
    }

    /** Ringkasan perubahan untuk Riwayat & audit trail. */
    private function diffSummary(array $old, array $oldItems, array $new, array $newLines): string
    {
        $parts = [];
        $head = [
            'order_date' => 'Tanggal order', 'po_number' => 'No. PO Customer', 'po_date' => 'Tanggal PO Customer',
            'customer_address' => 'Alamat', 'customer_phone' => 'Telepon', 'ppn_percent' => 'PPN', 'notes' => 'Catatan',
        ];
        foreach ($head as $k => $label) {
            if ((string) ($old[$k] ?? '') !== (string) ($new[$k] ?? '')) {
                $parts[] = "{$label}: '" . mb_substr((string) ($old[$k] ?? ''), 0, 60) . "' -> '" . mb_substr((string) ($new[$k] ?? ''), 0, 60) . "'";
            }
        }
        if ((int) $old['client_id'] !== (int) $new['client_id']) {
            $parts[] = 'Customer diganti';
        }
        $oldById = [];
        foreach ($oldItems as $oi) {
            $oldById[(int) $oi['id']] = $oi;
        }
        $kept = [];
        foreach ($newLines as $nl) {
            $lid = (int) $nl['line_id'];
            if ($lid > 0 && isset($oldById[$lid])) {
                $kept[$lid] = true;
                $o = $oldById[$lid];
                $chg = [];
                if (abs((float) $o['qty'] - $nl['qty']) > 0.004) {
                    $chg[] = 'qty ' . $this->fmt($o['qty']) . ' -> ' . $this->fmt($nl['qty']);
                }
                if (abs((float) $o['unit_price'] - $nl['unit_price']) > 0.004) {
                    $chg[] = 'harga ' . $this->fmt($o['unit_price']) . ' -> ' . $this->fmt($nl['unit_price']);
                }
                if (abs((float) $o['discount_percent'] - $nl['discount_percent']) > 0.004) {
                    $chg[] = 'diskon ' . $this->fmt($o['discount_percent']) . '% -> ' . $this->fmt($nl['discount_percent']) . '%';
                }
                if ((int) $o['item_id'] !== (int) $nl['item_id']) {
                    $chg[] = "barang diganti jadi {$nl['item_name']}";
                }
                if ($chg) {
                    $parts[] = "{$o['item_name']}: " . implode(', ', $chg);
                }
            } else {
                $parts[] = "Barang ditambah: {$nl['item_name']} x " . $this->fmt($nl['qty']);
            }
        }
        foreach ($oldById as $oid => $o) {
            if (!isset($kept[$oid])) {
                $parts[] = "Barang dihapus: {$o['item_name']}";
            }
        }
        return $parts ? implode('; ', $parts) : 'Tidak ada perubahan data';
    }
}
