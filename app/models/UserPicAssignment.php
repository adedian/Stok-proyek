<?php
require_once ROOT_PATH . '/core/Model.php';

/**
 * Mapping User -> PIC (Revisi 9).
 *
 * Menentukan PIC mana yang "terkait" dengan seorang user, dipakai
 * CashController untuk membatasi baris Kas yang boleh dilihat/diubah oleh
 * role Purchase / PIC Project / Admin Project. Dikelola terpusat lewat
 * Master Data > PIC Kas -- TIDAK ada nama PIC yang di-hardcode di kode.
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
     * Semua mapping + nama/role user, untuk halaman Master Data > PIC Kas.
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

    // ===================== Second-level auth Kas =====================

    /** Ada PIC (apa pun) untuk user ini? */
    public function hasAnyPic(int $userId): bool
    {
        return (bool) $this->db->fetchOne(
            "SELECT id FROM user_pic_assignments WHERE user_id = :uid LIMIT 1",
            ['uid' => $userId]
        );
    }

    /**
     * User AKTIF yang WAJIB punya PIC Kas (role non-exempt: Purchase, PIC
     * Project, Admin Project) tapi BELUM punya PIC aktif ber-password -->
     * mereka kena tembok "PIC Kas Belum Terdaftar" saat buka modul Kas.
     * Dipakai untuk panel bantu di halaman PIC Kas.
     */
    public function usersNeedingPicKas(): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.full_name, u.username, r.role_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.deleted_at IS NULL
                AND u.status = 'active'
                AND r.role_slug IN ('purchase', 'pic_project', 'admin_project')
                AND NOT EXISTS (
                    SELECT 1 FROM user_pic_assignments p
                     WHERE p.user_id = u.id AND p.is_active = 1
                       AND p.pic_password IS NOT NULL AND p.pic_password <> ''
                )
              ORDER BY r.role_name ASC, u.full_name ASC"
        );
    }

    /**
     * Nama PIC (dari master mapping) yang PEMILIK akunnya ber-divisi Kas
     * termasuk dalam $divisions. $divisions = null -> semua PIC.
     * Dipakai untuk isi dropdown filter PIC di daftar Kas untuk role
     * "lihat semua" (Super Admin/Accounting -> semua; Project Manager ->
     * hanya PIC divisi 'project' = pic_project + admin_project), sehingga
     * PIC muncul walau belum punya transaksi Kas.
     */
    public function picNamesForDivisions(?array $divisions): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT upa.pic_name, r.role_slug
               FROM user_pic_assignments upa
               JOIN users u ON u.id = upa.user_id AND u.deleted_at IS NULL
               JOIN roles r ON r.id = u.role_id
              WHERE upa.pic_name <> ''"
        );
        $names = [];
        foreach ($rows as $row) {
            if ($divisions === null
                || in_array(kasDivisionForRole($row['role_slug']), $divisions, true)) {
                $names[$row['pic_name']] = true;
            }
        }
        $names = array_keys($names);
        sort($names);
        return $names;
    }

    /** Ada PIC AKTIF + sudah ber-password (siap dipakai login Kas)? */
    public function hasLoginablePic(int $userId): bool
    {
        return (bool) $this->db->fetchOne(
            "SELECT id FROM user_pic_assignments
              WHERE user_id = :uid AND is_active = 1 AND pic_password IS NOT NULL AND pic_password <> ''
              LIMIT 1",
            ['uid' => $userId]
        );
    }

    /** Nama PIC aktif + ber-password milik user -- untuk dropdown form login Kas. */
    public function loginablePicNames(int $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT pic_name FROM user_pic_assignments
              WHERE user_id = :uid AND is_active = 1 AND pic_password IS NOT NULL AND pic_password <> ''
           ORDER BY pic_name ASC",
            ['uid' => $userId]
        );
        return array_column($rows, 'pic_name');
    }

    /**
     * Kandidat login Kas: cocokkan nama PIC ATAU username PIC, dalam lingkup
     * mapping milik user tsb, harus aktif & ber-password. NULL kalau tak ada.
     */
    public function findLoginCandidate(int $userId, string $nameOrUsername): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM user_pic_assignments
              WHERE user_id = :uid AND is_active = 1
                AND pic_password IS NOT NULL AND pic_password <> ''
                AND (pic_name = :n1 OR pic_username = :n2)
              LIMIT 1",
            ['uid' => $userId, 'n1' => $nameOrUsername, 'n2' => $nameOrUsername]
        );
        return $row ?: null;
    }

    /** Username PIC sudah dipakai baris lain? (username global-unik) */
    public function picUsernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM user_pic_assignments WHERE pic_username = :u";
        $params = ['u' => $username];
        if ($excludeId) {
            $sql .= " AND id <> :ex";
            $params['ex'] = $excludeId;
        }
        return (bool) $this->db->fetchOne($sql, $params);
    }

    public function rowByUserAndName(int $userId, string $picName): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM user_pic_assignments WHERE user_id = :uid AND pic_name = :pn LIMIT 1",
            ['uid' => $userId, 'pn' => $picName]
        );
        return $row ?: null;
    }

    /** Satu baris mapping (aman IDOR: WAJIB cocok user_id). */
    public function rowForUser(int $id, int $userId): ?array
    {
        $row = $this->db->fetchOne(
            "SELECT * FROM user_pic_assignments WHERE id = :id AND user_id = :uid LIMIT 1",
            ['id' => $id, 'uid' => $userId]
        );
        return $row ?: null;
    }

    /**
     * Semua mapping PIC milik user (untuk halaman "Pengaturan Akun" -> ganti
     * Password Kas sendiri). Hanya yang SUDAH ber-password (bisa dipakai login
     * Kas) yang relevan buat diganti.
     */
    public function credentialedAssignmentsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT id, pic_name, pic_username, is_active
               FROM user_pic_assignments
              WHERE user_id = :uid AND pic_password IS NOT NULL AND pic_password <> ''
           ORDER BY pic_name ASC",
            ['uid' => $userId]
        );
    }

    /**
     * role_slug pemilik (akun) sebuah nama PIC -- dipakai menentukan `division`
     * transaksi Kas saat dibuat. NULL kalau nama PIC belum di-mapping ke user.
     */
    public function ownerRoleSlugForPic(string $picName): ?string
    {
        $row = $this->db->fetchOne(
            "SELECT r.role_slug
               FROM user_pic_assignments a
               JOIN users u ON u.id = a.user_id
               JOIN roles r ON r.id = u.role_id
              WHERE a.pic_name = :p
           ORDER BY a.id ASC LIMIT 1",
            ['p' => $picName]
        );
        return $row['role_slug'] ?? null;
    }

    public function setCredential(int $id, ?string $username, string $passwordHash, bool $isActive): int
    {
        return $this->updateById($id, [
            'pic_username' => ($username !== null && $username !== '') ? $username : null,
            'pic_password' => $passwordHash,
            'is_active'    => $isActive ? 1 : 0,
        ]);
    }

    /** Semua mapping + kredensial + user, untuk halaman PIC Kas (Super Admin). */
    public function listWithUserAndCredential(): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, u.full_name, u.username, r.role_name
               FROM user_pic_assignments a
               JOIN users u ON u.id = a.user_id
               JOIN roles r ON r.id = u.role_id
              ORDER BY u.full_name ASC, a.pic_name ASC"
        );
    }
}
