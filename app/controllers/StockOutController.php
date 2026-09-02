<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/StockOut.php';
require_once ROOT_PATH . '/app/models/Inventory.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/SalesInvoice.php';
require_once ROOT_PATH . '/app/models/SalesInvoiceItem.php';

class StockOutController extends Controller
{
    private StockOut $stockOutModel;
    private Inventory $inventoryModel;
    private Project $projectModel;
    private User $userModel;
    private ActivityLog $activityLog;
    private SalesInvoice $salesInvoiceModel;
    private SalesInvoiceItem $salesInvoiceItemModel;

    /**
     * Tujuan pengeluaran "Client (Invoice)" -- urusan penjualan/AR, hanya boleh
     * dipakai Super Admin, Accounting, dan Purchase. Role lain: hanya Project.
     */
    private function canClientDestination(): bool
    {
        return hasRole([ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PURCHASE]);
    }

    public function __construct()
    {
        Middleware::requirePermission('stock_out', 'view');

        $this->stockOutModel = new StockOut();
        $this->inventoryModel = new Inventory();
        $this->projectModel = new Project();
        $this->userModel = new User();
        $this->activityLog = new ActivityLog();
        $this->salesInvoiceModel = new SalesInvoice();
        $this->salesInvoiceItemModel = new SalesInvoiceItem();
    }

    public function index()
    {
        $filters = [
            'project_id' => $_GET['project_id'] ?? '',
            'keyword'    => $_GET['keyword'] ?? '',
            'date_from'  => $_GET['date_from'] ?? '',
            'date_to'    => $_GET['date_to'] ?? '',
        ];

        $stockOuts = $this->stockOutModel->listWithRelations($filters);
        $projects = $this->projectModel->activeList();

        $this->view('stock_out/list', [
            'pageTitle' => 'Pengeluaran Barang',
            'stockOuts' => $stockOuts,
            'projects'  => $projects,
            'filters'   => $filters,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('stock_out', 'create');
        $canClientDest = $this->canClientDestination();
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $salesInvoiceId = $canClientDest ? (int) ($_GET['sales_invoice_id'] ?? 0) : 0;

        $inventoryItems = [];
        if ($projectId) {
            $inventoryItems = $this->inventoryModel->listByProject($projectId);
        } elseif ($salesInvoiceId) {
            $inventoryItems = $this->markInvoiceMatches($this->inventoryModel->listAllWithStock(), $salesInvoiceId);
        }

        $this->view('stock_out/form', [
            'pageTitle'    => 'Tambah Pengeluaran Barang',
            'mode'         => 'create',
            'stockOut'     => null,
            'projects'     => $this->projectModel->activeList(),
            'clientInvoices' => $canClientDest ? $this->salesInvoiceModel->listWithRelations([]) : [],
            'canClientDest' => $canClientDest,
            'selectedProjectId' => $projectId,
            'selectedSalesInvoiceId' => $salesInvoiceId,
            'selectedDestinationType' => $salesInvoiceId ? 'client' : 'project',
            'inventoryItems'    => $inventoryItems,
            'picUsers'          => $this->userModel->activeList(),
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('stock_out', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data, true);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('stock_out', 'create', ['project_id' => $data['project_id'], 'sales_invoice_id' => $data['sales_invoice_id']]);
        }

        assertPeriodOpen('stock_out', $data['out_date'], 'stock_out', 'create', ['project_id' => $data['project_id']]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // Satu submit form bisa berisi >1 barang -> dibuat 1 record stock_out
            // per barang (semua berbagi tujuan/PIC/tanggal yang sama). Model data
            // tetap "1 baris = 1 barang" spt sebelumnya, jadi Surat Jalan, laporan,
            // dashboard & Tempat Sampah tidak berubah.
            $qtyTotal = 0.0;
            foreach ($data['items'] as $item) {
                // Debit stok DULU -- kalau stok tidak cukup, exception dilempar
                // dan transaksi di-rollback, TIDAK ada record stock_out tersimpan.
                $this->inventoryModel->debitStock(
                    $item['inventory_id'],
                    $item['qty'],
                    'stock_out',
                    0, // reference_id di-update setelah record stock_out dibuat (lihat bawah)
                    $data['out_date'],
                    currentUserId(),
                    "Keluar ke {$data['destination']}"
                );

                $stockOutId = $this->stockOutModel->create([
                    'stock_out_number' => $this->stockOutModel->generateStockOutNumber(),
                    'inventory_id'     => $item['inventory_id'],
                    'project_id'       => $data['destination_type'] === 'project' ? $data['project_id'] : null,
                    'destination_type' => $data['destination_type'],
                    'sales_invoice_id' => $data['destination_type'] === 'client' ? $data['sales_invoice_id'] : null,
                    'pic_name'     => $data['pic_name'],
                    'destination'  => $data['destination'],
                    'qty'          => $item['qty'],
                    'out_date'     => $data['out_date'],
                    'notes'        => $data['notes'],
                    'created_by'   => currentUserId(),
                ]);

                // Update reference_id di stock_transactions yang barusan dibuat supaya menunjuk ke stock_out ini
                $this->fixLastTransactionReference($item['inventory_id'], $stockOutId);
                $qtyTotal += $item['qty'];
            }

            $jml = count($data['items']);
            $this->activityLog->log(
                currentUserId(),
                'stock_out',
                'create',
                "Pengeluaran barang dibuat: {$jml} barang ({$qtyTotal} unit) ke {$data['destination']}"
            );

            $pdo->commit();

            setFlash('success', $jml > 1
                ? "{$jml} baris pengeluaran barang berhasil disimpan."
                : 'Pengeluaran barang berhasil disimpan.');
            $this->redirect('stock_out', 'index');
        } catch (RuntimeException $e) {
            // Termasuk error "stok tidak mencukupi" dari debitStock()
            $pdo->rollBack();
            setFlash('error', $e->getMessage());
            $this->redirect('stock_out', 'create', ['project_id' => $data['project_id'], 'sales_invoice_id' => $data['sales_invoice_id']]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Stock out store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan pengeluaran barang. Silakan coba lagi.');
            $this->redirect('stock_out', 'create', ['project_id' => $data['project_id'], 'sales_invoice_id' => $data['sales_invoice_id']]);
        }
    }

    public function edit()
    {
        Middleware::requirePermission('stock_out', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $stockOut = $this->stockOutModel->findWithRelations($id);

        if (!$stockOut) {
            setFlash('error', 'Data pengeluaran barang tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }

        $this->view('stock_out/form', [
            'pageTitle'    => 'Edit Pengeluaran Barang',
            'mode'         => 'edit',
            'stockOut'     => $stockOut,
            'projects'     => $this->projectModel->activeList(),
            'clientInvoices' => $this->salesInvoiceModel->listWithRelations([]),
            // Semua field terkunci saat edit -- tetap tampilkan blok Client kalau
            // record-nya memang bertujuan client, walau role sekarang tak boleh
            // membuat yang baru.
            'canClientDest' => $this->canClientDestination() || $stockOut['destination_type'] === 'client',
            'selectedProjectId' => $stockOut['project_id'],
            'selectedSalesInvoiceId' => $stockOut['sales_invoice_id'],
            'selectedDestinationType' => $stockOut['destination_type'],
            'inventoryItems'    => $stockOut['project_id'] ? $this->inventoryModel->listByProject((int) $stockOut['project_id']) : [],
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('stock_out', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->stockOutModel->find($id);

        if (!$existing) {
            setFlash('error', 'Data pengeluaran barang tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('stock_out', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('stock_out', $existing['out_date'], 'stock_out', 'edit', ['id' => $id]);
        assertPeriodOpen('stock_out', $data['out_date'], 'stock_out', 'edit', ['id' => $id]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            // Kembalikan dulu stok lama (baik item maupun qty-nya bisa saja berubah)
            $this->inventoryModel->restoreStock(
                (int) $existing['inventory_id'],
                (float) $existing['qty'],
                'stock_out',
                $id,
                currentUserId(),
                'Koreksi otomatis karena pengeluaran barang ini diedit'
            );

            // Baru debit ulang dengan data baru -- kalau stok baru ternyata tidak cukup,
            // exception dilempar dan seluruh transaksi (termasuk restore di atas) di-rollback
            $this->inventoryModel->debitStock(
                $data['inventory_id'],
                $data['qty'],
                'stock_out',
                $id,
                $data['out_date'],
                currentUserId(),
                "Keluar ke {$data['destination']} (setelah edit)"
            );

            $this->stockOutModel->updateById($id, [
                'inventory_id'     => $data['inventory_id'],
                'project_id'       => $data['destination_type'] === 'project' ? $data['project_id'] : null,
                'destination_type' => $data['destination_type'],
                'sales_invoice_id' => $data['destination_type'] === 'client' ? $data['sales_invoice_id'] : null,
                'pic_name'     => $data['pic_name'],
                'destination'  => $data['destination'],
                'qty'          => $data['qty'],
                'out_date'     => $data['out_date'],
                'notes'        => $data['notes'],
            ]);

            $this->activityLog->log(
                currentUserId(),
                'stock_out',
                'update',
                "Pengeluaran barang #{$id} diperbarui"
            );

            $pdo->commit();

            setFlash('success', 'Pengeluaran barang berhasil diperbarui.');
            $this->redirect('stock_out', 'index');
        } catch (RuntimeException $e) {
            $pdo->rollBack();
            setFlash('error', $e->getMessage());
            $this->redirect('stock_out', 'edit', ['id' => $id]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Stock out update error: ' . $e->getMessage());
            setFlash('error', 'Gagal memperbarui pengeluaran barang.');
            $this->redirect('stock_out', 'edit', ['id' => $id]);
        }
    }

    public function delete()
    {
        Middleware::requirePermission('stock_out', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->stockOutModel->find($id);

        if (!$existing) {
            setFlash('error', 'Data pengeluaran barang tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }

        assertPeriodOpen('stock_out', $existing['out_date'], 'stock_out', 'index');
        $res = $this->deleteOneRecord($id);
        setFlash($res === true ? 'success' : 'error',
            $res === true ? 'Pengeluaran barang berhasil dihapus dan stok dikembalikan.' : 'Gagal menghapus pengeluaran barang.');

        $this->redirect('stock_out', 'index');
    }

    /**
     * Hapus 1 pengeluaran barang ke Tempat Sampah + kembalikan stok.
     * true = sukses, string = alasan skip. Dipakai delete() & rangeDelete().
     */
    private function deleteOneRecord(int $id)
    {
        $existing = $this->stockOutModel->find($id);
        if (!$existing) {
            return 'gagal';
        }
        // Soft-delete ke Tempat Sampah aman walau periode terkunci (koreksi stok
        // dicatat tanggal-sekarang) -- gerbang Tutup Bulan tetap berlaku untuk
        // hapus per-baris lewat delete().

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $this->inventoryModel->restoreStock(
                (int) $existing['inventory_id'],
                (float) $existing['qty'],
                'stock_out',
                $id,
                currentUserId(),
                'Koreksi otomatis karena pengeluaran barang ini dihapus'
            );

            $this->stockOutModel->deleteById($id);

            $this->activityLog->log(
                currentUserId(),
                'stock_out',
                'delete',
                "Pengeluaran barang #{$id} dihapus, stok dikembalikan"
            );

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Stock out deleteOneRecord error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua pengeluaran barang dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function rangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('stock_out', 'index');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('stock_out', 'index');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->stockOutModel->idsByDateRange('out_date', $from, $to) as $id) {
            $r = $this->deleteOneRecord($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('stock_out', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('stock_out', 'index');
    }

    /**
     * AJAX: ambil daftar item stok untuk project tertentu (dropdown "Barang")
     */
    public function ajaxItemsByProject()
    {
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $items = $projectId ? $this->inventoryModel->listByProject($projectId) : [];

        $this->json(['items' => $items]);
    }

    /**
     * AJAX: ambil daftar item YANG ADA STOKNYA lintas scope/project (dropdown
     * "Barang" saat tujuan pengeluaran = Client/Invoice) -- lihat catatan
     * lengkap di Inventory::listAllWithStock() kenapa bukan cuma Stok Kantor.
     */
    public function ajaxItemsByOffice()
    {
        if (!$this->canClientDestination()) {
            $this->json(['items' => []]);
        }
        $items = $this->inventoryModel->listAllWithStock();
        $salesInvoiceId = (int) ($_GET['sales_invoice_id'] ?? 0);
        if ($salesInvoiceId) {
            $items = $this->markInvoiceMatches($items, $salesInvoiceId);
        }
        $this->json(['items' => $items]);
    }

    /**
     * Tandai (bukan filter -- lihat diskusi: mayoritas baris invoice teks
     * bebas/jasa, TIDAK tertaut item_id, jadi filter ketat bikin dropdown
     * Barang kosong untuk hampir semua invoice) barang Stok Kantor yang
     * namanya cocok dengan salah satu barang di invoice terpilih, lalu
     * dahulukan yang cocok di urutan teratas. Match longgar (substring,
     * case-insensitive, 2 arah) karena nama di invoice vs Stok Kantor sering
     * beda kata sedikit (mis. "Inverter Deye 5kw" vs "Inverter Deye 5kw
     * Single Phase (SUN-5K-G05P1-EU-AM2)").
     */
    private function markInvoiceMatches(array $items, int $salesInvoiceId): array
    {
        $invoiceNames = array_filter(array_map('trim', $this->salesInvoiceItemModel->itemNamesByInvoice($salesInvoiceId)));

        foreach ($items as &$item) {
            $item['matches_invoice'] = false;
            foreach ($invoiceNames as $name) {
                if (stripos($item['item_name'], $name) !== false || stripos($name, $item['item_name']) !== false) {
                    $item['matches_invoice'] = true;
                    break;
                }
            }
        }
        unset($item);

        usort($items, fn($a, $b) => $b['matches_invoice'] <=> $a['matches_invoice']);

        return $items;
    }

    /**
     * AJAX: ambil sisa stok terkini untuk satu item inventory (dipanggil saat user
     * pilih barang, supaya validasi qty di JS bisa langsung tahu batas maksimalnya)
     */
    public function ajaxStockInfo()
    {
        $inventoryId = (int) ($_GET['inventory_id'] ?? 0);
        $item = $this->inventoryModel->find($inventoryId);

        if (!$item) {
            $this->json(['error' => 'Item tidak ditemukan'], 404);
        }

        $this->json([
            'qty_available' => (float) $item['qty_available'],
            'unit'          => $item['unit'],
        ]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        $destinationType = ($_POST['destination_type'] ?? 'project') === 'client' ? 'client' : 'project';
        // Client (Invoice) hanya Super Admin/Accounting/Purchase -- role lain
        // dipaksa ke Project walau POST-nya diutak-atik.
        if ($destinationType === 'client' && !$this->canClientDestination()) {
            $destinationType = 'project';
        }

        // Barang bisa dikirim sebagai array (form Tambah, >1 barang) atau skalar
        // (form Edit, tetap 1 barang). Normalkan jadi list $items + sediakan juga
        // inventory_id/qty "tunggal" (elemen pertama) supaya alur Edit lama jalan.
        $rawInv = $_POST['inventory_id'] ?? '';
        $rawQty = $_POST['qty'] ?? '';
        $items = [];
        if (is_array($rawInv)) {
            foreach ($rawInv as $i => $invId) {
                $invId = (int) $invId;
                $qty   = (float) ($rawQty[$i] ?? 0);
                if ($invId > 0 || $qty > 0) {
                    $items[] = ['inventory_id' => $invId, 'qty' => $qty];
                }
            }
        } elseif ((int) $rawInv > 0 || (float) $rawQty > 0) {
            $items[] = ['inventory_id' => (int) $rawInv, 'qty' => (float) $rawQty];
        }

        return [
            'items'            => $items,
            'inventory_id'     => $items[0]['inventory_id'] ?? 0,
            'qty'              => $items[0]['qty'] ?? 0.0,
            'project_id'       => (int) ($_POST['project_id'] ?? 0),
            'destination_type' => $destinationType,
            'sales_invoice_id' => (int) ($_POST['sales_invoice_id'] ?? 0),
            'pic_name'     => trim($_POST['pic_name'] ?? ''),
            'destination'  => trim($_POST['destination'] ?? ''),
            'out_date'     => $_POST['out_date'] ?? '',
            'notes'        => trim($_POST['notes'] ?? ''),
        ];
    }

    /**
     * @param bool $multiItem true saat Tambah (validasi list $data['items']),
     *                        false saat Edit (validasi 1 barang tunggal).
     */
    private function validateInput(array $data, bool $multiItem = false): array
    {
        $errors = [];

        // Tujuan pengeluaran WAJIB salah satu: Project ATAU Client (Invoice Keluar),
        // tidak boleh kosong dua-duanya atau diisi dua-duanya (lihat destination_type).
        if ($data['destination_type'] === 'project' && $data['project_id'] <= 0) {
            $errors[] = 'Project wajib dipilih.';
        }
        if ($data['destination_type'] === 'client' && $data['sales_invoice_id'] <= 0) {
            $errors[] = 'Client (Invoice) wajib dipilih.';
        }
        if ($data['pic_name'] === '') {
            $errors[] = 'PIC wajib diisi.';
        }
        if ($data['destination'] === '') {
            $errors[] = 'Tujuan wajib diisi.';
        }
        if (empty($data['out_date'])) {
            $errors[] = 'Tanggal keluar wajib diisi.';
        }

        if ($multiItem) {
            if (empty($data['items'])) {
                $errors[] = 'Minimal harus ada 1 barang.';
            }
            $seen = [];
            foreach ($data['items'] as $item) {
                if ($item['inventory_id'] <= 0) {
                    $errors[] = 'Masih ada baris barang yang belum dipilih.';
                    continue;
                }
                if ($item['qty'] <= 0) {
                    $errors[] = 'Qty harus lebih dari 0 untuk semua barang.';
                }
                if (isset($seen[$item['inventory_id']])) {
                    $errors[] = 'Ada barang yang dipilih lebih dari sekali -- gabungkan jadi satu baris.';
                }
                $seen[$item['inventory_id']] = true;
            }
            $errors = array_values(array_unique($errors));
        } else {
            if ($data['inventory_id'] <= 0) {
                $errors[] = 'Barang wajib dipilih.';
            }
            if ($data['qty'] <= 0) {
                $errors[] = 'Qty harus lebih dari 0.';
            }
        }

        // Catatan: pengecekan stok cukup/tidak dilakukan di Inventory::debitStock()
        // supaya logic-nya satu tempat saja (dipakai juga saat edit).

        return $errors;
    }

    /**
     * stock_transactions untuk stock_out dibuat SEBELUM record stock_out sendiri ada
     * (karena kita mau gagal cepat kalau stok tidak cukup, sebelum insert apapun).
     * Makanya reference_id-nya perlu di-patch belakangan setelah id stock_out diketahui.
     */
    private function fixLastTransactionReference(int $inventoryId, int $stockOutId): void
    {
        $db = new Database();
        $db->query(
            "UPDATE stock_transactions
             SET reference_id = :ref_id
             WHERE inventory_id = :inv_id AND reference_type = 'stock_out' AND reference_id = 0
             ORDER BY id DESC LIMIT 1",
            ['ref_id' => $stockOutId, 'inv_id' => $inventoryId]
        );
    }
}
