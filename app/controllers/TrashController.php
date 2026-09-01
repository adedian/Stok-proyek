<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/Supplier.php';
require_once ROOT_PATH . '/app/models/Client.php';
require_once ROOT_PATH . '/app/models/Warehouse.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/Payment.php';
require_once ROOT_PATH . '/app/models/GoodsReceipt.php';
require_once ROOT_PATH . '/app/models/StockOut.php';
require_once ROOT_PATH . '/app/models/StockOpname.php';
require_once ROOT_PATH . '/app/models/SalesInvoice.php';
require_once ROOT_PATH . '/app/models/OfflinePurchase.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/PaymentMethod.php';
require_once ROOT_PATH . '/app/models/DeliveryNote.php';
require_once ROOT_PATH . '/app/models/CollectionReceipt.php';
require_once ROOT_PATH . '/app/models/CashTransaction.php';
require_once ROOT_PATH . '/app/models/CashCategory.php';

/**
 * TrashController
 * Tempat Sampah gabungan lintas modul. Semua modul di bawah SUDAH soft-delete
 * lewat core/Model.php ($softDelete=true, kolom deleted_at/deleted_by) --
 * controller ini cuma membaca/restore/hapus-permanen baris yang sudah
 * dihapus, tidak menduplikasi logic hapus modul aslinya masing-masing.
 *
 * Modul yang TIDAK ada di sini SENGAJA dikecualikan karena memang tidak
 * punya aksi hapus sama sekali: ValidationController (approve/reject,
 * bukan delete) dan UserController (akun dinonaktifkan via status, bukan
 * dihapus -- banyak tabel lain FK ke created_by/deleted_by).
 */
class TrashController extends Controller
{
    private ActivityLog $activityLog;
    private array $modules;

    public function __construct()
    {
        Middleware::requirePermission('trash', 'view');
        $this->activityLog = new ActivityLog();
        $this->modules = [
            'item' => [
                'label' => 'Barang',
                'model' => new Item(),
                'display' => fn(array $r) => trim(($r['item_code'] ?? '') . ' - ' . ($r['item_name'] ?? ''), ' -'),
            ],
            'supplier' => [
                'label' => 'Supplier',
                'model' => new Supplier(),
                'display' => fn(array $r) => trim(($r['supplier_code'] ?? '') . ' - ' . ($r['supplier_name'] ?? ''), ' -'),
            ],
            'client' => [
                'label' => 'Client',
                'model' => new Client(),
                'display' => fn(array $r) => trim(($r['client_code'] ?? '') . ' - ' . ($r['client_name'] ?? ''), ' -'),
            ],
            'warehouse' => [
                'label' => 'Gudang',
                'model' => new Warehouse(),
                'display' => fn(array $r) => trim(($r['warehouse_code'] ?? '') . ' - ' . ($r['warehouse_name'] ?? ''), ' -'),
            ],
            'project' => [
                'label' => 'Project',
                'model' => new Project(),
                'display' => fn(array $r) => trim(($r['project_code'] ?? '') . ' - ' . ($r['project_name'] ?? ''), ' -'),
            ],
            'purchase_order' => [
                'label' => 'Purchase Order',
                'model' => new PurchaseOrder(),
                'display' => fn(array $r) => $r['po_number'] ?? '-',
            ],
            'payment' => [
                'label' => 'Pembayaran PO',
                'model' => new Payment(),
                'display' => fn(array $r) => $r['payment_number'] ?? '-',
            ],
            'goods_receipt' => [
                'label' => 'Penerimaan Barang',
                'model' => new GoodsReceipt(),
                'display' => fn(array $r) => $r['receipt_number'] ?? '-',
            ],
            'stock_out' => [
                'label' => 'Pengeluaran Barang',
                'model' => new StockOut(),
                'display' => fn(array $r) => $r['stock_out_number'] ?? '-',
            ],
            // 'inventory' (Kartu Stok) SENGAJA tidak di sini: baris ledger stok
            // adalah turunan otomatis dari GR/Pengeluaran/Opname -- ikut terhapus
            // & terpulihkan bersama dokumen induknya, bukan data yang dikelola
            // user langsung dari Tempat Sampah.
            'stock_opname' => [
                'label' => 'Stok Opname',
                'model' => new StockOpname(),
                'display' => fn(array $r) => $r['opname_number'] ?? '-',
            ],
            'sales_invoice' => [
                'label' => 'Invoice Keluar',
                'model' => new SalesInvoice(),
                'display' => fn(array $r) => $r['invoice_number'] ?? '-',
            ],
            'offline_purchase' => [
                'label' => 'Pembelian Offline',
                'model' => new OfflinePurchase(),
                'display' => fn(array $r) => $r['purchase_number'] ?? '-',
            ],
            'item_category' => [
                'label' => 'Kategori Barang',
                'model' => new ItemCategory(),
                'display' => fn(array $r) => $r['category_name'] ?? '-',
            ],
            'unit' => [
                'label' => 'Satuan',
                'model' => new Unit(),
                'display' => fn(array $r) => $r['unit_name'] ?? '-',
            ],
            'payment_method' => [
                'label' => 'Metode Pembayaran',
                'model' => new PaymentMethod(),
                'display' => fn(array $r) => $r['method_name'] ?? '-',
            ],
            'delivery_note' => [
                'label' => 'Surat Jalan',
                'model' => new DeliveryNote(),
                'display' => fn(array $r) => $r['delivery_number'] ?? '-',
            ],
            'collection_receipt' => [
                'label' => 'Pembayaran Invoice',
                'model' => new CollectionReceipt(),
                'display' => fn(array $r) => $r['receipt_number'] ?? '-',
            ],
            'cash' => [
                'label' => 'Kas',
                'model' => new CashTransaction(),
                'display' => fn(array $r) => trim(($r['no_bukti'] ?? '') . ' - ' . ($r['uraian'] ?? ''), ' -'),
            ],
            'cash_category' => [
                'label' => 'Kategori Kas',
                'model' => new CashCategory(),
                'display' => fn(array $r) => $r['category_name'] ?? '-',
            ],
        ];
    }

    public function index()
    {
        $moduleFilter = trim($_GET['module_filter'] ?? '');

        $userMap = [];
        foreach ((new User())->all() as $u) {
            $userMap[(int) $u['id']] = $u['full_name'];
        }

        $rows = [];
        foreach ($this->modules as $key => $cfg) {
            if ($moduleFilter !== '' && $moduleFilter !== $key) {
                continue;
            }
            foreach ($cfg['model']->trashedList() as $r) {
                // Hanya tampilkan yang BENAR-BENAR bisa dihapus permanen -- baris
                // yang masih dirujuk transaksi lain (FK) belum benar-benar
                // terhapus dari sistem, jadi disembunyikan dari daftar.
                if ($cfg['model']->isReferenced((int) $r['id'])) {
                    continue;
                }
                $rows[] = [
                    'module'       => $key,
                    'module_label' => $cfg['label'],
                    'display'      => ($cfg['display'])($r),
                    'id'           => (int) $r['id'],
                    'deleted_at'   => $r['deleted_at'],
                    'deleted_by'   => $userMap[(int) ($r['deleted_by'] ?? 0)] ?? '-',
                ];
            }
        }

        usort($rows, fn($a, $b) => strcmp((string) $b['deleted_at'], (string) $a['deleted_at']));

        $moduleOptions = [];
        foreach ($this->modules as $key => $cfg) {
            $moduleOptions[$key] = $cfg['label'];
        }

        $this->view('trash/index', [
            'pageTitle'     => 'Tempat Sampah',
            'rows'          => $rows,
            'moduleFilter'  => $moduleFilter,
            'moduleOptions' => $moduleOptions,
        ]);
    }

    public function restore()
    {
        Middleware::requirePermission('trash', 'restore');
        $this->requirePost();

        $module = trim($_POST['module'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $cfg = $this->resolveModule($module);
        $row = $cfg['model']->findTrashed($id);

        if (!$row) {
            setFlash('error', 'Data tidak ditemukan di Tempat Sampah.');
            $this->redirect('trash', 'index');
        }

        $pdo = getPDO();
        try {
            $pdo->beginTransaction();
            $cfg['model']->restoreById($id);

            // Kas dengan baris ber-kategori stok: stok dikembalikan saat masuk
            // Tempat Sampah, jadi saat DIPULIHKAN stok dikreditkan lagi
            // (idempotent via cash_transaction_items.stock_posted_at). No-op
            // kalau transaksi ini tidak menyentuh stok.
            $stockCredited = 0;
            if ($module === 'cash') {
                $stockCredited = $cfg['model']->applyStockCredit($id);
            }

            $this->activityLog->log(
                currentUserId(),
                $module,
                'restore',
                "{$cfg['label']} '" . ($cfg['display'])($row) . "' dipulihkan dari Tempat Sampah"
                    . ($stockCredited > 0 ? ' (stok dikreditkan ulang)' : '')
            );
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('Trash restore error: ' . $e->getMessage());
            setFlash('error', 'Gagal memulihkan data.');
            $this->redirect('trash', 'index');
        }

        setFlash('success', 'Data berhasil dipulihkan.');
        $this->redirect('trash', 'index');
    }

    /**
     * Hapus permanen SEMUA isi Tempat Sampah (opsional dibatasi module_filter).
     * Baris yang masih dipakai transaksi lain (FK constraint) dilewati dan
     * dihitung terpisah -- tidak menggagalkan yang lain.
     */
    public function forceDeleteAll()
    {
        Middleware::requirePermission('trash', 'force_delete');
        $this->requirePost();

        $moduleFilter = trim($_POST['module_filter'] ?? '');
        $deleted = 0;
        $skipped = 0;

        foreach ($this->modules as $key => $cfg) {
            if ($moduleFilter !== '' && $moduleFilter !== $key) {
                continue;
            }
            foreach ($cfg['model']->trashedList() as $r) {
                // Baris yang masih dirujuk transaksi lain tidak tampil di Tempat
                // Sampah (lihat index()), jadi jangan ikut dihitung "dilewati".
                if ($cfg['model']->isReferenced((int) $r['id'])) {
                    continue;
                }
                try {
                    $cfg['model']->forceDeleteById((int) $r['id']);
                    $deleted++;
                } catch (PDOException $e) {
                    // Jaring pengaman kalau ada relasi yang lolos cek di atas
                    // (mis. race condition) -- tetap tidak menggagalkan yang lain.
                    $skipped++;
                }
            }
        }

        $this->activityLog->log(
            currentUserId(),
            'trash',
            'force_delete',
            "Kosongkan Tempat Sampah" . ($moduleFilter !== '' ? " (modul: {$moduleFilter})" : '')
                . " -- {$deleted} dihapus permanen, {$skipped} dilewati (masih dipakai)"
        );

        if ($deleted === 0 && $skipped === 0) {
            setFlash('error', 'Tidak ada data untuk dihapus.');
        } elseif ($skipped === 0) {
            setFlash('success', "{$deleted} data berhasil dihapus permanen.");
        } else {
            setFlash('success', "{$deleted} data dihapus permanen. {$skipped} data dilewati karena masih dipakai transaksi lain.");
        }

        $this->redirect('trash', 'index', $moduleFilter !== '' ? ['module_filter' => $moduleFilter] : []);
    }

    public function forceDelete()
    {
        Middleware::requirePermission('trash', 'force_delete');
        $this->requirePost();

        $module = trim($_POST['module'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $cfg = $this->resolveModule($module);
        $row = $cfg['model']->findTrashed($id);

        if (!$row) {
            setFlash('error', 'Data tidak ditemukan di Tempat Sampah.');
            $this->redirect('trash', 'index');
        }

        try {
            $cfg['model']->forceDeleteById($id);
            $this->activityLog->log(
                currentUserId(),
                $module,
                'force_delete',
                "{$cfg['label']} '" . ($cfg['display'])($row) . "' dihapus permanen"
            );
            setFlash('success', 'Data berhasil dihapus permanen.');
        } catch (PDOException $e) {
            setFlash('error', 'Data masih memiliki relasi transaksi (PO/Invoice/Stok/dll), tidak bisa dihapus permanen.');
        }

        $this->redirect('trash', 'index');
    }

    // ================= Helper privat =================

    private function requirePost(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('trash', 'index');
        }
        verifyCsrf();
    }

    private function resolveModule(string $module): array
    {
        if (!isset($this->modules[$module])) {
            setFlash('error', 'Modul tidak dikenal.');
            $this->redirect('trash', 'index');
        }
        return $this->modules[$module];
    }
}
