<?php
require_once ROOT_PATH . '/core/Model.php';

class ActivityLog extends Model
{
    protected string $table = 'activity_logs';

    /**
     * Pencatatan audit trail TIDAK BOLEH pernah menggagalkan aksi utama user
     * (login/logout/CRUD, dst). Kalau insert-nya gagal -- misalnya session
     * lama masih menunjuk ke user_id yang sudah tidak ada di tabel users --
     * cukup dicatat ke error_log(), jangan sampai melempar exception ke atas.
     */
    public function log(?int $userId, string $module, string $action, string $description = ''): void
    {
        try {
            $this->create([
                'user_id'     => $userId,
                'module'      => $module,
                'action'      => $action,
                'description' => $description,
                'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_by'  => $userId,
            ]);
        } catch (Throwable $e) {
            error_log('ActivityLog::log gagal: ' . $e->getMessage());
        }
    }

    /**
     * Bangun klausa WHERE + params dari filter audit trail (dipakai bareng
     * listWithFilters() & countWithFilters()).
     */
    private function buildFilterWhere(array $filters): array
    {
        $sql = " WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = :user_id";
            $params['user_id'] = $filters['user_id'];
        }
        if (!empty($filters['module'])) {
            $sql .= " AND al.module = :module";
            $params['module'] = $filters['module'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND al.created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND al.created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        return [$sql, $params];
    }

    /**
     * Daftar aktivitas + nama user, untuk halaman audit trail di modul Laporan.
     *
     * $limit/$offset diisi -> mode paginasi (tampilan layar). Dibiarkan null ->
     * mode lama: ambil maksimal 500 baris terbaru (dipakai Export Excel/PDF
     * supaya satu file berisi banyak data sekaligus).
     */
    public function listWithFilters(array $filters = [], ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildFilterWhere($filters);
        $sql = "SELECT al.*, u.full_name
                FROM activity_logs al
                LEFT JOIN users u ON u.id = al.user_id"
            . $where
            . " ORDER BY al.created_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT " . max(1, $limit) . " OFFSET " . max(0, (int) $offset);
        } else {
            $sql .= " LIMIT 500";
        }

        return $this->db->fetchAll($sql, $params);
    }

    /** Jumlah total baris audit trail yang cocok dengan filter (untuk paginasi). */
    public function countWithFilters(array $filters = []): int
    {
        [$where, $params] = $this->buildFilterWhere($filters);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS c FROM activity_logs al" . $where, $params);
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Jumlah percobaan login GAGAL dari satu IP dalam $minutes menit terakhir.
     * Dipakai AuthController untuk throttling brute-force sederhana -- tanpa
     * tabel baru, tanpa mengunci akun permanen (jendela otomatis kedaluwarsa).
     */
    public function countRecentFailedLogins(string $ip, int $minutes = 15): int
    {
        $since = date('Y-m-d H:i:s', time() - ($minutes * 60));
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM activity_logs
             WHERE module = 'auth' AND action = 'login_failed'
               AND ip_address = :ip AND created_at >= :since",
            ['ip' => $ip, 'since' => $since]
        );
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Jumlah percobaan login GAGAL untuk satu USERNAME dalam $minutes menit
     * terakhir -- dipakai AuthController untuk lockout per-akun (pelengkap
     * throttle per-IP). Cocokkan lewat description (persis, bukan LIKE) yang
     * ditulis saat login gagal: "Percobaan login gagal: <username>".
     */
    public function countRecentFailedLoginsByUser(string $username, int $minutes = 15): int
    {
        $since = date('Y-m-d H:i:s', time() - ($minutes * 60));
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS c FROM activity_logs
             WHERE module = 'auth' AND action = 'login_failed'
               AND description = :desc AND created_at >= :since",
            ['desc' => 'Percobaan login gagal: ' . $username, 'since' => $since]
        );
        return (int) ($row['c'] ?? 0);
    }

    /**
     * Sisa detik sampai kunci login lepas (0 = tidak terkunci).
     * Ambil waktu kegagalan ke-$threshold PALING BARU dalam jendela; kunci lepas
     * $minutes setelah itu. Dipakai untuk menampilkan hitung mundur di form login.
     * $col = kolom pembeda internal ('ip_address' | 'description') -- BUKAN input user.
     */
    private function lockRemaining(string $col, string $val, int $minutes, int $threshold): int
    {
        $since = date('Y-m-d H:i:s', time() - ($minutes * 60));
        $offset = max(0, $threshold - 1);
        $row = $this->db->fetchOne(
            "SELECT created_at FROM activity_logs
             WHERE module = 'auth' AND action = 'login_failed'
               AND {$col} = :v AND created_at >= :since
             ORDER BY created_at DESC LIMIT 1 OFFSET {$offset}",
            ['v' => $val, 'since' => $since]
        );
        if (!$row) {
            return 0; // kegagalan < $threshold -> tidak terkunci
        }
        return max(0, (strtotime($row['created_at']) + $minutes * 60) - time());
    }

    /** Sisa detik kunci per-IP (0 = tidak terkunci). */
    public function ipLockRemaining(string $ip, int $minutes = 15, int $threshold = 8): int
    {
        return $this->lockRemaining('ip_address', $ip, $minutes, $threshold);
    }

    /** Sisa detik kunci per-akun (0 = tidak terkunci). */
    public function userLockRemaining(string $username, int $minutes = 15, int $threshold = 5): int
    {
        return $this->lockRemaining('description', 'Percobaan login gagal: ' . $username, $minutes, $threshold);
    }

    /**
     * Daftar module unik yang pernah tercatat -- dipakai untuk dropdown filter.
     */
    public function distinctModules(): array
    {
        $rows = $this->db->fetchAll("SELECT DISTINCT module FROM activity_logs ORDER BY module ASC");
        return array_column($rows, 'module');
    }
}
