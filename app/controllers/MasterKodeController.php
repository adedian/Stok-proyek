<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/CodeConfig.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/Item.php';
require_once ROOT_PATH . '/app/models/Supplier.php';
require_once ROOT_PATH . '/app/models/Client.php';
require_once ROOT_PATH . '/app/models/Warehouse.php';
require_once ROOT_PATH . '/app/models/Project.php';

/**
 * MasterKodeController
 * Master Kode v2: PUSAT PENGATURAN KELOMPOK KODE -- bukan daftar gabungan semua
 * entity. index() menampilkan 5 kelompok (Barang/Supplier/Client/Gudang/Project);
 * group() menampilkan konfigurasi prefix SATU kelompok + daftar kode kelompok itu
 * SAJA, datanya dibaca langsung dari model entity aslinya (Item/Supplier/dst),
 * bukan dari tabel/list gabungan. Lihat app/models/CodeConfig.php untuk arsitektur
 * lengkapnya (konfigurasi vs kode aktual).
 */
class MasterKodeController extends Controller
{
    private CodeConfig $codeConfig;
    private ActivityLog $activityLog;

    public function __construct()
    {
        // quickAddPrefix() dicek izinnya sendiri (lebih longgar: pengguna yang
        // boleh 'item'.'quick_add' juga boleh menambah prefix Barang dari modal
        // quick-add) -- jangan diblokir dulu oleh gate 'view' yang Super Admin-only.
        if (($_GET['action'] ?? 'index') !== 'quickAddPrefix') {
            Middleware::requirePermission('master_kode', 'view');
        }
        $this->codeConfig  = new CodeConfig();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $groups = [];
        foreach ($this->codeConfig->entityOptions() as $type => $label) {
            $configs = $this->codeConfig->configsForEntity($type);
            $groups[] = [
                'type'        => $type,
                'label'       => $label,
                'prefixCount' => count($configs),
                'prefixes'    => array_column($configs, 'prefix'),
                'masterCode'  => $this->codeConfig->masterCodeForEntity($type),
            ];
        }

        $this->view('master_kode/index', [
            'pageTitle' => 'Master Kode',
            'groups'    => $groups,
        ]);
    }

    public function group()
    {
        $type = trim($_GET['type'] ?? '');
        $meta = $this->codeConfig->entityMeta($type);

        if (!$meta) {
            setFlash('error', 'Kelompok Master Kode tidak dikenal.');
            $this->redirect('master_kode', 'index');
        }

        $model = $this->resolveModel($type);
        $filters = ['keyword' => trim($_GET['keyword'] ?? '')];
        // Kelompok Barang (item_stok_proyek/item_stok_lampu/item_inventory_kantor)
        // semuanya baca dari tabel `items` yang SAMA -- filter stock_type supaya
        // daftar kode di sini cuma menampilkan barang KELOMPOK INI saja, bukan
        // gabungan ketiganya (lihat catatan di CodeConfig::$entities).
        if (!empty($meta['stock_type'])) {
            $filters['stock_type'] = $meta['stock_type'];
        }
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $model->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        // Urut berdasarkan kolom kode kelompok ini sendiri (selalu ada di sortWhitelist
        // masing-masing model -- item_code/supplier_code/dst).
        $rows = $model->listPaginated($filters, $meta['code_col'], 'asc', $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter([
            'module'  => 'master_kode',
            'action'  => 'group',
            'type'    => $type,
            'keyword' => $filters['keyword'],
        ]));

        $this->view('master_kode/group', [
            'pageTitle'  => 'Master Kode - ' . $meta['label'],
            'entityType' => $type,
            'entityMeta' => $meta,
            'configs'    => $this->codeConfig->configsForEntity($type),
            'masterCode' => $this->codeConfig->masterCodeForEntity($type),
            'rows'       => $rows,
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    /** Validasi kelompok dari POST; redirect kalau tidak dikenal. */
    private function requireEntity(): array
    {
        $type = trim($_POST['entity_type'] ?? '');
        $meta = $this->codeConfig->entityMeta($type);
        if (!$meta) {
            setFlash('error', 'Kelompok Master Kode tidak dikenal.');
            $this->redirect('master_kode', 'index');
        }
        return [$type, $meta];
    }

    private function guardPost(): void
    {
        Middleware::requirePermission('master_kode', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_kode', 'index');
        }
        verifyCsrf();
    }

    /** Set Master Code (kode akhir) untuk seluruh prefix kelompok ini. */
    public function saveMasterCode()
    {
        $this->guardPost();
        [$type, $meta] = $this->requireEntity();

        $res = $this->codeConfig->setMasterCode($type, $_POST['master_code'] ?? '');
        if (!$res['ok']) {
            setFlash('error', $res['error']);
        } else {
            $this->activityLog->log(currentUserId(), 'master_kode', 'update',
                "Master Code {$meta['label']} diset: " . strtoupper(trim($_POST['master_code'] ?? '')));
            setFlash('success', 'Master Code berhasil disimpan.');
        }
        $this->redirect('master_kode', 'group', ['type' => $type]);
    }

    /** Tambah prefix baru. */
    public function addPrefix()
    {
        $this->guardPost();
        [$type, $meta] = $this->requireEntity();

        $res = $this->codeConfig->addPrefix(
            $type,
            $_POST['prefix'] ?? '',
            (int) ($_POST['digit_length'] ?? 4),
            currentUserId()
        );
        if (!$res['ok']) {
            setFlash('error', $res['error']);
            if (stripos($res['error'], 'sudah digunakan') !== false) {
                $this->activityLog->log(currentUserId(), 'master_kode', 'prefix_duplicate_rejected',
                    "Prefix duplikat ditolak ({$meta['label']}): " . strtoupper(trim($_POST['prefix'] ?? '')));
            }
        } else {
            $this->activityLog->log(currentUserId(), 'master_kode', 'create',
                "Prefix baru {$meta['label']}: " . strtoupper(trim($_POST['prefix'] ?? '')));
            setFlash('success', 'Prefix berhasil ditambahkan.');
        }
        $this->redirect('master_kode', 'group', ['type' => $type]);
    }

    /**
     * AJAX quick-add prefix -- dipanggil dari tombol "+" di samping dropdown
     * "Prefix Kode" pada form Tambah Barang, supaya tidak perlu pindah ke
     * halaman Master Kode. Balikannya dipakai JS untuk menyuntik <option> baru
     * (butuh prefix + digit_length + next_number + master_code).
     */
    public function quickAddPrefix()
    {
        // Super Admin (master_kode.edit) boleh untuk semua kelompok. Pengguna
        // 'item'.'quick_add' (Purchase/Accounting/PIC/Admin Project) boleh juga,
        // TAPI hanya untuk kelompok Barang (item_*) -- dari modal quick-add Barang.
        $canMasterKode = can('master_kode', 'edit');
        $canItemQuickAdd = can('item', 'quick_add');
        if (!$canMasterKode && !$canItemQuickAdd) {
            $this->json(['errors' => ['Anda tidak berhak menambah prefix kode.']], 403);
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['errors' => ['Metode tidak diizinkan.']], 405);
        }
        verifyCsrf();

        $type = trim($_POST['entity_type'] ?? '');
        if (!$canMasterKode && strpos($type, 'item_') !== 0) {
            $this->json(['errors' => ['Anda hanya boleh menambah prefix kelompok Barang.']], 403);
        }
        $meta = $this->codeConfig->entityMeta($type);
        if (!$meta) {
            $this->json(['errors' => ['Kelompok Master Kode tidak dikenal.']], 422);
        }

        $prefix = strtoupper(trim($_POST['prefix'] ?? ''));
        $res = $this->codeConfig->addPrefix($type, $prefix, (int) ($_POST['digit_length'] ?? 4), currentUserId());
        if (!$res['ok']) {
            $this->json(['errors' => [$res['error']]], 422);
        }

        $this->activityLog->log(currentUserId(), 'master_kode', 'create',
            "Prefix baru {$meta['label']} (quick-add dari form Barang): {$prefix}");

        $cfg = $this->codeConfig->getConfigByPrefix($type, $prefix);
        $this->json([
            'id'           => $cfg['prefix'],
            'label'        => $cfg['prefix'],
            'digit_length' => (int) $cfg['digit_length'],
            'next_number'  => (int) $cfg['next_number'],
            'master_code'  => $cfg['master_code'] ?? '',
        ]);
    }

    /** Ubah prefix / digit satu baris. */
    public function updatePrefix()
    {
        $this->guardPost();
        [$type, $meta] = $this->requireEntity();

        $res = $this->codeConfig->updatePrefixConfig(
            (int) ($_POST['id'] ?? 0),
            $_POST['prefix'] ?? '',
            (int) ($_POST['digit_length'] ?? 4)
        );
        if (!$res['ok']) {
            setFlash('error', $res['error']);
        } else {
            $this->activityLog->log(currentUserId(), 'master_kode', 'update',
                "Prefix {$meta['label']} diubah: " . strtoupper(trim($_POST['prefix'] ?? '')));
            setFlash('success', 'Prefix berhasil diperbarui.');
        }
        $this->redirect('master_kode', 'group', ['type' => $type]);
    }

    /** Hapus prefix (hanya kalau belum pernah dipakai). */
    public function deletePrefix()
    {
        $this->guardPost();
        [$type, $meta] = $this->requireEntity();

        $res = $this->codeConfig->deletePrefixConfig((int) ($_POST['id'] ?? 0));
        if (!$res['ok']) {
            setFlash('error', $res['error']);
        } else {
            $this->activityLog->log(currentUserId(), 'master_kode', 'delete',
                "Prefix {$meta['label']} dihapus (id " . (int) ($_POST['id'] ?? 0) . ")");
            setFlash('success', 'Prefix dihapus.');
        }
        $this->redirect('master_kode', 'group', ['type' => $type]);
    }

    // ================= Helper privat =================

    private function resolveModel(string $type)
    {
        switch ($type) {
            case 'item_stok_proyek':
            case 'item_stok_lampu':
            case 'item_inventory_kantor':
                return new Item();
            case 'supplier': return new Supplier();
            case 'client': return new Client();
            case 'warehouse': return new Warehouse();
            case 'project': return new Project();
            default: return null;
        }
    }
}
