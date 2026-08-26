<?php
require_once ROOT_PATH . '/core/Model.php';

class StockOpnameItem extends Model
{
    protected string $table = 'stock_opname_items';

    /**
     * Item satu opname -- selalu tampilkan histori APA ADANYA (qty_system/qty_actual/
     * difference yang tersimpan, bukan dihitung ulang). JOIN inventory SENGAJA tidak
     * difilter deleted_at supaya barang yang dihapus SETELAH opname ini tetap muncul
     * di histori (lihat prinsip snapshot) -- inventory_deleted_at diikutkan supaya
     * view bisa menandai item tsb "sudah dihapus" tanpa mempengaruhi data historisnya.
     */
    public function itemsByOpname(int $opnameId): array
    {
        $sql = "SELECT soi.*, inv.item_name, inv.unit, inv.deleted_at AS inventory_deleted_at
                FROM stock_opname_items soi
                JOIN inventory inv ON inv.id = soi.inventory_id
                WHERE soi.stock_opname_id = :opname_id
                ORDER BY inv.item_name ASC";
        return $this->db->fetchAll($sql, ['opname_id' => $opnameId]);
    }

    public function deleteByOpname(int $opnameId): int
    {
        return $this->db->query(
            "DELETE FROM stock_opname_items WHERE stock_opname_id = :opname_id",
            ['opname_id' => $opnameId]
        )->rowCount();
    }

    /**
     * Baris per-item untuk Laporan Stok Opname -- SATU-SATUNYA sumber data adalah
     * stock_opname_items (qty_system/qty_actual/difference yang tersimpan saat opname
     * dibuat/diselesaikan). TIDAK PERNAH join/hitung ulang dari qty_available inventory
     * hari ini -- itu justru bug yang membuat laporan opname lama "berubah sendiri"
     * mengikuti stok terkini. JOIN inventory hanya untuk resolusi nama/satuan barang
     * (histori, deleted_at TIDAK difilter -- barang yang sudah dihapus tetap harus
     * tampil di laporan opname yang memang menyertakannya, lihat prinsip snapshot).
     *
     * item_code: inventory TIDAK punya FK ke tabel master `items` (item disimpan
     * sebagai item_name teks bebas, bukan item_id) -- kode diambil best-effort lewat
     * LEFT JOIN nama, dan bernilai NULL/'-' kalau tidak ada padanan aktif di master.
     */
    public function reportRows(array $filters = []): array
    {
        $sql = "SELECT soi.*,
                       inv.item_name, inv.unit, inv.deleted_at AS inventory_deleted_at,
                       so.opname_number, so.opname_date, so.status AS opname_status,
                       so.stock_scope, so.project_id,
                       p.project_name,
                       mi.item_code
                FROM stock_opname_items soi
                JOIN stock_opname so ON so.id = soi.stock_opname_id AND so.deleted_at IS NULL
                JOIN inventory inv ON inv.id = soi.inventory_id
                LEFT JOIN projects p ON p.id = so.project_id
                LEFT JOIN items mi ON mi.item_name = inv.item_name AND mi.deleted_at IS NULL
                WHERE 1=1";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND so.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
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

        $sql .= " ORDER BY so.opname_date DESC, so.id DESC, soi.id ASC";

        return $this->db->fetchAll($sql, $params);
    }
}
