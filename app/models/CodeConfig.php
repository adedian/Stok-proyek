<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * CodeConfig -- Master Kode v3 (multi-prefix).
 *
 * Menyimpan POLA kode per (entity_type, prefix). SATU entity bisa punya BANYAK
 * prefix, masing-masing counter sendiri. `master_code` = kode akhir per entity
 * (ITM/SUP/CLI/WH/PRJ), diatur admin di Master Kode.
 *
 * Format kode: PREFIX.NOMOR.MASTERCODE  (mis. ME.0001.ITM)
 *
 * Kode aktual tetap di items.item_code / suppliers.supplier_code / dst
 * (single source of truth). Kode LAMA format PREFIX-NNNNN tetap valid --
 * nextCode() cek collision langsung ke tabel entity apa pun formatnya.
 */
class CodeConfig extends Model
{
    protected string $table = 'code_configs';

    /** master_code default per entity (dipakai saat menambah prefix pertama). */
    private array $entities = [
        'item_stok_proyek' => [
            'table' => 'items', 'code_col' => 'item_code', 'name_col' => 'item_name',
            'label' => 'Barang - Stok Proyek', 'module' => 'item', 'stock_type' => 'stok_proyek',
            'master_code' => 'ITM',
        ],
        'item_stok_lampu' => [
            'table' => 'items', 'code_col' => 'item_code', 'name_col' => 'item_name',
            'label' => 'Barang - Stok Lampu', 'module' => 'item', 'stock_type' => 'stok_lampu',
            'master_code' => 'ITM',
        ],
        'item_inventory_kantor' => [
            'table' => 'items', 'code_col' => 'item_code', 'name_col' => 'item_name',
            'label' => 'Barang - Inventory Kantor', 'module' => 'item', 'stock_type' => 'inventory_kantor',
            'master_code' => 'ITM',
        ],
        'supplier' => [
            'table' => 'suppliers', 'code_col' => 'supplier_code', 'name_col' => 'supplier_name',
            'label' => 'Supplier', 'module' => 'supplier', 'master_code' => 'SUP',
        ],
        'client' => [
            'table' => 'clients', 'code_col' => 'client_code', 'name_col' => 'client_name',
            'label' => 'Client', 'module' => 'client', 'master_code' => 'CLI',
        ],
        'warehouse' => [
            'table' => 'warehouses', 'code_col' => 'warehouse_code', 'name_col' => 'warehouse_name',
            'label' => 'Gudang', 'module' => 'warehouse', 'master_code' => 'WH',
        ],
        'project' => [
            'table' => 'projects', 'code_col' => 'project_code', 'name_col' => 'project_name',
            'label' => 'Project', 'module' => 'project', 'master_code' => 'PRJ',
        ],
    ];

    public function entityMeta(string $entityType): ?array
    {
        return $this->entities[$entityType] ?? null;
    }

    public function entityOptions(): array
    {
        $out = [];
        foreach ($this->entities as $key => $meta) {
            $out[$key] = $meta['label'];
        }
        return $out;
    }

    /** Semua baris prefix untuk satu entity (urut prefix). */
    public function configsForEntity(string $entityType): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM code_configs WHERE entity_type = :t ORDER BY prefix ASC",
            ['t' => $entityType]
        );
    }

    /** Baris prefix pertama (backward-compat "sudah dikonfigurasi?"). NULL kalau belum ada. */
    public function getConfig(string $entityType): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM code_configs WHERE entity_type = :t ORDER BY prefix ASC LIMIT 1",
            ['t' => $entityType]
        ) ?: null;
    }

    public function getConfigByPrefix(string $entityType, string $prefix): ?array
    {
        return $this->db->fetchOne(
            "SELECT * FROM code_configs WHERE entity_type = :t AND prefix = :p",
            ['t' => $entityType, 'p' => strtoupper(trim($prefix))]
        ) ?: null;
    }

    /** master_code aktif untuk entity (dari baris config; fallback ke default). */
    public function masterCodeForEntity(string $entityType): string
    {
        $row = $this->db->fetchOne(
            "SELECT master_code FROM code_configs WHERE entity_type = :t AND master_code <> '' ORDER BY prefix ASC LIMIT 1",
            ['t' => $entityType]
        );
        if ($row && $row['master_code'] !== '') {
            return $row['master_code'];
        }
        return $this->entities[$entityType]['master_code'] ?? '';
    }

    public function prefixExists(string $entityType, string $prefix, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM code_configs WHERE entity_type = :t AND prefix = :p";
        $params = ['t' => $entityType, 'p' => strtoupper(trim($prefix))];
        if ($excludeId) {
            $sql .= " AND id <> :ex";
            $params['ex'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    /** Tambah prefix baru untuk entity. Return ['ok'=>bool,'error'=>string]. */
    public function addPrefix(string $entityType, string $prefix, int $digitLength, ?int $userId): array
    {
        if (!$this->entityMeta($entityType)) {
            return ['ok' => false, 'error' => 'Kelompok tidak dikenal.'];
        }
        $prefix = strtoupper(trim($prefix));
        if ($prefix === '' || !preg_match('/^[A-Z0-9]+$/', $prefix)) {
            return ['ok' => false, 'error' => 'Prefix hanya boleh huruf/angka (tanpa spasi/simbol).'];
        }
        if ($this->prefixExists($entityType, $prefix)) {
            return ['ok' => false, 'error' => "Prefix {$prefix} sudah digunakan pada kategori ini."];
        }
        $digitLength = max(1, min(10, $digitLength));
        $this->db->insert('code_configs', [
            'entity_type'  => $entityType,
            'prefix'       => $prefix,
            'master_code'  => $this->masterCodeForEntity($entityType),
            'digit_length' => $digitLength,
            'next_number'  => 1,
            'status'       => 'active',
            'created_by'   => $userId,
        ]);
        return ['ok' => true, 'error' => ''];
    }

    /** Ubah prefix / digit satu baris. Return ['ok'=>bool,'error'=>string]. */
    public function updatePrefixConfig(int $id, string $prefix, int $digitLength): array
    {
        $row = $this->find($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'Baris konfigurasi tidak ditemukan.'];
        }
        $prefix = strtoupper(trim($prefix));
        if ($prefix === '' || !preg_match('/^[A-Z0-9]+$/', $prefix)) {
            return ['ok' => false, 'error' => 'Prefix hanya boleh huruf/angka.'];
        }
        if ($this->prefixExists($row['entity_type'], $prefix, $id)) {
            return ['ok' => false, 'error' => "Prefix {$prefix} sudah digunakan pada kategori ini."];
        }
        $this->db->update(
            'code_configs',
            ['prefix' => $prefix, 'digit_length' => max(1, min(10, $digitLength)), 'updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );
        return ['ok' => true, 'error' => ''];
    }

    /** Hapus baris prefix -- hanya kalau belum pernah dipakai (next_number = 1). */
    public function deletePrefixConfig(int $id): array
    {
        $row = $this->find($id);
        if (!$row) {
            return ['ok' => false, 'error' => 'Baris tidak ditemukan.'];
        }
        if ((int) $row['next_number'] > 1) {
            return ['ok' => false, 'error' => 'Prefix ini sudah pernah menghasilkan kode, tidak bisa dihapus.'];
        }
        $this->db->query("DELETE FROM code_configs WHERE id = :id", ['id' => $id]);
        return ['ok' => true, 'error' => ''];
    }

    /** Set master_code untuk SEMUA prefix milik satu entity. */
    public function setMasterCode(string $entityType, string $masterCode): array
    {
        if (!$this->entityMeta($entityType)) {
            return ['ok' => false, 'error' => 'Kelompok tidak dikenal.'];
        }
        $masterCode = strtoupper(trim($masterCode));
        if ($masterCode === '' || !preg_match('/^[A-Z0-9]{1,10}$/', $masterCode)) {
            return ['ok' => false, 'error' => 'Master Code hanya boleh 1-10 huruf/angka.'];
        }
        $this->db->query(
            "UPDATE code_configs SET master_code = :mc, updated_at = NOW() WHERE entity_type = :t",
            ['mc' => $masterCode, 't' => $entityType]
        );
        return ['ok' => true, 'error' => ''];
    }

    /** Preview format kode dari sebuah baris config (bukan kode final). */
    public function previewFormat(array $config): string
    {
        $num = str_pad((string) $config['next_number'], (int) $config['digit_length'], '0', STR_PAD_LEFT);
        $mc  = trim((string) ($config['master_code'] ?? ''));
        return $config['prefix'] . '.' . $num . ($mc !== '' ? '.' . $mc : '');
    }

    /**
     * Generate kode berikutnya untuk (entity_type, prefix) secara ATOMIC.
     * $prefix null -> pakai prefix pertama entity (dipakai quick-add yang
     * tidak punya pilihan prefix). Return null kalau kelompok/prefix belum
     * dikonfigurasi -- caller WAJIB menolak penyimpanan.
     *
     * Format: PREFIX.NOMOR.MASTERCODE. Ada collision check ke tabel entity
     * (menangkap kode lama format apa pun yang kebetulan bentrok).
     */
    public function nextCode(string $entityType, ?string $prefix = null): ?string
    {
        $meta = $this->entityMeta($entityType);
        if (!$meta) {
            return null;
        }

        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $sql = "SELECT * FROM code_configs WHERE entity_type = :t AND status = 'active'";
            $params = ['t' => $entityType];
            if ($prefix !== null) {
                $sql .= " AND prefix = :p";
                $params['p'] = strtoupper(trim($prefix));
            }
            $sql .= " ORDER BY prefix ASC LIMIT 1 FOR UPDATE";

            $config = $this->db->fetchOne($sql, $params);
            if (!$config) {
                if ($manageTx) {
                    $this->db->rollBack();
                }
                return null;
            }

            $mc = trim((string) ($config['master_code'] ?? ''));
            $mcSuffix = $mc !== '' ? '.' . $mc : '';

            $number = (int) $config['next_number'];
            $code = null;
            for ($attempt = 0; $attempt < 100; $attempt++) {
                $candidate = $config['prefix'] . '.'
                    . str_pad((string) $number, (int) $config['digit_length'], '0', STR_PAD_LEFT)
                    . $mcSuffix;
                $collides = $this->db->fetchOne(
                    "SELECT id FROM {$meta['table']} WHERE {$meta['code_col']} = :code",
                    ['code' => $candidate]
                );
                if (!$collides) {
                    $code = $candidate;
                    break;
                }
                $number++;
            }

            if ($code === null) {
                if ($manageTx) {
                    $this->db->rollBack();
                }
                return null;
            }

            $this->db->query(
                "UPDATE code_configs SET next_number = :next WHERE id = :id",
                ['next' => $number + 1, 'id' => (int) $config['id']]
            );
            if ($manageTx) {
                $this->db->commit();
            }
            return $code;
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
