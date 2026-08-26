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
                ['deleted_at' => date('Y-m-d H:i:s')],
                "{$this->primaryKey} = :pk",
                ['pk' => $id]
            );
        }
        $sql = "DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id";
        return $this->db->query($sql, ['id' => $id])->rowCount();
    }
}
