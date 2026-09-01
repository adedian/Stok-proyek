<?php
require_once ROOT_PATH . '/core/Database.php';

/**
 * Base Model
 * Semua model turunan cukup set $table dan $primaryKey.
 */
abstract class Model
{
    protected Database $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected bool $softDelete = false; // set true di model yang punya kolom deleted_at

    public function __construct()
    {
        $this->db = new Database();
    }

    public function find(int $id)
    {
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id";
        if ($this->softDelete) {
            $sql .= " AND deleted_at IS NULL";
        }
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    public function all(string $orderBy = 'id DESC'): array
    {
        $sql = "SELECT * FROM {$this->table}";
        if ($this->softDelete) {
            $sql .= " WHERE deleted_at IS NULL";
        }
        $sql .= " ORDER BY {$orderBy}";
        return $this->db->fetchAll($sql);
    }

    public function create(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    public function updateById(int $id, array $data): int
    {
        return $this->db->update($this->table, $data, "{$this->primaryKey} = :pk", ['pk' => $id]);
    }

    public function deleteById(int $id): int
    {
        if ($this->softDelete) {
            return $this->db->update(
                $this->table,
                ['deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => currentUserId()],
                "{$this->primaryKey} = :pk",
                ['pk' => $id]
            );
        }
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        return $this->db->query($sql, ['id' => $id])->rowCount();
    }

    /**
     * Kebalikan dari deleteById() (soft delete) -- kembalikan record ke aktif
     * pakai ID asli, tidak membuat baris baru. Tidak berlaku untuk model yang
     * tidak soft-delete (tidak pernah masuk trash).
     */
    public function restoreById(int $id): int
    {
        if (!$this->softDelete) {
            return 0;
        }
        return $this->db->update(
            $this->table,
            ['deleted_at' => null, 'deleted_by' => null],
            "{$this->primaryKey} = :pk",
            ['pk' => $id]
        );
    }

    /**
     * Hard delete permanen dari Trash. Dipanggil hanya dari TrashController,
     * setelah baris sudah berstatus soft-deleted. Kalau baris masih dipakai
     * transaksi lain, MySQL akan menolak (FK constraint) -- caller yang
     * menangkap PDOException-nya (lihat TrashController::forceDelete()).
     */
    public function forceDeleteById(int $id): int
    {
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        return $this->db->query($sql, ['id' => $id])->rowCount();
    }

    public function findTrashed(int $id)
    {
        if (!$this->softDelete) {
            return null;
        }
        $sql = "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id AND deleted_at IS NOT NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    public function trashedList(): array
    {
        if (!$this->softDelete) {
            return [];
        }
        return $this->db->fetchAll("SELECT * FROM {$this->table} WHERE deleted_at IS NOT NULL ORDER BY deleted_at DESC");
    }

    /**
     * ID record (yang belum soft-deleted) dengan $dateCol dalam rentang tanggal
     * inklusif. Dipakai fitur "Hapus per Rentang Tanggal" -- $dateCol berasal
     * dari string konstan di controller (mis. 'out_date'), BUKAN input user.
     * DATE() supaya kolom DATETIME (mis. cash_transactions.trx_date) ikut cocok.
     */
    public function idsByDateRange(string $dateCol, string $from, string $to): array
    {
        $sql = "SELECT {$this->primaryKey} AS id FROM {$this->table} WHERE DATE({$dateCol}) BETWEEN :f AND :t";
        if ($this->softDelete) {
            $sql .= " AND deleted_at IS NULL";
        }
        $sql .= " ORDER BY {$dateCol} ASC, {$this->primaryKey} ASC";
        return array_map('intval', array_column($this->db->fetchAll($sql, ['f' => $from, 't' => $to]), 'id'));
    }

    /**
     * True kalau baris ini masih dirujuk FK "keras" (RESTRICT / NO ACTION) dari
     * tabel lain -- artinya DELETE permanen PASTI ditolak MySQL. FK ber-aturan
     * CASCADE / SET NULL diabaikan karena tidak menghalangi hard delete.
     *
     * Dipakai Tempat Sampah supaya hanya menampilkan baris yang benar-benar
     * bisa dihapus permanen (tidak "nyangkut" di transaksi lain).
     */
    public function isReferenced(int $id): bool
    {
        foreach ($this->blockingForeignKeys() as $fk) {
            $hit = $this->db->fetchOne(
                "SELECT 1 FROM `{$fk['table']}` WHERE `{$fk['column']}` = :id LIMIT 1",
                ['id' => $id]
            );
            if ($hit) {
                return true;
            }
        }
        return false;
    }

    /**
     * Daftar (tabel anak, kolom) yang FK ke PK tabel ini dengan aturan hapus
     * yang MENGHALANGI DELETE. Hasil di-cache per tabel selama request.
     */
    private function blockingForeignKeys(): array
    {
        static $cache = [];
        if (isset($cache[$this->table])) {
            return $cache[$this->table];
        }
        $rows = $this->db->fetchAll(
            "SELECT k.TABLE_NAME AS child_table, k.COLUMN_NAME AS child_column
               FROM information_schema.KEY_COLUMN_USAGE k
               JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                 ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
                AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
              WHERE k.REFERENCED_TABLE_SCHEMA = DATABASE()
                AND k.REFERENCED_TABLE_NAME = :t
                AND k.REFERENCED_COLUMN_NAME = :pk
                AND r.DELETE_RULE IN ('RESTRICT', 'NO ACTION')",
            ['t' => $this->table, 'pk' => $this->primaryKey]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['table' => $r['child_table'], 'column' => $r['child_column']];
        }
        return $cache[$this->table] = $out;
    }
}
