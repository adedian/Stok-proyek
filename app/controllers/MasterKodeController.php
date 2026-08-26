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
        Middleware::requirePermission('master_kode', 'view');
        $this->codeConfig  = new CodeConfig();
        $this->activityLog = new ActivityLog();
    }

    public function index()
    {
        $groups = [];
        foreach ($this->codeConfig->entityOptions() as $type => $label) {
            $groups[] = [
                'type'   => $type,
                'label'  => $label,
                'config' => $this->codeConfig->getConfig($type),
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
            'config'     => $this->codeConfig->getConfig($type),
            'rows'       => $rows,
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    public function saveConfig()
    {
        Middleware::requirePermission('master_kode', 'edit');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('master_kode', 'index');
        }
        verifyCsrf();

        $type = trim($_POST['entity_type'] ?? '');
        $meta = $this->codeConfig->entityMeta($type);

        if (!$meta) {
            setFlash('error', 'Kelompok Master Kode tidak dikenal.');
            $this->redirect('master_kode', 'index');
        }

        $prefix = trim($_POST['prefix'] ?? '');
        $digitLength = (int) ($_POST['digit_length'] ?? 4);

        if ($prefix === '' || !preg_match('/^[A-Za-z0-9]+$/', $prefix)) {
            setFlash('error', 'Prefix wajib diisi dan hanya boleh huruf/angka (tanpa spasi atau simbol).');
            $this->redirect('master_kode', 'group', ['type' => $type]);
        }
        if ($digitLength < 1 || $digitLength > 10) {
            setFlash('error', 'Jumlah digit nomor harus antara 1-10.');
            $this->redirect('master_kode', 'group', ['type' => $type]);
        }

        $this->codeConfig->saveConfig($type, $prefix, $digitLength, currentUserId());
        $this->activityLog->log(
            currentUserId(),
            'master_kode',
            'update',
            "Konfigurasi kode {$meta['label']} diubah: prefix={$prefix}, digit={$digitLength}"
        );
        setFlash('success', 'Konfigurasi kode berhasil disimpan.');
        $this->redirect('master_kode', 'group', ['type' => $type]);
    }

    // ================= Helper privat =================

    private function resolveModel(string $type)
    {
        switch ($type) {
            case 'item': return new Item();
            case 'supplier': return new Supplier();
            case 'client': return new Client();
            case 'warehouse': return new Warehouse();
            case 'project': return new Project();
            default: return null;
        }
    }
}
