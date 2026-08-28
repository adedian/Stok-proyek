<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/CashCategory.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Master Kategori Kas (Revisi 9) -- Master Data > Kategori Kas.
 * Pola identik ItemCategoryController. Accounting boleh kelola (view/create/
 * edit) tetapi TIDAK hapus (aturan global: Accounting no delete).
 */
class CashCategoryController extends Controller
{
    private CashCategory $categoryModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('cash_category', 'view');

        $this->categoryModel = new CashCategory();
        $this->activityLog   = new ActivityLog();
    }

    public function index()
    {
        $filters = ['keyword' => trim($_GET['keyword'] ?? '')];
        $sort = $_GET['sort'] ?? 'category_name';
        $dir  = $_GET['dir'] ?? 'asc';
        $page = (int) ($_GET['page'] ?? 1);

        $totalRows = $this->categoryModel->countFiltered($filters);
        $pg = paginationInfo($totalRows, $page);
        $categories = $this->categoryModel->listPaginated($filters, $sort, $dir, $pg['perPage'], $pg['offset']);

        $baseQuery = http_build_query(array_filter(array_merge($filters, [
            'module' => 'cash_category', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('cash_category/list', [
            'pageTitle'  => 'Kategori Kas',
            'categories' => $categories,
            'filters'    => $filters,
            'sort'       => $sort,
            'dir'        => $dir,
            'pagination' => $pg,
            'baseQuery'  => $baseQuery,
        ]);
    }

    public function create()
    {
        Middleware::requirePermission('cash_category', 'create');
        $this->view('cash_category/form', [
            'pageTitle' => 'Tambah Kategori Kas',
            'mode'      => 'create',
            'category'  => null,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('cash_category', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash_category', 'create');
        }
        verifyCsrf();

        $name = trim($_POST['category_name'] ?? '');
        $errors = $this->validate($name);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('cash_category', 'create');
        }

        $this->categoryModel->create(['category_name' => $name, 'created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'cash_category', 'create', "Kategori Kas '{$name}' dibuat");
        setFlash('success', 'Kategori Kas berhasil ditambahkan.');
        $this->redirect('cash_category', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('cash_category', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $category = $this->categoryModel->find($id);

        if (!$category) {
            setFlash('error', 'Kategori tidak ditemukan.');
            $this->redirect('cash_category', 'index');
        }

        $this->view('cash_category/form', [
            'pageTitle' => 'Edit Kategori Kas',
            'mode'      => 'edit',
            'category'  => $category,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('cash_category', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash_category', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->categoryModel->find($id);
        if (!$existing) {
            setFlash('error', 'Kategori tidak ditemukan.');
            $this->redirect('cash_category', 'index');
        }

        $name = trim($_POST['category_name'] ?? '');
        $errors = $this->validate($name, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('cash_category', 'edit', ['id' => $id]);
        }

        $this->categoryModel->updateById($id, ['category_name' => $name]);
        $this->activityLog->log(currentUserId(), 'cash_category', 'update', "Kategori Kas '{$name}' diperbarui");
        setFlash('success', 'Kategori Kas berhasil diperbarui.');
        $this->redirect('cash_category', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('cash_category', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('cash_category', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $category = $this->categoryModel->find($id);

        if ($category) {
            $this->categoryModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'cash_category', 'delete', "Kategori Kas '{$category['category_name']}' dihapus");
            setFlash('success', 'Kategori Kas berhasil dihapus.');
        } else {
            setFlash('error', 'Kategori tidak ditemukan.');
        }

        $this->redirect('cash_category', 'index');
    }

    private function validate(string $name, ?int $excludeId = null): array
    {
        $errors = [];
        if ($name === '') {
            $errors[] = 'Nama kategori wajib diisi.';
        } elseif ($this->categoryModel->nameExists($name, $excludeId)) {
            $errors[] = 'Nama kategori sudah ada.';
        }
        return $errors;
    }
}
