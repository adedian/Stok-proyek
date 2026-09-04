<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

class StockOpname extends Model
{
    protected string $table = 'stock_opname';
    protected bool $softDelete = true;

    public array $statusLabels = [
        'draft'     => 'Draft',
        'completed' => 'Selesai',
    ];

    public array $statusBadgeClass = [
        'draft'     => 'secondary',
        'completed' => 'success',
    ];

    /**
     * @deprecated 2026-09-12 -- "Kategori Stok" sekarang pakai 3 Jenis Stok master
     * (stockTypeLabels()). Dipertahankan hanya untuk data lama yang masih membaca
     * stock_scope; jangan dipakai untuk fitur baru.
     */
    public array $scopeLabels = [
        'proyek' => 'Stok Proyek',
        'kantor' => 'Stok Kantor',
    ];

    /** Jenis Stok (stock_type) -- SUMBER: stockTypeLabels() helper global. */
    public array $stockTypeLabels;

    public function __construct()
    {
        parent::__construct();
        $this->stockTypeLabels = stockTypeLabels();
    }

    /**
     * "001/SO.HME/X/2026" -- lihat catatan lengkap di SalesInvoice::generateInvoiceNumber(),
     * pola & jaminan yang sama (atomic, reset per tahun).
     */
    public function generateOpnameNumber(?string $opnameDate = null): string
    {
        return (new DocumentNumber())->next('stock_opname', 'prefix_opn', $opnameDate);
    }

    /** Preview nomor untuk FORM tambah (tidak menaikkan counter) -- lihat DocumentNumber::preview(). */
    public function previewOpnameNumber(?string $opnameDate = null): string
    {
        return (new DocumentNumber())->preview('stock_opname', 'prefix_opn', $opnameDate);
    }

    public function listWithRelations(array $filters = []): array
    {
        // LEFT JOIN (bukan INNER JOIN) karena stock_scope='kantor' boleh punya
        // project_id NULL -- opname untuk bucket Kantor tidak selalu terikat project.
        $sql = "SELECT so.*, p.project_name
                FROM stock_opname so
                LEFT JOIN projects p ON p.id = so.project_id
                WHERE so.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND so.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['stock_scope'])) {
            $sql .= " AND so.stock_scope = :stock_scope";
            $params['stock_scope'] = $filters['stock_scope'];
        }
        if (!empty($filters['stock_type'])) {
            $sql .= " AND so.stock_type = :stock_type";
            $params['stock_type'] = $filters['stock_type'];
        }
        if (!empty($filters['status'])) {
            $sql .= " AND so.status = :status";
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND so.opname_date >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND so.opname_date <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= " ORDER BY so.created_at DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        $sql = "SELECT so.*, p.project_name
                FROM stock_opname so
                LEFT JOIN projects p ON p.id = so.project_id
                WHERE so.id = :id AND so.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }
}
