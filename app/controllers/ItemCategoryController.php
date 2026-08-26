<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/ItemCategory.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

class ItemCategoryController extends Controller
{
    private ItemCategory $categoryModel;
    private ActivityLog $activityLog;

    public function __construct()
    {
        Middleware::requirePermission('item_category', 'view');

        $this->categoryModel = new ItemCategory();
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
            'module' => 'item_category', 'sort' => $sort, 'dir' => $dir,
        ])));

        $this->view('item_category/list', [
            'pageTitle'  => 'Kategori Barang',
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
        Middleware::requirePermission('item_category', 'create');
        $this->view('item_category/form', [
            'pageTitle' => 'Tambah Kategori Barang',
            'mode'      => 'create',
            'category'  => null,
        ]);
    }

    public function store()
    {
        Middleware::requirePermission('item_category', 'create');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('item_category', 'create');
        }
        verifyCsrf();

        $name = trim($_POST['category_name'] ?? '');
        $errors = $this->validate($name);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('item_category', 'create');
        }

        $this->categoryModel->create(['category_name' => $name, 'created_by' => currentUserId()]);
        $this->activityLog->log(currentUserId(), 'item_category', 'create', "Kategori '{$name}' dibuat");
        setFlash('success', 'Kategori barang berhasil ditambahkan.');
        $this->redirect('item_category', 'index');
    }

    public function edit()
    {
        Middleware::requirePermission('item_category', 'edit');
        $id = (int) ($_GET['id'] ?? 0);
        $category = $this->categoryModel->find($id);

        if (!$category) {
            setFlash('error', 'Kategori tidak ditemukan.');
            $this->redirect('item_category', 'index');
        }

        $this->view('item_category/form', [
            'pageTitle' => 'Edit Kategori Barang',
            'mode'      => 'edit',
            'category'  => $category,
        ]);
    }

    public function update()
    {
        Middleware::requirePermission('item_category', 'edit');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('item_category', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $existing = $this->categoryModel->find($id);
        if (!$existing) {
            setFlash('error', 'Kategori tidak ditemukan.');
            $this->redirect('item_category', 'index');
        }

        $name = trim($_POST['category_name'] ?? '');
        $errors = $this->validate($name, $id);

        if (!empty($errors)) {
            setFlash('error', implode(' ', $errors));
            $this->redirect('item_category', 'edit', ['id' => $id]);
        }

        $this->categoryModel->updateById($id, ['category_name' => $name]);
        $this->activityLog->log(currentUserId(), 'item_category', 'update', "Kategori '{$name}' diperbarui");
        setFlash('success', 'Kategori barang berhasil diperbarui.');
        $this->redirect('item_category', 'index');
    }

    public function delete()
    {
        Middleware::requirePermission('item_category', 'delete');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('item_category', 'index');
        }
        verifyCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $category = $this->categoryModel->find($id);

        if ($category) {
            $this->categoryModel->deleteById($id);
            $this->activityLog->log(currentUserId(), 'item_category', 'delete', "Kategori '{$category['category_name']}' dihapus");
            setFlash('success', 'Kategori barang berhasil dihapus.');
        } else {
            setFlash('error', 'Kategori tidak ditemukan.');
        }

        $this->redirect('item_category', 'index');
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
