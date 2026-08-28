<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Mapping User -> PIC (Revisi 9).
 *
 * Menentukan PIC mana yang "terkait" dengan seorang user, dipakai
 * CashController untuk membatasi baris Kas yang boleh dilihat/diubah oleh
 * role Purchase / PIC Project / Admin Project. Dikelola terpusat lewat
 * Master Data > PIC Mapping -- TIDAK ada nama PIC yang di-hardcode di kode.
 *
 * Hard delete (bukan soft): mapping bukan transaksi, tidak perlu Trash.
 */
class UserPicAssignment extends Model
{
    protected string $table = 'user_pic_assignments';

    /**
     * Daftar nama PIC yang terkait dengan satu user. Array kosong artinya
     * user tersebut belum di-assign PIC apa pun (kalau dia role ber-scope,
     * berarti dia belum boleh melihat Kas siapa pun).
     */
    public function picNamesForUser(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT pic_name FROM user_pic_assignments WHERE user_id = :uid ORDER BY pic_name ASC",
            ['uid' => $userId]
        );
        return array_column($rows, 'pic_name');
    }

    /**
     * Semua mapping + nama/role user, untuk halaman Master Data > PIC Mapping.
     */
    public function listWithUser(): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, u.full_name, u.username, r.role_name
               FROM user_pic_assignments a
               JOIN users u ON u.id = a.user_id
               JOIN roles r ON r.id = u.role_id
              ORDER BY u.full_name ASC, a.pic_name ASC"
        );
    }

    /**
     * Semua nama PIC unik yang terdaftar di mapping (lintas user) -- jadi
     * sumber pilihan dropdown PIC di form Kas untuk role yang lihat semua
     * (Super Admin / Accounting / Project Manager).
     */
    public function allPicNames(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT pic_name FROM user_pic_assignments ORDER BY pic_name ASC"
        );
        return array_column($rows, 'pic_name');
    }

    public function exists(int $userId, string $picName): bool
    {
        return (bool) $this->db->fetchOne(
            "SELECT id FROM user_pic_assignments WHERE user_id = :uid AND pic_name = :pn",
            ['uid' => $userId, 'pn' => $picName]
        );
    }
}
