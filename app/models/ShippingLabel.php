<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * ShippingLabel (Label Pengiriman) -- alamat "Kepada" + "Pengirim" yang dicetak
 * sebagai dua kotak. Berdiri sendiri: tanpa nomor dokumen & tanpa relasi master.
 */
class ShippingLabel extends Model
{
    protected string $table = 'shipping_labels';
    protected bool $softDelete = true;

    /** Kolom isian per pihak ("to" = Kepada, "from" = Pengirim). Kunci form = {pihak}_{kolom}. */
    public const PARTIES = ['to', 'from'];
    public const FIELDS  = ['name', 'address', 'city', 'postal_code', 'phone'];

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $sql = $countOnly
            ? "SELECT COUNT(*) AS total FROM shipping_labels sl WHERE sl.deleted_at IS NULL"
            : "SELECT sl.* FROM shipping_labels sl WHERE sl.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause(
                $filters['keyword'],
                ['sl.to_name', 'sl.to_address', 'sl.to_city', 'sl.from_name', 'sl.from_address', 'sl.from_city'],
                [],
                'slkw'
            );
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(sl.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(sl.created_at) <= :date_to";
            $params['date_to'] = $filters['date_to'];
        }

        return [$sql, $params];
    }

    public function countFiltered(array $filters): int
    {
        [$sql, $params] = $this->buildListQuery($filters, true);
        $row = $this->db->fetchOne($sql, $params);
        return (int) ($row['total'] ?? 0);
    }

    public function listPaginated(array $filters, int $limit, int $offset): array
    {
        [$sql, $params] = $this->buildListQuery($filters);
        $sql .= " ORDER BY sl.created_at DESC, sl.id DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        return $this->db->fetchAll($sql, $params);
    }

    /** Semua label yang cocok filter (untuk "Cetak Semua"), tanpa paginasi. */
    public function listAll(array $filters): array
    {
        [$sql, $params] = $this->buildListQuery($filters);
        $sql .= " ORDER BY sl.created_at DESC, sl.id DESC";
        return $this->db->fetchAll($sql, $params);
    }
}
