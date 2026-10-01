<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/RequestBudget.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';

/**
 * Laporan Request Budget (menu Laporan) -- HANYA Accounting & Super Admin.
 *
 * Sengaja modul izin TERPISAH ('request_budget_report'), bukan bagian akses Request
 * Budget biasa: Accounting boleh melihat laporan + detail lifecycle tetapi TIDAK punya
 * hak apa pun untuk mengubah workflow (tidak ada tombol/endpoint aksi di controller ini).
 * Laporan lintas project, hanya request yang sudah disubmit (bukan Draft).
 *
 * Cetak/Export mengikuti template Excel dari Accounting -- belum diimplementasikan karena
 * file template belum diterima (lihat catatan di views/request_budget_report/list.php).
 */
class RequestBudgetReportController extends Controller
{
    private RequestBudget $rbModel;

    public function __construct()
    {
        Middleware::requirePermission('request_budget_report', 'view');
        $this->rbModel = new RequestBudget();
    }

    private function filters(): array
    {
        return [
            'keyword'          => trim($_GET['keyword'] ?? ''),
            'date_from'        => trim($_GET['date_from'] ?? ''),
            'date_to'          => trim($_GET['date_to'] ?? ''),
            'project_id'       => (int) ($_GET['project_id'] ?? 0),
            'status'           => trim($_GET['status'] ?? ''),
            'approval'         => trim($_GET['approval'] ?? ''),
            'purchase_user_id' => (int) ($_GET['purchase_user_id'] ?? 0),
            'forward_to'       => trim($_GET['forward_to'] ?? ''),
            'vendor'           => trim($_GET['vendor'] ?? ''),
            'po_number'        => trim($_GET['po_number'] ?? ''),
            'invoice_number'   => trim($_GET['invoice_number'] ?? ''),
        ];
    }

    public function index()
    {
        $filters = $this->filters();
        $total = $this->rbModel->reportCount($filters);
        $pg = paginationInfo($total, (int) ($_GET['page'] ?? 1), 20);

        $this->view('request_budget_report/list', [
            'pageTitle'  => 'Laporan Request Budget',
            'rows'       => $this->rbModel->reportRows($filters, $pg['perPage'], $pg['offset']),
            'filters'    => $filters,
            'pagination' => $pg,
            'baseQuery'  => http_build_query(array_filter(['module' => 'request_budget_report'] + $filters)),
            'grandTotal' => $this->rbModel->reportTotal($filters),
            'projects'   => (new Project())->activeList(),
            'statuses'   => array_diff_key(RequestBudget::STATUS_LABELS, [RequestBudget::DRAFT => 1]),
            'purchaseUsers' => $this->rbModel->purchaseUserOptions(),
        ]);
    }

    /** Detail lifecycle lengkap (read-only) -- tidak ada aksi workflow di halaman ini. */
    public function detail()
    {
        $id = (int) ($_GET['id'] ?? 0);
        $rb = $id > 0 ? $this->rbModel->findWithRelations($id) : null;
        if (!$rb || $rb['status'] === RequestBudget::DRAFT) {
            setFlash('error', 'Request Budget tidak ditemukan.');
            $this->redirect('request_budget_report', 'index');
        }
        (new ActivityLog())->log(currentUserId(), 'request_budget_report', 'view', "Laporan Request Budget: membuka detail {$rb['request_number']} (#{$id})");

        $this->view('request_budget_report/detail', [
            'pageTitle'   => 'Detail Laporan Request Budget',
            'rb'          => $rb,
            'items'       => $this->rbModel->items($id),
            'approvals'   => $this->rbModel->approvals($id),
            'pos'         => $this->rbModel->pos($id),
            'invoices'    => $this->rbModel->invoices($id),
            'attachments' => $this->rbModel->attachments($id),
            'history'     => $this->rbModel->history($id),
        ]);
    }
}
