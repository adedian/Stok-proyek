<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/Unit.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/CodeConfig.php';

class ItemController extends Controller
{
    private Item $itemModel;
    private ItemCategory $categoryModel;
    private Unit $unitModel;
    private ActivityLog $activityLog;
    private CodeConfig $codeConfig;

    /**
     * Jenis Stok (items.stock_type) -> kelompok Master Kode (code_configs.entity_type)
     * -- lihat app/models/CodeConfig.php untuk kenapa Barang dipecah 3 kelompok kode.
     * NILAI HARUS SAMA dengan helper global stockTypeLabels() (sumber tunggal yang
     * dipakai Stok Opname / Penerimaan Barang / Laporan Stok Barang). PHP tidak
     * mengizinkan pemanggilan fungsi di ekspresi const, jadi didefinisikan ulang
     * di sini sebagai cermin enum `items.stock_type`.
     */
    public const STOCK_TYPE_LABELS = [
        'stok_proyek'      => 'Stok Proyek',
        'stok_lampu'       => 'Stok Lampu',
        'inventory_kantor' => 'Inventory Kantor',
    ];

    private const STOCK_TYPE_CODE_ENTITY = [
        'stok_proyek'      => 'item_stok_proyek',
        'stok_lampu'       => 'item_stok_lampu',
        'inventory_kantor' => 'item_inventory_kantor',
    ];

    public function __construct()
    {
        // quickStore() dicek permission-nya sendiri (lebih longgar, lihat method-nya) --
        // jangan diblokir lebih dulu di sini oleh gate 'view' yang Super Admin-only.
        if (($_GET['action'] ?? 'index') !== 'quickStore') {
            Middleware::requirePermission('item', 'view');
        }

        $this->itemModel     = new Item();
        $this->categoryModel = new ItemCategory();
        $this->unitModel     = new Unit();
        $this->activityLog   = new ActivityLog();
        $this->codeConfig    = new CodeConfig();
    }

    public function index()
    {
        $filters = [
            'keyword'     => trim($_GET['keyword'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
            'status'      => $_GET['status'] ?? '',
            'stock_type'  => $_GET['stock_type'] ?? '',
        ];
        $sort = $_GET['sort'] ?? 'item_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->itemModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $items = $this->itemModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'item', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('item/list', [
            'pageTitle'       => 'Barang',
            'items'           => $items,
            'filters'         => $filters,
            'sort'            => $sort,
            'dir'             => $dir,
            'pagination'      => $pg,
            'baseQuery'       => $baseQuery,
            'categories'      => $this->categoryModel->activeList(),
            'stockTypeLabels' => self::STOCK_TYPE_LABELS,
        ]);
    }

    public function exportCsv()
    {
        $filters = [
            'keyword'     => trim($_GET['keyword'] ?? ''),
            'category_id' => $_GET['category_id'] ?? '',
            'status'      => $_GET['status'] ?? '',
            'stock_type'  => $_GET['stock_type'] ?? '',
        ];
        $rows = $this->itemModel->listPaginated($filters, 'item_name', 'asc', 10000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="barang_' . date('Ymd_His') . '.csv"');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Kode', 'Nama Barang', 'Jenis Stok', 'Kategori', 'Satuan', 'Spesifikasi', 'Tersedia', 'Stok Minimum', 'Status']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['item_code'], $r['item_name'], self::STOCK_TYPE_LABELS[$r['stock_type']] ?? $r['stock_type'], $r['category_name'] ?? '-', $r['unit_name'],
                $r['specification'], $r['total_available'] ?? 0, $r['min_stock'], $r['status'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function create()
    {
        Middleware::requirePermission('item', 'create');

        $codeConfigs = [];
        $codePrefixes = [];
        $codeMasterCodes = [];
        foreach (self::STOCK_TYPE_CODE_ENTITY as $stockType => $entityType) {
            $codeConfigs[$stockType]     = $this->codeConfig->getConfig($entityType);
            $codePrefixes[$stockType]    = $this->codeConfig->configsForEntity($entityType);
            $codeMasterCodes[$stockType] = $this->codeConfig->masterCodeForEntity($entityType);
        }

        $this->view('item/form', [
            'pageTitle'       => 'Tambah Barang',
            'mode'            => 'create',
            'item'            => null,
            'categories'      => $this->categoryModel->activeList(),
            'units'           => $this->unitModel->activeList(),
            'stockTypeLabels' => self::STOCK_TYPE_LABELS,
            'codeConfigs'     => $codeConfigs,
            'codePrefixes'    => $codePrefixes,
            'codeMasterCodes' => $codeMasterCodes,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('item', 'create');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('item', 'create');
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('item', 'create');
        }

        $codeEntityType = self::STOCK_TYPE_CODE_ENTITY[$data['stock_type']];
        $codePrefix = trim($_POST['code_prefix'] ?? '');
        $itemCode = $this->codeConfig->nextCode($codeEntityType, $codePrefix !== '' ? $codePrefix : null);
        if ($itemCode === null) {
            $label = self::STOCK_TYPE_LABELS[$data['stock_type']];
            setFlash('error', "Prefix kode Barang - {$label} belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Barang - {$label}.");
            $this->redirect('item', 'create');
        }

        $this->itemModel->create(array_merge($data, [
            'item_code'  => $itemCode,
            'created_by' => currentUserId(),
        ]));

        $this->activityLog->log(currentUserId(), 'item', 'create', "Barang '{$data['item_name']}' dibuat");
        setFlash('success', 'Barang berhasil ditambahkan.');
        $this->redirect('item', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('item', 'edit');

        $id = (int) ($_GET['id'] ?? 0);
        $item = $this->itemModel->findWithRelations($id);

        if (!$item) {
            setFlash('error', 'Barang tidak ditemukan.');
            $this->redirect('item', 'index');
        }

        $this->view('item/form', [
            'pageTitle'       => 'Edit Barang',
            'mode'            => 'edit',
            'item'            => $item,
            'categories'      => $this->categoryModel->activeList(),
            'units'           => $this->unitModel->activeList(),
            'stockTypeLabels' => self::STOCK_TYPE_LABELS,
            'codeConfigs'     => [],
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('item', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('item', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->itemModel->find($id);

        if (!$existing) {
            setFlash('error', 'Barang tidak ditemukan.');
            $this->redirect('item', 'index');
        }

        $data = $this->collectInput();
        $errors = $this->validateInput($data, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('item', 'edit', ['id' => $id]);
        }

        $this->itemModel->updateById($id, $data);
        $this->activityLog->log(currentUserId(), 'item', 'update', "Barang '{$data['item_name']}' diperbarui");
        setFlash('success', 'Barang berhasil diperbarui.');
        $this->redirect('item', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('item', 'delete');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('item', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $item = $this->itemModel->find($id);

        if ($item) {
            $this->itemModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'item', 'delete', "Barang '{$item['item_name']}' dihapus");
            setFlash('success', 'Barang berhasil dihapus.');
        } else {
            setFlash('error', 'Barang tidak ditemukan.');
        }

        $this->redirect('item', 'index');
    }

    /**
     * AJAX quick-add dipanggil dari baris item form Purchase Order.
     * Lihat 'item'.'quick_add' di config/permissions.php.
     */
    public function quickStore()
    {
        Middleware::requirePermission('item', 'quick_add');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $data = $this->collectInput();
        $errors = $this->validateInput($data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
        }

        $codeEntityType = self::STOCK_TYPE_CODE_ENTITY[$data['stock_type']];
        $codePrefix = trim($_POST['code_prefix'] ?? '');
        $itemCode = $this->codeConfig->nextCode($codeEntityType, $codePrefix !== '' ? $codePrefix : null);
        if ($itemCode === null) {
            $label = self::STOCK_TYPE_LABELS[$data['stock_type']];
            $this->json(['errors' => ["Prefix kode Barang - {$label} belum dikonfigurasi. Silakan konfigurasi melalui Master Kode > Barang - {$label}."]], 422);
        }

        $id = $this->itemModel->create(array_merge($data, [
            'item_code'  => $itemCode,
            'created_by' => currentUserId(),
        ]));

        $unit = $this->unitModel->find($data['unit_id']);

        $this->activityLog->log(
            currentUserId(),
            'item',
            'quick_add',
            "Barang '{$data['item_name']}' ({$itemCode}) ditambahkan cepat dari form lain"
        );

        $this->json([
            'id'        => $id,
            // 'label' HANYA nama barang (sama seperti opsi lain di dropdown Barang) --
            // JANGAN diselipi satuan di sini, karena JS di form PO menyalin teks opsi
            // ini apa adanya ke hidden item_name[] yang dikirim ke server.
            'label'     => $data['item_name'],
            'unit_id'   => $data['unit_id'],
            'unit_name' => $unit['unit_name'] ?? '',
            'item_code' => $itemCode,
        ]);
    }

    // ================= Helper privat =================

    private function collectInput(): array
    {
        $status = $_POST['status'] ?? 'active';
        // Default 'stok_proyek' kalau field tidak dikirim -- dipakai quickStore()
        // (modal quick-add dari form PO dkk. tidak punya field Jenis Stok, konteksnya
        // memang selalu pengadaan barang proyek).
        $stockType = $_POST['stock_type'] ?? 'stok_proyek';

        return [
            'item_name'     => trim($_POST['item_name'] ?? ''),
            'category_id'   => !empty($_POST['category_id']) ? (int) $_POST['category_id'] : null,
            'stock_type'    => array_key_exists($stockType, self::STOCK_TYPE_LABELS) ? $stockType : 'stok_proyek',
            'unit_id'       => (int) ($_POST['unit_id'] ?? 0),
            'specification' => trim($_POST['specification'] ?? ''),
            'min_stock'     => (float) ($_POST['min_stock'] ?? 0),
            'status'        => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
        ];
    }

    private function validateInput(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if ($data['item_name'] === '') {
            $errors[] = 'Nama barang wajib diisi.';
        } elseif ($this->itemModel->nameExists($data['item_name'], $excludeId)) {
            $errors[] = "Data dengan nama '{$data['item_name']}' sudah tersedia.";
        }
        if ($data['unit_id'] <= 0) {
            $errors[] = 'Satuan wajib dipilih.';
        }
        if ($data['min_stock'] < 0) {
            $errors[] = 'Stok minimum tidak boleh negatif.';
        }

        return $errors;
    }
}
