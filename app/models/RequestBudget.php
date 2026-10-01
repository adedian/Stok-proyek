<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/ProjectUserAccess.php';

/**
 * RequestBudget -- pengajuan budget project (modul mandiri).
 *
 * Alur status (nilai DB konsisten, label Indonesia hanya di UI):
 *   DRAFT -> PENDING_APPROVAL -> APPROVED -> PURCHASE_COMPLETED -> FORWARDED -> COMPLETED
 *   PENDING_APPROVAL -> REJECTED -> (revisi) DRAFT
 *
 *   - PENDING_APPROVAL: menunggu approval DUA slot (pm = Project Manager, purchase = Purchase),
 *     disimpan terpisah di request_budget_approvals. Baru APPROVED kalau KEDUANYA approved.
 *   - APPROVED = "Menunggu Proses Purchase": Andy melengkapi PO/Invoice/dokumen pendukung.
 *   - PURCHASE_COMPLETED = "Dilengkapi Andy": data lengkap, siap diajukan.
 *
 *   - Hak approval (izin 'approve') dan hak pengajuan lanjutan ('forward') terpisah: Vicky (PM)
 *     boleh approve slot PM tapi TIDAK 'purchase_process'/'forward'; hanya Andy (Purchase) /
 *     Super Admin yang boleh melengkapi data Purchase dan meneruskan ke Purwati/Nissa,
 *     itupun hanya kalau KEDUA approval sudah selesai dan data wajib lengkap.
 *
 * Semua perpindahan status lewat transition() yang memakai UPDATE bersyarat
 * (WHERE status = status_lama) sehingga validasi status dilakukan atomik di DB,
 * bukan dipercaya dari form/frontend.
 *
 * Modul ini TIDAK menyentuh PO / Pembayaran / Kas / Invoice / Stok.
 */
class RequestBudget extends Model
{
    protected string $table = 'request_budgets';
    protected bool $softDelete = true;

    public const DRAFT = 'DRAFT';
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';
    public const APPROVED = 'APPROVED';
    public const PURCHASE_COMPLETED = 'PURCHASE_COMPLETED';
    public const REJECTED = 'REJECTED';
    public const FORWARDED = 'FORWARDED';
    public const COMPLETED = 'COMPLETED';

    public const STATUS_LABELS = [
        self::DRAFT => 'Draft',
        self::PENDING_APPROVAL => 'Menunggu Approval',
        self::APPROVED => 'Disetujui - Menunggu Proses Purchase',
        self::PURCHASE_COMPLETED => 'Dilengkapi Andy (Siap Diajukan)',
        self::REJECTED => 'Ditolak',
        self::FORWARDED => 'Diajukan ke Purwati/Nissa',
        self::COMPLETED => 'Selesai',
    ];

    public const STATUS_BADGES = [
        self::DRAFT => 'secondary',
        self::PENDING_APPROVAL => 'warning text-dark',
        self::APPROVED => 'success',
        self::PURCHASE_COMPLETED => 'info text-dark',
        self::REJECTED => 'danger',
        self::FORWARDED => 'primary',
        self::COMPLETED => 'dark',
    ];

    /** Slot approval: kunci => [label, role_slug yang mengisinya]. Role = peran jabatan, bukan nama user. */
    public const APPROVAL_SLOTS = [
        'pm'       => ['label' => 'Approval Project Manager', 'role' => ROLE_PROJECT_MANAGER],
        'purchase' => ['label' => 'Approval Purchase',        'role' => ROLE_PURCHASE],
    ];

    /** Tujuan pengajuan lanjutan (penerima, BUKAN hak akses -- Purwati belum punya akun di aplikasi). */
    public const FORWARD_DESTINATIONS = ['Purwati', 'Nissa', 'Purwati & Nissa'];
    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s] ?? (string) $s;
    }

    public static function statusBadge(?string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    // Nomor otomatis (RB-0001) -- atomik, aman dari 2 user bersamaan
    // ------------------------------------------------------------------

    /** Nomor berikutnya TANPA menaikkan counter (pratinjau di form). */
    public function previewNumber(): string
    {
        $row = $this->db->fetchOne(
            "SELECT next_number FROM document_number_counters WHERE doc_type = 'request_budget' AND year = 0"
        );
        return self::formatNumber($row ? (int) $row['next_number'] : 1);
    }

    /**
     * Ambil + naikkan counter. SELECT ... FOR UPDATE mengunci baris counter,
     * jadi dua request bersamaan selalu dapat nomor berbeda. Aman dipanggil di
     * dalam transaction caller (nesting-safe, pola CashNumber::next()).
     */
    public function nextNumber(): string
    {
        $manageTx = !$this->db->inTransaction();
        if ($manageTx) {
            $this->db->beginTransaction();
        }
        try {
            $row = $this->db->fetchOne(
                "SELECT id, next_number FROM document_number_counters
                  WHERE doc_type = 'request_budget' AND year = 0 FOR UPDATE"
            );
            if ($row) {
                $number = (int) $row['next_number'];
                $this->db->query(
                    "UPDATE document_number_counters SET next_number = :n WHERE id = :id",
                    ['n' => $number + 1, 'id' => $row['id']]
                );
            } else {
                $number = 1;
                $this->db->insert('document_number_counters', [
                    'doc_type' => 'request_budget', 'year' => 0, 'next_number' => 2,
                ]);
            }
            if ($manageTx) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($manageTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return self::formatNumber($number);
    }

    public static function formatNumber(int $n): string
    {
        return 'RB-' . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------
    // Scope akses (siapa boleh melihat request mana)
    // ------------------------------------------------------------------

    /**
     * Cakupan lihat untuk user yang login:
     *  - mode 'all'     : semua request (Super Admin, atau akun dengan izin view_all -- Vicky & Andy)
     *  - mode 'project' : request buatan sendiri ATAU di project yang di-assign (Project > Akses)
     */
    public function scopeForCurrentUser(): array
    {
        $uid = (int) currentUserId();
        if (currentUserRole() === ROLE_SUPER_ADMIN) {
            return ['mode' => 'all', 'user_id' => $uid, 'project_ids' => []];
        }
        if (can('request_budget', 'view_all')) {
            return ['mode' => 'all', 'user_id' => $uid, 'project_ids' => []];
        }
        $ids = array_map(fn($p) => (int) $p['id'], (new ProjectUserAccess())->projectsForUser($uid));
        return ['mode' => 'project', 'user_id' => $uid, 'project_ids' => $ids];
    }

    /** @return array [sql-fragment (diawali AND), params] untuk alias tabel $a. */
    public function scopeSql(array $scope, string $a = 'rb'): array
    {
        if ($scope['mode'] === 'all') {
            return ['', []];
        }
        $sql = " AND ({$a}.requester_user_id = :scuid";
        $params = ['scuid' => $scope['user_id']];
        if (!empty($scope['project_ids'])) {
            $marks = [];
            foreach ($scope['project_ids'] as $i => $pid) {
                $marks[] = ":scpj{$i}";
                $params["scpj{$i}"] = (int) $pid;
            }
            $sql .= " OR {$a}.project_id IN (" . implode(', ', $marks) . ")";
        }
        return [$sql . ")", $params];
    }

    /** Guard satu baris (detail/print/aksi) -- logika identik scopeSql(). */
    public function canView(array $rb, ?array $scope = null): bool
    {
        $scope = $scope ?? $this->scopeForCurrentUser();
        if ($scope['mode'] === 'all') {
            return true;
        }
        return (int) $rb['requester_user_id'] === (int) $scope['user_id']
            || in_array((int) $rb['project_id'], $scope['project_ids'], true);
    }

    /** Project yang boleh dipilih saat membuat request: Super Admin semua, lainnya hanya yang di-assign. */
    public function selectableProjects(): array
    {
        // Super Admin & akun dengan izin view_all (Purchase lintas project) boleh memilih semua
        // project aktif; PIC/Admin Project hanya project yang di-assign (Project > Akses).
        if (currentUserRole() === ROLE_SUPER_ADMIN || can('request_budget', 'view_all')) {
            return $this->db->fetchAll(
                "SELECT id, project_code, project_name FROM projects
                  WHERE deleted_at IS NULL AND status != 'closed' ORDER BY project_name ASC"
            );
        }
        return (new ProjectUserAccess())->projectsForUser((int) currentUserId());
    }

    public function projectAllowedForCreate(int $projectId): bool
    {
        foreach ($this->selectableProjects() as $p) {
            if ((int) $p['id'] === $projectId) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Query
    // ------------------------------------------------------------------

    private function baseFrom(): string
    {
        return "FROM request_budgets rb
                JOIN projects p ON p.id = rb.project_id
                JOIN users rq ON rq.id = rb.requester_user_id
                LEFT JOIN users ap ON ap.id = rb.approved_by
                LEFT JOIN users fw ON fw.id = rb.forwarded_by";
    }

    /** @return array [where-sql, params] */
    private function filterWhere(array $filters, array $scope): array
    {
        $sql = " WHERE rb.deleted_at IS NULL";
        $params = [];

        [$ssql, $sparams] = $this->scopeSql($scope);
        $sql .= $ssql;
        $params += $sparams;

        if (!empty($filters['keyword'])) {
            // Smart Search: nomor (kode), pengaju, project, keperluan, dan nama barang/kebutuhan.
            [$kwSql, $kwParams] = SmartSearch::clause(
                $filters['keyword'],
                [
                    'rq.full_name', 'p.project_name', 'rb.purpose',
                    "(SELECT GROUP_CONCAT(i.item_name SEPARATOR ' ') FROM request_budget_items i WHERE i.request_budget_id = rb.id)",
                ],
                ['rb.request_number'],
                'rbkw'
            );
            if ($kwSql !== '') {
                $sql .= " AND {$kwSql}";
                $params += $kwParams;
            }
        }
        if (!empty($filters['project_id'])) {
            $sql .= " AND rb.project_id = :f_project";
            $params['f_project'] = (int) $filters['project_id'];
        }
        if (!empty($filters['requester_id'])) {
            $sql .= " AND rb.requester_user_id = :f_req";
            $params['f_req'] = (int) $filters['requester_id'];
        }
        if (!empty($filters['status']) && isset(self::STATUS_LABELS[$filters['status']])) {
            $sql .= " AND rb.status = :f_status";
            $params['f_status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $sql .= " AND rb.request_date >= :f_from";
            $params['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND rb.request_date <= :f_to";
            $params['f_to'] = $filters['date_to'];
        }
        return [$sql, $params];
    }

    public function countFiltered(array $filters, array $scope): int
    {
        [$where, $params] = $this->filterWhere($filters, $scope);
        $row = $this->db->fetchOne("SELECT COUNT(*) AS n " . $this->baseFrom() . $where, $params);
        return (int) ($row['n'] ?? 0);
    }

    public function listPaginated(array $filters, array $scope, int $limit, int $offset): array
    {
        [$where, $params] = $this->filterWhere($filters, $scope);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT rb.*, p.project_name, rq.full_name AS requester_name,
                    ap.full_name AS approved_by_name, fw.full_name AS forwarded_by_name "
            . $this->baseFrom() . $where
            . " ORDER BY rb.request_date DESC, rb.id DESC LIMIT {$limit} OFFSET {$offset}",
            $params
        );
    }

    public function findWithRelations(int $id)
    {
        return $this->db->fetchOne(
            "SELECT rb.*, p.project_name, p.project_code, rq.full_name AS requester_name, rqr.role_name AS requester_role,
                    ap.full_name AS approved_by_name, rj.full_name AS rejected_by_name,
                    fw.full_name AS forwarded_by_name, cp.full_name AS completed_by_name, pc.full_name AS purchase_completed_by_name,
                    sg_rq.signature_image AS requester_signature, sg_rq.name AS requester_signature_name,
                    sg_ap.signature_image AS approver_signature, sg_ap.name AS approver_signature_name
               FROM request_budgets rb
               JOIN projects p ON p.id = rb.project_id
               JOIN users rq ON rq.id = rb.requester_user_id
               LEFT JOIN roles rqr ON rqr.id = rq.role_id
               LEFT JOIN users ap ON ap.id = rb.approved_by
               LEFT JOIN users rj ON rj.id = rb.rejected_by
               LEFT JOIN users fw ON fw.id = rb.forwarded_by
               LEFT JOIN users cp ON cp.id = rb.completed_by
               LEFT JOIN users pc ON pc.id = rb.purchase_completed_by
               LEFT JOIN signatures sg_rq ON sg_rq.user_id = rb.requester_user_id AND sg_rq.deleted_at IS NULL
               LEFT JOIN signatures sg_ap ON sg_ap.user_id = rb.approved_by AND sg_ap.deleted_at IS NULL
              WHERE rb.id = :id AND rb.deleted_at IS NULL",
            ['id' => $id]
        );
    }

    public function items(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM request_budget_items WHERE request_budget_id = :id ORDER BY id ASC",
            ['id' => $id]
        );
    }

    public function history(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT h.id, h.request_budget_id, h.user_id, h.action, h.old_status, h.new_status, h.notes, h.created_at,
                    u.full_name AS user_name, COALESCE(h.role_name, r.role_name) AS role_name
               FROM request_budget_history h
               LEFT JOIN users u ON u.id = h.user_id
               LEFT JOIN roles r ON r.id = u.role_id
              WHERE h.request_budget_id = :id ORDER BY h.created_at ASC, h.id ASC",
            ['id' => $id]
        );
    }

    /** Nama role (mis. 'PM', 'Purchase') pelaku -- disimpan sebagai snapshot di riwayat/approval/pengajuan. */
    public function roleNameOf(?int $userId): ?string
    {
        if (!$userId) {
            return null;
        }
        $row = $this->db->fetchOne(
            "SELECT r.role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = :id",
            ['id' => $userId]
        );
        return $row['role_name'] ?? null;
    }

    public function addHistory(int $id, ?int $userId, string $action, ?string $old, ?string $new, ?string $notes = null): void
    {
        $this->db->insert('request_budget_history', [
            'request_budget_id' => $id,
            'user_id'           => $userId,
            'role_name'         => $this->roleNameOf($userId),
            'action'            => $action,
            'old_status'        => $old,
            'new_status'        => $new,
            'notes'             => $notes,
        ]);
    }

    // ------------------------------------------------------------------
    // Simpan header + item (total DIHITUNG ULANG di sini, bukan dari JS)
    // ------------------------------------------------------------------

    /**
     * Normalisasi baris item & hitung total di backend.
     * @param array $rows [['item_name','description','qty','unit_name','price','notes'], ...]
     * @return array [items-yang-sudah-dihitung, total]
     */
    public static function computeItems(array $rows): array
    {
        $items = [];
        $total = 0.0;
        foreach ($rows as $r) {
            $name = trim((string) ($r['item_name'] ?? ''));
            $qty = (float) ($r['qty'] ?? 0);
            $price = (float) ($r['price'] ?? 0);
            if ($name === '' && $qty <= 0 && $price <= 0) {
                continue; // baris kosong diabaikan
            }
            $line = round($qty * $price, 2);
            $total += $line;
            $items[] = [
                'item_name'            => $name,
                'description'          => trim((string) ($r['description'] ?? '')) ?: null,
                'qty'                  => $qty,
                'unit_name'            => trim((string) ($r['unit_name'] ?? '')) ?: null,
                'estimated_unit_price' => $price,
                'estimated_total'      => $line,
                'notes'                => trim((string) ($r['notes'] ?? '')) ?: null,
            ];
        }
        return [$items, round($total, 2)];
    }

    public function replaceItems(int $id, array $items): void
    {
        $this->db->query("DELETE FROM request_budget_items WHERE request_budget_id = :id", ['id' => $id]);
        foreach ($items as $it) {
            $this->db->insert('request_budget_items', ['request_budget_id' => $id] + $it);
        }
    }

    /**
     * Pindah status secara ATOMIK: hanya berhasil kalau status saat ini masih salah
     * satu dari $fromStatuses. false = sudah diproses user lain / status tidak valid.
     */
    public function transition(int $id, array $fromStatuses, string $toStatus, array $extra = []): bool
    {
        $marks = [];
        $params = ['id' => $id, 'to_status' => $toStatus];
        foreach (array_values($fromStatuses) as $i => $st) {
            $marks[] = ":from{$i}";
            $params["from{$i}"] = $st;
        }
        $sets = ['status = :to_status'];
        foreach ($extra as $col => $val) {
            if (!preg_match('/^[a-z_]+$/', $col)) {
                continue;
            }
            $sets[] = "{$col} = :x_{$col}";
            $params["x_{$col}"] = $val;
        }
        $stmt = $this->db->query(
            "UPDATE request_budgets SET " . implode(', ', $sets)
            . " WHERE id = :id AND deleted_at IS NULL AND status IN (" . implode(', ', $marks) . ")",
            $params
        );
        return $stmt->rowCount() > 0;
    }

    // ------------------------------------------------------------------
    // Hitungan untuk Dashboard / notifikasi
    // ------------------------------------------------------------------

    public function countByStatus(string $status, array $scope): int
    {
        [$ssql, $sparams] = $this->scopeSql($scope);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM request_budgets rb
              WHERE rb.deleted_at IS NULL AND rb.status = :st" . $ssql,
            ['st' => $status] + $sparams
        );
        return (int) ($row['n'] ?? 0);
    }

    public function countVisible(array $scope): int
    {
        [$ssql, $sparams] = $this->scopeSql($scope);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM request_budgets rb WHERE rb.deleted_at IS NULL" . $ssql,
            $sparams
        );
        return (int) ($row['n'] ?? 0);
    }

    /** Pengaju-pengaju yang pernah membuat request (dropdown filter), dibatasi scope. */
    public function requesterOptions(array $scope): array
    {
        [$ssql, $sparams] = $this->scopeSql($scope);
        return $this->db->fetchAll(
            "SELECT DISTINCT rq.id, rq.full_name
               FROM request_budgets rb JOIN users rq ON rq.id = rb.requester_user_id
              WHERE rb.deleted_at IS NULL" . $ssql . " ORDER BY rq.full_name ASC",
            $sparams
        );
    }

    /** user_id aktif yang berhak (can) melakukan $action -- penerima notifikasi. */
    public function userIdsWithPermission(string $action): array
    {
        require_once ROOT_PATH . '/app/models/User.php';
        $ids = [];
        foreach ((new User())->activeListWithRole() as $u) {
            if (canForUser((int) $u['id'], $u['role_slug'], 'request_budget', $action)) {
                $ids[] = (int) $u['id'];
            }
        }
        return $ids;
    }
    // ------------------------------------------------------------------
    // Approval per slot (Project Manager + Purchase)
    // ------------------------------------------------------------------

    /** Slot yang boleh diisi user login: 'pm' / 'purchase' / 'all' (Super Admin) / null. */
    public function approvalSlotForCurrentUser(): ?string
    {
        $role = currentUserRole();
        if ($role === ROLE_SUPER_ADMIN) {
            return 'all';
        }
        foreach (self::APPROVAL_SLOTS as $key => $def) {
            if ($def['role'] === $role) {
                return $key;
            }
        }
        return null;
    }

    public function approvals(int $id): array
    {
        $rows = $this->db->fetchAll(
            "SELECT a.*, u.full_name AS approver_name
               FROM request_budget_approvals a
               LEFT JOIN users u ON u.id = a.approver_user_id
              WHERE a.request_budget_id = :id",
            ['id' => $id]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['slot']] = $r;
        }
        return $out;
    }

    /** Buat ulang 2 slot approval PENDING (dipanggil saat request disubmit). */
    public function createApprovals(int $id): void
    {
        $this->db->query("DELETE FROM request_budget_approvals WHERE request_budget_id = :id", ['id' => $id]);
        foreach (array_keys(self::APPROVAL_SLOTS) as $slot) {
            $this->db->insert('request_budget_approvals', ['request_budget_id' => $id, 'slot' => $slot, 'status' => 'PENDING']);
        }
    }

    public function clearApprovals(int $id): void
    {
        $this->db->query("DELETE FROM request_budget_approvals WHERE request_budget_id = :id", ['id' => $id]);
    }

    /**
     * Isi slot approval secara ATOMIK (hanya kalau slot masih PENDING).
     * @param string[] $slots slot yang diisi; Super Admin boleh mengisi semua slot yang masih pending
     * @return int jumlah slot yang berhasil diubah
     */
    public function actApprovals(int $id, array $slots, string $status, ?int $userId, ?string $role, ?string $note): int
    {
        $n = 0;
        foreach ($slots as $slot) {
            $stmt = $this->db->query(
                "UPDATE request_budget_approvals
                    SET status = :st, approver_user_id = :uid, approver_role = :role, note = :note, acted_at = NOW()
                  WHERE request_budget_id = :id AND slot = :slot AND status = 'PENDING'",
                ['st' => $status, 'uid' => $userId, 'role' => $role, 'note' => $note, 'id' => $id, 'slot' => $slot]
            );
            $n += $stmt->rowCount();
        }
        return $n;
    }

    public function pendingApprovalSlots(int $id): array
    {
        $rows = $this->db->fetchAll(
            "SELECT slot FROM request_budget_approvals WHERE request_budget_id = :id AND status = 'PENDING'",
            ['id' => $id]
        );
        return array_column($rows, 'slot');
    }

    /** Berapa request yang approval slot-nya (milik user ini) masih PENDING -- untuk alert Dashboard. */
    public function countAwaitingMyApproval(array $scope): int
    {
        $slot = $this->approvalSlotForCurrentUser();
        if ($slot === null) {
            return 0;
        }
        [$ssql, $sparams] = $this->scopeSql($scope);
        $slotSql = $slot === 'all' ? '' : ' AND a.slot = :myslot';
        // Slot Purchase baru "menunggu" Purchase setelah slot PM approved.
        $seqSql = $slot === 'purchase'
            ? " AND EXISTS (SELECT 1 FROM request_budget_approvals pm WHERE pm.request_budget_id = rb.id AND pm.slot = 'pm' AND pm.status = 'APPROVED')"
            : '';
        $params = $sparams;
        if ($slot !== 'all') {
            $params['myslot'] = $slot;
        }
        $row = $this->db->fetchOne(
            "SELECT COUNT(DISTINCT rb.id) AS n
               FROM request_budgets rb
               JOIN request_budget_approvals a ON a.request_budget_id = rb.id AND a.status = 'PENDING'{$slotSql}
              WHERE rb.deleted_at IS NULL AND rb.status = 'PENDING_APPROVAL'" . $seqSql . $ssql,
            $params
        );
        return (int) ($row['n'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Data & dokumen Purchase (PO, Invoice, Dokumen Pendukung)
    // ------------------------------------------------------------------

    public function pos(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, u.full_name AS created_by_name FROM request_budget_pos p
               LEFT JOIN users u ON u.id = p.created_by
              WHERE p.request_budget_id = :id ORDER BY p.id ASC", ['id' => $id]);
    }

    public function invoices(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT i.*, u.full_name AS created_by_name FROM request_budget_invoices i
               LEFT JOIN users u ON u.id = i.created_by
              WHERE i.request_budget_id = :id ORDER BY i.id ASC", ['id' => $id]);
    }

    public function attachments(int $id): array
    {
        return $this->db->fetchAll(
            "SELECT a.*, u.full_name AS created_by_name FROM request_budget_attachments a
               LEFT JOIN users u ON u.id = a.created_by
              WHERE a.request_budget_id = :id ORDER BY a.id ASC", ['id' => $id]);
    }

    /** type: po | invoice | attachment -> nama tabel (whitelist, tidak pernah dari input mentah). */
    public static function docTable(string $type): ?string
    {
        return ['po' => 'request_budget_pos', 'invoice' => 'request_budget_invoices', 'attachment' => 'request_budget_attachments'][$type] ?? null;
    }

    public function addDoc(string $type, int $rbId, array $data): int
    {
        $table = self::docTable($type);
        if (!$table) {
            throw new InvalidArgumentException('Jenis dokumen tidak valid.');
        }
        return $this->db->insert($table, ['request_budget_id' => $rbId] + $data);
    }

    /** Ambil 1 dokumen HANYA kalau memang milik request ini (anti-IDOR antar request). */
    public function findDoc(string $type, int $docId, int $rbId)
    {
        $table = self::docTable($type);
        if (!$table) {
            return null;
        }
        return $this->db->fetchOne("SELECT * FROM {$table} WHERE id = :id AND request_budget_id = :rb", ['id' => $docId, 'rb' => $rbId]);
    }

    public function deleteDoc(string $type, int $docId, int $rbId): void
    {
        $table = self::docTable($type);
        if ($table) {
            $this->db->query("DELETE FROM {$table} WHERE id = :id AND request_budget_id = :rb", ['id' => $docId, 'rb' => $rbId]);
        }
    }

    /**
     * Kelengkapan data Purchase sebelum boleh "Dilengkapi" / diajukan ke Purwati/Nissa.
     * PO, Invoice, dan dokumen pendukung SEMUANYA OPSIONAL (permintaan user): request boleh
     * diajukan tanpa lampiran apa pun. Method dipertahankan sebagai satu titik aturan kalau
     * nanti ada syarat wajib dari template Excel.
     * @return string[] daftar masalah; kosong = lengkap
     */
    public function purchaseDataProblems(int $id): array
    {
        return [];
    }

    /** Request ini punya file dengan path tsb? -> id request (untuk guard akses file). */
    public function requestIdByFilePath(string $path): ?int
    {
        foreach (['request_budget_pos', 'request_budget_invoices', 'request_budget_attachments'] as $t) {
            $row = $this->db->fetchOne("SELECT request_budget_id FROM {$t} WHERE file_path = :p LIMIT 1", ['p' => $path]);
            if ($row) {
                return (int) $row['request_budget_id'];
            }
        }
        return null;
    }

    // ------------------------------------------------------------------
    // Laporan Request Budget (Accounting & Super Admin)
    // ------------------------------------------------------------------

    /** @return array [where-sql, params] untuk laporan -- TANPA scope project (laporan lintas project). */
    private function reportWhere(array $f): array
    {
        $sql = " WHERE rb.deleted_at IS NULL AND rb.status <> 'DRAFT'";
        $p = [];
        if (!empty($f['date_from'])) { $sql .= " AND rb.request_date >= :r_from"; $p['r_from'] = $f['date_from']; }
        if (!empty($f['date_to']))   { $sql .= " AND rb.request_date <= :r_to";   $p['r_to'] = $f['date_to']; }
        if (!empty($f['project_id'])) { $sql .= " AND rb.project_id = :r_proj"; $p['r_proj'] = (int) $f['project_id']; }
        if (!empty($f['status']) && isset(self::STATUS_LABELS[$f['status']])) { $sql .= " AND rb.status = :r_status"; $p['r_status'] = $f['status']; }
        if (!empty($f['approval'])) {
            if ($f['approval'] === 'all_approved') {
                $sql .= " AND NOT EXISTS (SELECT 1 FROM request_budget_approvals a WHERE a.request_budget_id = rb.id AND a.status <> 'APPROVED')
                          AND EXISTS (SELECT 1 FROM request_budget_approvals a2 WHERE a2.request_budget_id = rb.id)";
            } elseif ($f['approval'] === 'pending') {
                $sql .= " AND EXISTS (SELECT 1 FROM request_budget_approvals a WHERE a.request_budget_id = rb.id AND a.status = 'PENDING')";
            } elseif ($f['approval'] === 'rejected') {
                $sql .= " AND EXISTS (SELECT 1 FROM request_budget_approvals a WHERE a.request_budget_id = rb.id AND a.status = 'REJECTED')";
            }
        }
        if (!empty($f['purchase_user_id'])) { $sql .= " AND (rb.purchase_completed_by = :r_pu OR rb.forwarded_by = :r_pu2)"; $p['r_pu'] = (int) $f['purchase_user_id']; $p['r_pu2'] = (int) $f['purchase_user_id']; }
        if (!empty($f['forward_to']) && in_array($f['forward_to'], self::FORWARD_DESTINATIONS, true)) { $sql .= " AND rb.forwarded_to = :r_to_dest"; $p['r_to_dest'] = $f['forward_to']; }
        if (!empty($f['vendor'])) {
            $sql .= " AND (EXISTS (SELECT 1 FROM request_budget_pos x WHERE x.request_budget_id = rb.id AND x.vendor_name LIKE :r_vendor)
                       OR EXISTS (SELECT 1 FROM request_budget_invoices y WHERE y.request_budget_id = rb.id AND y.vendor_name LIKE :r_vendor2))";
            $p['r_vendor'] = '%' . $f['vendor'] . '%'; $p['r_vendor2'] = '%' . $f['vendor'] . '%';
        }
        if (!empty($f['po_number'])) { $sql .= " AND EXISTS (SELECT 1 FROM request_budget_pos x WHERE x.request_budget_id = rb.id AND x.po_number LIKE :r_po)"; $p['r_po'] = '%' . $f['po_number'] . '%'; }
        if (!empty($f['invoice_number'])) { $sql .= " AND EXISTS (SELECT 1 FROM request_budget_invoices y WHERE y.request_budget_id = rb.id AND y.invoice_number LIKE :r_inv)"; $p['r_inv'] = '%' . $f['invoice_number'] . '%'; }
        if (!empty($f['keyword'])) {
            [$kw, $kp] = SmartSearch::clause($f['keyword'], ['rq.full_name', 'p.project_name', 'rb.purpose'], ['rb.request_number'], 'rrkw');
            if ($kw !== '') { $sql .= " AND {$kw}"; $p += $kp; }
        }
        return [$sql, $p];
    }

    public function reportRows(array $filters, int $limit = 50, int $offset = 0): array
    {
        [$where, $params] = $this->reportWhere($filters);
        return $this->db->fetchAll(
            "SELECT rb.*, p.project_name, rq.full_name AS requester_name,
                    (SELECT GROUP_CONCAT(x.po_number SEPARATOR ', ') FROM request_budget_pos x WHERE x.request_budget_id = rb.id) AS po_numbers,
                    (SELECT GROUP_CONCAT(y.invoice_number SEPARATOR ', ') FROM request_budget_invoices y WHERE y.request_budget_id = rb.id) AS invoice_numbers,
                    (SELECT GROUP_CONCAT(DISTINCT v.vendor_name SEPARATOR ', ') FROM (
                        SELECT request_budget_id, vendor_name FROM request_budget_pos WHERE vendor_name IS NOT NULL AND vendor_name <> ''
                        UNION SELECT request_budget_id, vendor_name FROM request_budget_invoices WHERE vendor_name IS NOT NULL AND vendor_name <> '') v
                      WHERE v.request_budget_id = rb.id) AS vendors,
                    (SELECT a.status FROM request_budget_approvals a WHERE a.request_budget_id = rb.id AND a.slot = 'pm') AS approval_pm,
                    (SELECT a.status FROM request_budget_approvals a WHERE a.request_budget_id = rb.id AND a.slot = 'purchase') AS approval_purchase,
                    fw.full_name AS forwarded_by_name
               FROM request_budgets rb
               JOIN projects p ON p.id = rb.project_id
               JOIN users rq ON rq.id = rb.requester_user_id
               LEFT JOIN users fw ON fw.id = rb.forwarded_by"
            . $where . " ORDER BY rb.request_date DESC, rb.id DESC LIMIT " . max(1, $limit) . " OFFSET " . max(0, $offset),
            $params
        );
    }

    public function reportCount(array $filters): int
    {
        [$where, $params] = $this->reportWhere($filters);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM request_budgets rb
               JOIN projects p ON p.id = rb.project_id JOIN users rq ON rq.id = rb.requester_user_id" . $where, $params);
        return (int) ($row['n'] ?? 0);
    }

    public function reportTotal(array $filters): float
    {
        [$where, $params] = $this->reportWhere($filters);
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(rb.total_amount),0) AS t FROM request_budgets rb
               JOIN projects p ON p.id = rb.project_id JOIN users rq ON rq.id = rb.requester_user_id" . $where, $params);
        return (float) ($row['t'] ?? 0);
    }

    /** User yang pernah melengkapi/meneruskan (dropdown filter "Andy/Purchase"). */
    public function purchaseUserOptions(): array
    {
        return $this->db->fetchAll(
            "SELECT DISTINCT u.id, u.full_name FROM users u
              WHERE u.id IN (SELECT purchase_completed_by FROM request_budgets WHERE purchase_completed_by IS NOT NULL)
                 OR u.id IN (SELECT forwarded_by FROM request_budgets WHERE forwarded_by IS NOT NULL)
              ORDER BY u.full_name ASC"
        );
    }
}
