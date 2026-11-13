<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * FormDocument -- menu "Form": satu tabel `forms` untuk beberapa jenis form
 * (Pengiriman Dokumen, Kwitansi, Order Form, Pengajuan Bon Sementara).
 * Isian spesifik jenis disimpan sebagai JSON di kolom `data`; validasi per jenis
 * ada di FormController. Berdiri sendiri: tanpa efek ke stok/kas/PO/invoice.
 */
class FormDocument extends Model
{
    protected string $table = 'forms';
    protected bool $softDelete = true;

    /** slug jenis => label (urutan = urutan dropdown). */
    public static function types(): array
    {
        return [
            'pengiriman'    => 'Form Pengiriman Dokumen',
            'kwitansi'      => 'Kwitansi',
            'order_form'    => 'Order Form',
            'bon_sementara' => 'Pengajuan Bon Sementara',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        return self::types()[$type] ?? ($type ?? '-');
    }

    public static function isValidType($type): bool
    {
        return is_string($type) && array_key_exists($type, self::types());
    }

    /** Tambah kunci 'd' (isian ter-decode) ke baris hasil query. */
    public static function hydrate(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }
        $decoded = json_decode((string) ($row['data'] ?? ''), true);
        $row['d'] = is_array($decoded) ? $decoded : [];
        return $row;
    }

    private function buildListQuery(array $filters, bool $countOnly = false): array
    {
        $sql = ($countOnly ? "SELECT COUNT(*) AS total" : "SELECT f.*") . " FROM forms f WHERE f.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['type']) && self::isValidType($filters['type'])) {
            $sql .= " AND f.form_type = :ftype";
            $params['ftype'] = $filters['type'];
        }
        if (!empty($filters['keyword'])) {
            [$ssSql, $ssParams] = SmartSearch::clause($filters['keyword'], ['f.summary', 'f.data'], [], 'fkw');
            if ($ssSql !== '') {
                $sql .= " AND {$ssSql}";
                $params += $ssParams;
            }
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(f.created_at) >= :date_from";
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(f.created_at) <= :date_to";
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
        $sql .= " ORDER BY f.created_at DESC, f.id DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        return $this->db->fetchAll($sql, $params);
    }

    /** Semua form yang cocok filter (untuk "Cetak Semua"), tanpa paginasi. */
    public function listAll(array $filters): array
    {
        [$sql, $params] = $this->buildListQuery($filters);
        return $this->db->fetchAll($sql . " ORDER BY f.created_at DESC, f.id DESC", $params);
    }
}
