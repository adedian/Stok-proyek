<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Inventory.php';
require_once ROOT_PATH . '/app/models/StockTransaction.php';
require_once ROOT_PATH . '/app/models/StockOpname.php';
require_once ROOT_PATH . '/app/models/StockOpnameItem.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class InventoryController extends Controller
{
    private Inventory $inventoryModel;
    private StockTransaction $transactionModel;
    private StockOpname $opnameModel;
    private StockOpnameItem $opnameItemModel;
    private Project $projectModel;
    private User $userModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('inventory', 'view');

        $this->inventoryModel  = new Inventory();
        $this->transactionModel = new StockTransaction();
        $this->opnameModel     = new StockOpname();
        $this->opnameItemModel = new StockOpnameItem();
        $this->projectModel    = new Project();
        $this->userModel       = new User();
        $this->activityLog     = new ActivityLog();
    }

    /**
     * Kartu stok realtime: semua item + filter project/keyword/status stok
     */
    public function index()
    {
        $filters = [
            'project_id'    => $_GET['project_id'] ?? '',
            'stock_type'    => $_GET['stock_type'] ?? '',
            'keyword'       => $_GET['keyword'] ?? '',
            'stock_filter'  => $_GET['stock_filter'] ?? '',
        ];

        $items = $this->inventoryModel->listWithFilters($filters);
        $projects = $this->projectModel->activeList();

        $this->view('inventory/list', [
            'pageTitle' => 'Stok Barang',
            'items'     => $items,
            'projects'  => $projects,
            'filters'   => $filters,
        ]);
    }

    /**
     * Kartu stok / mutasi satu item barang
     */
    public function history()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $item = $this->inventoryModel->findWithRelations($id);

        if (!$item) {
            setFlash('error', 'Data barang tidak ditemukan.');
            $this->redirect('inventory', 'index');
        }

        $transactions = $this->transactionModel->historyByInventory($id);

        $this->view('inventory/history', [
            'pageTitle'    => 'Kartu Stok - ' . $item['item_name'],
            'item'         => $item,
            'transactions' => $transactions,
        ]);
    }

    /**
     * Daftar stock opname
     */
    public function opnameIndex()
    {
        $filters = [
            'project_id'  => $_GET['project_id'] ?? '',
            'stock_type'  => $_GET['stock_type'] ?? '',
            'status'      => $_GET['status'] ?? '',
        ];

        $opnames = $this->opnameModel->listWithRelations($filters);
        $projects = $this->projectModel->activeList();

        $this->view('inventory/opname_list', [
            'pageTitle'        => 'Stok Opname',
            'opnames'          => $opnames,
            'projects'         => $projects,
            'filters'          => $filters,
            'statusLabels'     => $this->opnameModel->statusLabels,
            'statusBadgeClass' => $this->opnameModel->statusBadgeClass,
            'stockTypeLabels'  => $this->opnameModel->stockTypeLabels,
        ]);
    }

    /**
     * Form tambah opname. Pilih Jenis Stok (Stok Proyek / Stok Lampu / Inventory
     * Kantor) + Project OPSIONAL -> semua baris inventory jenis itu di-prefill
     * sebagai input qty_actual (qty_system diambil otomatis dari Inventory,
     * sumber kebenaran yang sama dengan halaman Stok Barang). Tanpa Project =
     * hitung semua baris jenis stok itu lintas project + kantor.
     */
    public function opnameCreate()
    {
        Middleware::requirePermission('inventory', 'create');

        $stockType = normalizeStockType($_GET['stock_type'] ?? 'stok_proyek');
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $projectIdOrNull = $projectId > 0 ? $projectId : null;

        $items = $this->inventoryModel->forOpname($stockType, $projectIdOrNull);

        $this->view('inventory/opname_form', [
            'pageTitle'   => 'Tambah Stok Opname',
            'opnameNumber' => $this->opnameModel->previewOpnameNumber(),
            'projects'    => $this->projectModel->activeList(),
            'selectedStockType'  => $stockType,
            'selectedProjectId'  => $projectId,
            'items'       => $items,
            'picUsers'    => $this->userModel->activeList(),
            'stockTypeLabels' => $this->opnameModel->stockTypeLabels,
        ]);
    }

    public function opnameStore()
    {
        Middleware::requirePermission('inventory', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('inventory', 'opnameCreate');
        }
        verifyCsrf();

        $stockType = normalizeStockType($_POST['stock_type'] ?? 'stok_proyek');
        $projectId = (int) ($_POST['project_id'] ?? 0);
        $projectIdOrNull = $projectId > 0 ? $projectId : null;
        // stock_scope (kolom lama) diturunkan dari ada/tidaknya Project -- Project
        // kini opsional untuk SEMUA jenis stok.
        $stockScope = $projectId > 0 ? 'proyek' : 'kantor';
        $opnameDate = $_POST['opname_date'] ?? '';
        $notes = trim($_POST['notes'] ?? '');
        // array_unique: jaga-jaga terhadap submit ganda (mis. double-click / replay form)
        // supaya satu inventory_id tidak pernah tercatat 2x sebagai baris item opname yang sama.
        $inventoryIds = array_unique(array_map('intval', $_POST['inventory_id'] ?? []));
        $qtyActualsRaw = $_POST['qty_actual'] ?? [];
        // Petakan qty_actual berdasarkan inventory_id (bukan index array) supaya tetap
        // benar walau ada baris duplikat/kosong yang dibuang oleh array_unique di atas.
        $qtyActualByInventoryId = [];
        foreach ($_POST['inventory_id'] ?? [] as $i => $rawId) {
            $qtyActualByInventoryId[(int) $rawId] = (float) ($qtyActualsRaw[$i] ?? 0);
        }

        $errors = [];
        if (empty($opnameDate)) {
            $errors[] = 'Tanggal opname wajib diisi.';
        }
        if (empty($inventoryIds)) {
            $errors[] = 'Tidak ada item barang untuk bucket stok ini.';
        }
        foreach ($qtyActualByInventoryId as $qty) {
            if ($qty < 0) {
                $errors[] = 'Stok fisik tidak boleh negatif.';
                break;
            }
        }

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('inventory', 'opnameCreate', ['stock_type' => $stockType, 'project_id' => $projectId]);
        }

        assertPeriodOpen('stock_opname', $opnameDate, 'inventory', 'opnameCreate', ['stock_type' => $stockType, 'project_id' => $projectId]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $opnameId = $this->opnameModel->create([
                'opname_number' => $this->opnameModel->generateOpnameNumber(),
                'project_id'    => $projectIdOrNull,
                'stock_scope'   => $stockScope,
                'stock_type'    => $stockType,
                'opname_date'   => $opnameDate,
                'status'        => 'draft',
                'notes'         => $notes,
                'created_by'    => currentUserId(),
            ]);

            foreach ($inventoryIds as $inventoryId) {
                if ($inventoryId <= 0) {
                    continue;
                }
                $inventoryItem = $this->inventoryModel->find($inventoryId);
                if (!$inventoryItem) {
                    continue;
                }
                $qtySystem = (float) $inventoryItem['qty_available'];
                $qtyActual = $qtyActualByInventoryId[$inventoryId] ?? $qtySystem;

                $this->opnameItemModel->create([
                    'stock_opname_id' => $opnameId,
                    'inventory_id'    => $inventoryId,
                    'qty_system'      => $qtySystem,
                    'qty_actual'      => $qtyActual,
                    'difference'      => $qtyActual - $qtySystem,
                    'created_by'      => currentUserId(),
                ]);
            }

            $scopeDesc = stockTypeLabel($stockType) . ($projectId > 0 ? " (project #{$projectId})" : ' (semua project + kantor)');
            $this->activityLog->log(
                currentUserId(),
                'stock_opname',
                'create',
                "Stok opname dibuat untuk {$scopeDesc}"
            );

            $pdo->commit();

            setFlash('success', 'Stok opname berhasil disimpan sebagai draft.');
            $this->redirect('inventory', 'opnameDetail', ['id' => $opnameId]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Stock opname store error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyimpan stok opname. Silakan coba lagi.');
            $this->redirect('inventory', 'opnameCreate', ['stock_type' => $stockType, 'project_id' => $projectId]);
        }
    }

    public function opnameDetail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $opname = $this->opnameModel->findWithRelations($id);

        if (!$opname) {
            setFlash('error', 'Data stok opname tidak ditemukan.');
            $this->redirect('inventory', 'opnameIndex');
        }

        $items = $this->opnameItemModel->itemsByOpname($id);

        $this->view('inventory/opname_detail', [
            'pageTitle'        => 'Detail Stok Opname',
            'opname'           => $opname,
            'items'            => $items,
            'statusLabels'     => $this->opnameModel->statusLabels,
            'statusBadgeClass' => $this->opnameModel->statusBadgeClass,
            'stockTypeLabels'  => $this->opnameModel->stockTypeLabels,
        ]);
    }

    /**
     * Selesaikan opname: kunci status jadi 'completed' dan terapkan penyesuaian
     * stok untuk tiap item yang selisih (qty_available disamakan dengan qty_actual).
     */
    public function opnameComplete()
    {
        Middleware::requirePermission('inventory', 'complete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('inventory', 'opnameIndex');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $opname = $this->opnameModel->find($id);

        if (!$opname) {
            setFlash('error', 'Data stok opname tidak ditemukan.');
            $this->redirect('inventory', 'opnameIndex');
        }
        if ($opname['status'] === 'completed') {
            setFlash('error', 'Stok opname ini sudah selesai sebelumnya.');
            $this->redirect('inventory', 'opnameDetail', ['id' => $id]);
        }

        assertPeriodOpen('stock_opname', $opname['opname_date'], 'inventory', 'opnameDetail', ['id' => $id]);

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();

            $items = $this->opnameItemModel->itemsByOpname($id);
            foreach ($items as $item) {
                if ((float) $item['difference'] == 0) {
                    continue;
                }
                $this->inventoryModel->adjustToActual(
                    (int) $item['inventory_id'],
                    (float) $item['qty_actual'],
                    'stock_opname',
                    $id,
                    currentUserId(),
                    "Penyesuaian dari stok opname {$opname['opname_number']}"
                );
            }

            $this->opnameModel->updateById($id, ['status' => 'completed']);

            $this->activityLog->log(
                currentUserId(),
                'stock_opname',
                'complete',
                "Stok opname {$opname['opname_number']} diselesaikan, stok disesuaikan"
            );

            $pdo->commit();
            setFlash('success', 'Stok opname diselesaikan, stok sistem sudah disesuaikan.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Stock opname complete error: ' . $e->getMessage());
            setFlash('error', 'Gagal menyelesaikan stok opname.');
        }

        $this->redirect('inventory', 'opnameDetail', ['id' => $id]);
    }

    /**
     * Hapus stok opname.
     * - Draft: belum pernah menyentuh stok (penyesuaian baru diterapkan saat
     *   opnameComplete()), jadi cukup soft-delete. Boleh Super Admin/Gudang.
     * - Selesai: PERNAH menyesuaikan qty_available inventory. Menghapusnya berarti
     *   membatalkan penyesuaian itu -- operasi sensitif yang mengubah stok riil,
     *   jadi dibatasi Super Admin saja (sama seperti hapus baris kartu stok /
     *   'delete_stock') dan WAJIB mencatat transaksi pembalik di stock_transactions
     *   supaya kartu stok tetap bisa ditelusuri (bukan diam-diam mengubah angka).
     */
    public function opnameDelete()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('inventory', 'opnameIndex');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $opname = $this->opnameModel->find($id);

        if (!$opname) {
            setFlash('error', 'Data stok opname tidak ditemukan.');
            $this->redirect('inventory', 'opnameIndex');
        }

        assertPeriodOpen('stock_opname', $opname['opname_date'], 'inventory', 'opnameDetail', ['id' => $id]);

        $res = $this->deleteOneOpname($id);
        if ($res === true) {
            setFlash('success', $opname['status'] === 'completed'
                ? 'Stok opname berhasil dihapus. Penyesuaian stok yang sudah diterapkan telah dibatalkan.'
                : 'Stok opname berhasil dihapus.');
        } else {
            setFlash('error', 'Gagal menghapus stok opname.');
        }

        $this->redirect('inventory', 'opnameIndex');
    }

    /**
     * Hapus 1 stok opname ke Tempat Sampah. Kalau statusnya "completed",
     * penyesuaian stok yang dulu diterapkan dibatalkan (transaksi pembalik di
     * stock_transactions). true = sukses, string = alasan skip. Dipakai
     * opnameDelete() & opnameRangeDelete().
     */
    private function deleteOneOpname(int $id)
    {
        $opname = $this->opnameModel->find($id);
        if (!$opname) {
            return 'gagal';
        }
        // Soft-delete ke Tempat Sampah aman walau periode terkunci (pembatalan
        // penyesuaian dicatat tanggal-sekarang) -- gerbang Tutup Bulan tetap
        // berlaku untuk hapus per-baris lewat opnameDelete().

        if ($opname['status'] !== 'completed') {
            Middleware::requirePermission('inventory', 'delete');
            try {
                $this->opnameModel->deleteById($id);
                $this->activityLog->log(currentUserId(), 'stock_opname', 'delete', "Stok opname {$opname['opname_number']} dihapus");
                return true;
            } catch (Throwable $e) {
                error_log('Stock opname deleteOne error: ' . $e->getMessage());
                return 'gagal';
            }
        }

        Middleware::requirePermission('inventory', 'delete_stock');
        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $items = $this->opnameItemModel->itemsByOpname($id);
            foreach ($items as $item) {
                $difference = (float) $item['difference'];
                if ($difference == 0) {
                    continue;
                }
                $inventoryItem = $this->inventoryModel->find((int) $item['inventory_id']);
                if (!$inventoryItem) {
                    continue; // baris inventory-nya sendiri sudah dihapus, tidak ada yang bisa dibalik
                }
                // Balikkan selisih yang dulu diterapkan opnameComplete() (bukan reset ke
                // qty_system, supaya mutasi stok lain setelah opname ini tidak ikut hilang).
                $reverted = (float) $inventoryItem['qty_available'] - $difference;
                $this->inventoryModel->adjustToActual(
                    (int) $item['inventory_id'],
                    $reverted,
                    'stock_opname_delete',
                    $id,
                    currentUserId(),
                    "Pembatalan penyesuaian stok opname {$opname['opname_number']} (dihapus)"
                );
            }
            $this->opnameModel->deleteById($id);
            $this->activityLog->log(
                currentUserId(),
                'stock_opname',
                'delete',
                "Stok opname {$opname['opname_number']} (Selesai) dihapus, penyesuaian stok dibatalkan"
            );
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Stock opname deleteOne (completed) error: ' . $e->getMessage());
            return 'gagal';
        }
    }

    /** Hapus semua stok opname dalam rentang tanggal ke Tempat Sampah -- KHUSUS Super Admin. */
    public function opnameRangeDelete()
    {
        rangeDeleteGuardSuperAdmin();
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('inventory', 'opnameIndex');
        }
        verifyCsrf();

        [$from, $to] = rangeDeleteReadDates();
        if ($err = rangeDeleteValidate($from, $to)) {
            setFlash('error', $err);
            $this->redirect('inventory', 'opnameIndex');
        }

        $deleted = 0;
        $skipped = [];
        foreach ($this->opnameModel->idsByDateRange('opname_date', $from, $to) as $id) {
            $r = $this->deleteOneOpname($id);
            if ($r === true) {
                $deleted++;
            } else {
                $skipped[$r] = ($skipped[$r] ?? 0) + 1;
            }
        }

        rangeDeleteLog('stock_opname', $from, $to, $deleted, array_sum($skipped));
        rangeDeleteFlash($deleted, $skipped);
        $this->redirect('inventory', 'opnameIndex');
    }

    /**
     * Hapus baris kartu stok -- khusus Super Admin. Dipakai untuk membersihkan
     * baris stok yang barangnya sudah dihapus dari Master Data / sudah tidak
     * relevan. Kalau qty belum 0, adjustment penutup otomatis dicatat dulu
     * (lihat Inventory::deleteWithAdjustment()) supaya kartu stok tetap lengkap.
     */
    public function deleteItem()
    {
        Middleware::requirePermission('inventory', 'delete_stock');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('inventory', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $item = $this->inventoryModel->find($id);

        if (!$item) {
            setFlash('error', 'Data stok barang tidak ditemukan.');
            $this->redirect('inventory', 'index');
        }

        $this->inventoryModel->deleteWithAdjustment($id, currentUserId());

        $this->activityLog->log(
            currentUserId(),
            'inventory',
            'delete',
            "Baris stok '{$item['item_name']}' dihapus (qty terakhir: {$item['qty_available']} {$item['unit']})"
        );

        setFlash('success', "Baris stok '{$item['item_name']}' berhasil dihapus.");
        $this->redirect('inventory', 'index');
    }

    /**
     * AJAX: ambil daftar baris inventory untuk form Stok Opname (dipakai saat user
     * ganti Jenis Stok / Project tanpa reload). Project opsional: tanpa Project =
     * semua baris jenis stok itu (lintas project + kantor).
     */
    public function ajaxItemsByProject()
    {
        $stockType = normalizeStockType($_GET['stock_type'] ?? 'stok_proyek');
        $projectId = (int) ($_GET['project_id'] ?? 0);
        $projectIdOrNull = $projectId > 0 ? $projectId : null;

        $items = $this->inventoryModel->forOpname($stockType, $projectIdOrNull);

        $this->json(['items' => $items]);
    }
}
