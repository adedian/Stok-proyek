<?php
require_once ROOT_PATH . '/core/Model.php';

class Role extends Model
{
    protected string $table = 'roles';

    /**
     * Role yang boleh DIPILIH saat membuat/mengubah user (Revisi 9).
     * 'gudang' dikecualikan (nonaktif); 'finance' DIAKTIFKAN KEMBALI (2026-10-05) sebagai alias Accounting
     * (lihat roleAlias()). Baris role gudang tetap ada di DB
     * untuk histori, tapi tidak lagi ditawarkan sebagai pilihan aktif.
     */
    public function assignableList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM roles
              WHERE role_slug NOT IN ('gudang')
              ORDER BY role_name ASC"
        );
    }

    public function slugById(int $id): ?string
    {
        $row = $this->db->fetchOne("SELECT role_slug FROM roles WHERE id = :id", ['id' => $id]);
        return $row['role_slug'] ?? null;
    }
}
