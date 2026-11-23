<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * FormDocument -- menu "Form": satu tabel `forms` untuk beberapa jenis form
 * (Pengiriman Dokumen, Kwitansi, Form Order Barang, Pengajuan Bon Sementara, Tanda Terima Dokumen).
 * Semua jenis form bernomor otomatis (forms.form_number, mis. 001/KWT.HME/XI/2026);
 * kode per jenis ada di DocumentNumber::DOC_TYPE_CODE.
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
            'order_form'    => 'Form Order Barang',
            'bon_sementara' => 'Pengajuan Bon Sementara',
            'tanda_terima'  => 'Tanda Terima Dokumen',
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

    /** slug jenis form => doc_type counter di DocumentNumber (jenis yang tidak ada di sini tidak bernomor). */
    private const NUMBER_DOC_TYPE = [
        'pengiriman'    => 'form_pengiriman',
        'kwitansi'     => 'form_kwitansi',
        'bon_sementara' => 'form_bon_sementara',
        'tanda_terima'  => 'form_tanda_terima',
        'order_form'    => 'form_order_barang',
    ];

    public static function isNumbered(?string $type): bool
    {
        return isset(self::NUMBER_DOC_TYPE[$type ?? '']);
    }

    /** Nomor berikutnya (hanya tampilan di form, counter TIDAK naik); null untuk jenis tak bernomor. */
    public function previewNumber(string $type, ?string $date = null): ?string
    {
        if (!self::isNumbered($type)) {
            return null;
        }
        $docType = self::NUMBER_DOC_TYPE[$type];
        [$settingKey, $defaultCode] = DocumentNumber::DOC_TYPE_CODE[$docType];
        return (new DocumentNumber())->preview($docType, $settingKey, $date, $defaultCode);
    }

    /**
     * Simpan form baru; untuk jenis bernomor nomor dibuat di transaksi yang SAMA dengan INSERT-nya,
     * jadi kalau simpan gagal nomornya tidak ikut terbakar. Tanggal nomor = tanggal dokumen.
     */
    public function createNumbered(array $row, ?string $docDate = null): int
    {
        $type = (string) ($row['form_type'] ?? '');
        if (!self::isNumbered($type)) {
            return $this->create($row);
        }

        $docType = self::NUMBER_DOC_TYPE[$type];
        [$settingKey, $defaultCode] = DocumentNumber::DOC_TYPE_CODE[$docType];

        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $row['form_number'] = (new DocumentNumber())->next($docType, $settingKey, $docDate, $defaultCode);
            $id = $this->create($row);
            if ($manageTx) {
                $this->db->commit();
            }
            return $id;
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
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
            [$ssSql, $ssParams] = SmartSearch::clause($filters['keyword'], ['f.form_number', 'f.summary', 'f.data'], [], 'fkw');
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
