<?php
require_once ROOT_PATH . '/core/Model.php';

class Role extends Model
{
    protected string $table = 'roles';

    /**
     * Role yang boleh DIPILIH saat membuat/mengubah user (Revisi 9).
     * 'finance' & 'gudang' dikecualikan -- baris rolenya tetap ada di DB
     * untuk histori, tapi tidak lagi ditawarkan sebagai pilihan aktif.
     */
    public function assignableList(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM roles
              WHERE role_slug NOT IN ('finance','gudang')
              ORDER BY role_name ASC"
        );
    }
}
