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
            // SATU nomor dokumen untuk seluruh barang pada submit ini; baris pertama jadi
            // "kepala" dokumen (document_id = id-nya sendiri), baris lain mengacu ke dia.
            $docNumber = $this->stockOutModel->generateStockOutNumber();
            $docId = null;
            $qtyTotal = 0.0;
            foreach ($data['items'] as $item) {
                $newId = $this->createStockOutRecord($item, $data, $docNumber, $docId);
                if ($docId === null) {
                    $docId = $newId;
                }
                $qtyTotal += $item['qty'];
            }

            $jml = count($data['items']);
            $this->activityLog->log(
                currentUserId(),
                'stock_out',
                'create',
                "Pengeluaran barang {$docNumber} dibuat: {$jml} barang ({$qtyTotal} unit) ke {$data['destination']}"
            );

            $pdo->commit();

            setFlash('success', $jml > 1
                ? "Pengeluaran barang {$docNumber} berhasil disimpan ({$jml} barang)."
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
        // Edit membuka SELURUH dokumen (semua barang bernomor sama), bukan hanya baris yang diklik.
        $docId = (int) ($stockOut['document_id'] ?: $stockOut['id']);
        $docRows = $this->stockOutModel->documentRows($docId);
        foreach ($docRows as $docRow) {
            $this->assertValidationAllowsChange($docRow, 'diubah');
        }

        $this->view('stock_out/form', [
            'pageTitle'    => 'Edit Pengeluaran Barang',
            'mode'         => 'edit',
            'stockOut'     => $stockOut,
            'docRows'      => $docRows,
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

    /**
     * Simpan perubahan SATU dokumen pengeluaran (semua barangnya) -- TIDAK PERNAH membuat nomor baru.
     *
     * Identitas dokumen = document_id (id baris kepala) dari baris yang dibuka, dibaca dari DB.
     * Tujuan/Project/Client TIDAK dibaca dari request (terkunci) -- dipakai nilai tersimpan.
     *   - line_id[]/line_qty[]       : barang yang SUDAH ada. Qty berubah -> selisihnya saja yang
     *                                  dikreditkan/didebit ke stok (tidak ada potong stok ganda);
     *                                  baris yang tidak dikirim lagi = dihapus + stok dikembalikan.
     *   - add_inventory_id[]/add_qty[]: barang BARU -> baris baru pada dokumen & nomor yang sama.
     * Semua dalam satu transaksi: gagal di mana pun (mis. stok kurang) = rollback total.
     */
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
        $docId = (int) ($existing['document_id'] ?: $existing['id']);
        $docRows = $this->stockOutModel->documentRows($docId);
        if (empty($docRows)) {
            setFlash('error', 'Data pengeluaran barang tidak ditemukan.');
            $this->redirect('stock_out', 'index');
        }
        foreach ($docRows as $docRow) {
            $this->assertValidationAllowsChange($docRow, 'diubah');
        }
        $docNumber = (string) $existing['stock_out_number'];

        // --- Header: PIC/Tujuan/Tanggal/Keterangan boleh diubah; jenis tujuan & project/client terkunci ---
        $data = $this->collectInput();
        $data['destination_type'] = $existing['destination_type'];
        $data['project_id']       = (int) ($existing['project_id'] ?? 0);
        $data['sales_invoice_id'] = (int) ($existing['sales_invoice_id'] ?? 0);
        $errors = $this->validateInput($data, false, true);

        // --- Barang yang sudah ada: hanya id milik dokumen ini yang dianggap sah ---
        $rowsById = [];
        foreach ($docRows as $r) {
            $rowsById[(int) $r['id']] = $r;
        }
        $postedIds = $_POST['line_id'] ?? [];
        $postedQty = $_POST['line_qty'] ?? [];
        $keep = []; // id baris => qty baru
        if (!is_array($postedIds) || !is_array($postedQty)) {
            $postedIds = [];
            $postedQty = [];
            $errors[] = 'Data barang tidak valid.';
        }
        foreach ($postedIds as $i => $lineId) {
            $lineId = (int) $lineId;
            if (!isset($rowsById[$lineId])) {
                $errors[] = 'Ada baris barang yang bukan bagian dari dokumen ini.';
                continue;
            }
            if (isset($keep[$lineId])) {
                $errors[] = 'Baris barang terkirim ganda.';
                continue;
            }
            $qty = (float) ($postedQty[$i] ?? 0);
            if ($qty <= 0) {
                $errors[] = 'Qty harus lebih dari 0 untuk semua barang (hapus barisnya bila barang tidak jadi dikeluarkan).';
            } elseif ($qty > INPUT_MAX_QTY) {
                $errors[] = 'Qty melebihi batas wajar.';
            }
            $keep[$lineId] = $qty;
        }

        // --- Barang baru (tambahan) ---
        $extraItems = $this->collectExtraItems();
        if (!empty($extraItems) && !can('stock_out', 'create')) {
            denyAccess('Anda tidak punya izin menambah barang pengeluaran.');
        }
        $keptInventoryIds = [];
        foreach ($keep as $lineId => $qty) {
            $keptInventoryIds[] = (int) $rowsById[$lineId]['inventory_id'];
        }
        $errors = array_merge($errors, $this->validateExtraItems($extraItems, $keptInventoryIds));

        if (count($keep) + count($extraItems) === 0) {
            $errors[] = 'Dokumen harus punya minimal 1 barang. Untuk membatalkan seluruh pengeluaran, gunakan Hapus.';
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', array_values(array_unique($errors))));
            $this->redirect('stock_out', 'edit', ['id' => $id]);
        }

        assertPeriodOpen('stock_out', $existing['out_date'], 'stock_out', 'edit', ['id' => $id]);
        assertPeriodOpen('stock_out', $data['out_date'], 'stock_out', 'edit', ['id' => $id]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $changedQty = 0;
            $deleted = 0;
            foreach ($docRows as $row) {
                $rowId = (int) $row['id'];
                $invId = (int) $row['inventory_id'];
                $oldQty = (float) $row['qty'];

                if (!isset($keep[$rowId])) {
                    // Baris dihapus pengguna: kembalikan stok barisnya, lalu buang ke Tempat Sampah.
                    $this->inventoryModel->restoreStock(
                        $invId, $oldQty, 'stock_out', $rowId, currentUserId(),
                        'Koreksi otomatis karena barang dihapus dari pengeluaran ini'
                    );
                    $this->stockOutModel->deleteById($rowId);
                    $deleted++;
                    continue;
                }

                $newQty = $keep[$rowId];
                $diff = round($newQty - $oldQty, 2);
                if ($diff > 0) {
                    // Qty bertambah: debit HANYA selisihnya. Stok kurang -> exception -> rollback total.
                    $this->inventoryModel->debitStock(
                        $invId, $diff, 'stock_out', $rowId, $data['out_date'], currentUserId(),
                        "Keluar ke {$data['destination']} (tambah qty setelah edit)"
                    );
                    $changedQty++;
                } elseif ($diff < 0) {
                    $this->inventoryModel->restoreStock(
                        $invId, -$diff, 'stock_out', $rowId, currentUserId(),
                        'Koreksi otomatis karena qty pengeluaran barang ini diedit'
                    );
                    $changedQty++;
                }

                $fields = [
                    'pic_name'    => $data['pic_name'],
                    'destination' => $data['destination'],
                    'qty'         => $newQty,
                    'out_date'    => $data['out_date'],
                    'notes'       => $data['notes'],
                ];
                // Ditolak validator -> setelah diperbaiki kembali 'menunggu' (diajukan ulang).
                if (StockOut::requiresValidation($row['project_id']) && ($row['validation_status'] ?? '') === 'ditolak') {
                    $fields['validation_status'] = StockOut::initialValidationStatus($row['project_id']);
                    $fields['validated_by']      = null;
                    $fields['validated_at']      = null;
                    $fields['validation_note']   = null;
                }
                $this->stockOutModel->updateById($rowId, $fields);
            }

            // Barang baru: baris baru pada dokumen & NOMOR YANG SAMA (tidak ada nomor baru).
            foreach ($extraItems as $item) {
                $this->createStockOutRecord($item, $data, $docNumber, $docId);
            }

            $parts = [];
            if ($changedQty) { $parts[] = "{$changedQty} qty diubah"; }
            if ($extraItems) { $parts[] = count($extraItems) . ' barang ditambah'; }
            if ($deleted)    { $parts[] = "{$deleted} barang dihapus"; }
            $this->activityLog->log(
                currentUserId(),
                'stock_out',
                'update',
                "Pengeluaran barang {$docNumber} diperbarui" . ($parts ? ' (' . implode(', ', $parts) . ')' : '')
            );

            $pdo->commit();

            setFlash('success', "Pengeluaran barang {$docNumber} berhasil diperbarui"
                . ($parts ? ' (' . implode(', ', $parts) . ').' : '.'));
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
        $this->assertValidationAllowsChange($existing, 'dihapus');

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
        $items = $projectId ? $this->inventoryModel->listAllWithStock() : [];

        // Semua barang ber-stok ditampilkan; milik project tujuan di urutan teratas.
        // Pemilihan barang milik project lain/Kantor diberi peringatan di form.
        usort($items, function ($a, $b) use ($projectId) {
            $ownA = (int) ($a['project_id'] ?? 0) === $projectId ? 0 : 1;
            $ownB = (int) ($b['project_id'] ?? 0) === $projectId ? 0 : 1;
            return [$ownA, $a['item_name']] <=> [$ownB, $b['item_name']];
        });

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

    /**
     * Debit stok + buat SATU baris stock_out untuk satu barang pada dokumen $docNumber (dipakai
     * Tambah & penambahan barang di Edit; $documentId null = baris kepala dokumen baru). Dipanggil di dalam transaksi pemanggil: stok tidak cukup -> exception ->
     * seluruh transaksi di-rollback. Mengembalikan id stock_out baru.
     */
    private function createStockOutRecord(array $item, array $data, string $docNumber, ?int $documentId): int
    {
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
            'stock_out_number' => $docNumber,
            'document_id'      => $documentId, // NULL = baris kepala dokumen baru (di-set ke id-nya sendiri di bawah)
            'inventory_id'     => $item['inventory_id'],
            'project_id'       => $data['destination_type'] === 'project' ? $data['project_id'] : null,
            'destination_type' => $data['destination_type'],
            'sales_invoice_id' => $data['destination_type'] === 'client' ? $data['sales_invoice_id'] : null,
            'pic_name'     => $data['pic_name'],
            'destination'  => $data['destination'],
            'qty'          => $item['qty'],
            'out_date'     => $data['out_date'],
            'notes'        => $data['notes'],
            // Ditentukan SERVER dari project_id (bukan dari input form):
            // project -> 'menunggu' validasi, tanpa project -> 'tidak_perlu'.
            'validation_status' => StockOut::initialValidationStatus(
                $data['destination_type'] === 'project' ? $data['project_id'] : null
            ),
            'created_by'   => currentUserId(),
        ]);

        if ($documentId === null) {
            $this->stockOutModel->updateById($stockOutId, ['document_id' => $stockOutId]);
        }

        // Update reference_id di stock_transactions yang barusan dibuat supaya menunjuk ke stock_out ini
        $this->fixLastTransactionReference($item['inventory_id'], $stockOutId);
        return $stockOutId;
    }

    /** Barang tambahan dari form Edit (add_inventory_id[] + add_qty[]); baris kosong diabaikan. */
    private function collectExtraItems(): array
    {
        $rawInv = $_POST['add_inventory_id'] ?? [];
        $rawQty = $_POST['add_qty'] ?? [];
        $items = [];
        if (!is_array($rawInv) || !is_array($rawQty)) {
            return $items;
        }
        foreach ($rawInv as $i => $invId) {
            $invId = (int) $invId;
            $qty   = (float) ($rawQty[$i] ?? 0);
            if ($invId > 0 || $qty > 0) {
                $items[] = ['inventory_id' => $invId, 'qty' => $qty];
            }
        }
        return $items;
    }

    private function validateExtraItems(array $items, array $keptInventoryIds): array
    {
        $errors = [];
        $seen = [];
        foreach ($items as $item) {
            if ($item['inventory_id'] <= 0) {
                $errors[] = 'Masih ada baris barang tambahan yang belum dipilih.';
                continue;
            }
            if ($item['qty'] <= 0) {
                $errors[] = 'Qty harus lebih dari 0 untuk semua barang tambahan.';
            }
            if (in_array($item['inventory_id'], $keptInventoryIds, true)) {
                $errors[] = 'Barang tambahan tidak boleh sama dengan barang yang sudah ada di dokumen ini -- ubah Qty-nya saja.';
            }
            if ($item['qty'] > INPUT_MAX_QTY) {
                $errors[] = 'Qty melebihi batas wajar.';
            }
            if (!$this->inventoryModel->find($item['inventory_id'])) {
                $errors[] = 'Ada barang tambahan yang tidak ditemukan di data stok.';
            }
            if (isset($seen[$item['inventory_id']])) {
                $errors[] = 'Ada barang tambahan yang dipilih lebih dari sekali -- gabungkan jadi satu baris.';
            }
            $seen[$item['inventory_id']] = true;
        }
        return array_values(array_unique($errors));
    }

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
    private function validateInput(array $data, bool $multiItem = false, bool $headerOnly = false): array
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
        if (!isValidDateString($data['out_date'])) {
            $errors[] = 'Tanggal keluar wajib diisi dengan tanggal yang valid.';
        }

        if ($headerOnly) {
            return $errors; // Edit dokumen: barang divalidasi terpisah (line_* & add_*)
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
    /**
     * Pengeluaran barang yang SUDAH 'tervalidasi' terkunci -- hanya Super Admin
     * yang boleh mengubah/menghapus (pola identik Kas: CashTransaction::
     * assertValidationAllowsChange()). 'menunggu' & 'ditolak' tetap bebas
     * diedit/dihapus pembuatnya seperti sebelumnya.
     */
    private function assertValidationAllowsChange(array $row, string $verb): void
    {
        // Hanya pengeluaran PROJECT yang terkunci setelah tervalidasi; non-project
        // tidak butuh validasi sehingga workflow edit/hapusnya tidak berubah.
        if (StockOut::requiresValidation($row['project_id'] ?? null)
            && ($row['validation_status'] ?? 'menunggu') === 'tervalidasi'
            && currentUserRole() !== ROLE_SUPER_ADMIN) {
            denyAccess("Pengeluaran barang '{$row['stock_out_number']}' sudah divalidasi -- tidak bisa {$verb}. Hubungi Super Admin bila perlu koreksi.");
        }
    }

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
