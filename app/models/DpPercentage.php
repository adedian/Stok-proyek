<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * DpPercentage
 * Master pilihan persentase Tagihan DP untuk Invoice Keluar -- pola sama
 * dengan Signature (name + status aktif/nonaktif, soft delete). Invoice
 * menyimpan SNAPSHOT persentase (sales_invoices.dp_percentage) terpisah dari
 * FK ini, jadi menghapus/menonaktifkan baris di sini TIDAK PERNAH mengubah
 * invoice yang sudah terbit -- aman dihapus kapan saja.
 */
class DpPercentage extends Model
{
    protected string $table = 'dp_percentages';
    protected bool $softDelete = true;

    public array $statusLabels = [
        'active'   => 'Aktif',
        'inactive' => 'Nonaktif',
    ];

    /**
     * Pilihan aktif untuk dropdown "Tagihan DP" di form Invoice Keluar --
     * urut dari persentase terkecil supaya enak dibaca di dropdown.
     */
    public function activeList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM dp_percentages WHERE status = 'active' AND deleted_at IS NULL ORDER BY percentage ASC"
        );
    }

    public function listAll(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM dp_percentages WHERE deleted_at IS NULL ORDER BY percentage ASC"
        );
    }

    /**
     * Ambil 1 baris TANPA filter deleted_at -- dipakai SalesInvoiceController
     * untuk resolve nilai % yang dipilih user secara aman (id harus baris nyata
     * yang pernah ada di tabel ini, tidak bisa dipalsukan ke angka sembarang),
     * termasuk kalau baris masternya sudah dihapus/dinonaktifkan setelah invoice
     * lama dibuat -- lihat SalesInvoiceController::dpPercentagesForEdit().
     */
    public function findAny(int $id)
    {
        return $this->db->fetchOne("SELECT * FROM dp_percentages WHERE id = :id", ['id' => $id]);
    }

    public function nameExists(string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM dp_percentages WHERE name = :name AND deleted_at IS NULL";
        $params = ['name' => $name];
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }
}
