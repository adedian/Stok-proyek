<?php
require_once ROOT_PATH . '/core/Model.php';

class PurchaseOrderHistory extends Model
{
    protected string $table = 'purchase_order_history';

    /**
     * Peta event riwayat PO -> [modul, aksi] di Riwayat Aktivitas (audit log
     * global). Riwayat per-PO tetap seperti semula; event yang sama SEKARANG
     * juga dicatat ke audit log supaya PO/Penerimaan/Validasi/Pembayaran
     * muncul di Laporan > Riwayat Aktivitas (sebelumnya cuma di timeline PO).
     */
    private const AUDIT_MAP = [
        'created'                => ['purchase_order', 'create'],
        'updated'                => ['purchase_order', 'update'],
        'status_changed'         => ['purchase_order', 'update'],
        'deleted'                => ['purchase_order', 'delete'],
        'goods_received'         => ['goods_receipt',  'create'],
        'goods_receipt_updated'  => ['goods_receipt',  'update'],
        'goods_receipt_deleted'  => ['goods_receipt',  'delete'],
        'validation'             => ['validation',     'validate'],
        'payment_added'          => ['payment',        'create'],
        'payment_updated'        => ['payment',        'update'],
        'payment_deleted'        => ['payment',        'delete'],
    ];

    public function log(int $poId, string $action, string $description, ?int $userId): void
    {
        $this->create([
            'purchase_order_id' => $poId,
            'action'      => $action,
            'description' => $description,
            'created_by'  => $userId,
        ]);

        // Audit log global -- best-effort, tidak boleh menggagalkan aksi utama
        // (ActivityLog::log() sudah menelan exception-nya sendiri).
        if (isset(self::AUDIT_MAP[$action])) {
            require_once ROOT_PATH . '/app/models/ActivityLog.php';
            [$module, $auditAction] = self::AUDIT_MAP[$action];
            try {
                $po = $this->db->fetchOne("SELECT po_number FROM purchase_orders WHERE id = :id", ['id' => $poId]);
                $poNo = $po['po_number'] ?? ('#' . $poId);
            } catch (Throwable $e) {
                $poNo = '#' . $poId;
            }
            (new ActivityLog())->log($userId, $module, $auditAction, "PO {$poNo}: {$description}");
        }
    }

    // ------------------------------------------------------------------
    // Rincian perubahan (sebelum -> sesudah) untuk riwayat "Data Purchase Order diperbarui"
    // ------------------------------------------------------------------

    private static function trimNum($n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    }

    private static function money($n, string $cur): string
    {
        return $cur . ' ' . number_format((float) $n, 2, '.', ',');
    }

    private static function clip(?string $t, int $max = 120): string
    {
        $t = trim(preg_replace('/\s+/', ' ', (string) $t));
        if ($t === '') {
            return '(kosong)';
        }
        return mb_strlen($t) > $max ? mb_substr($t, 0, $max) . '...' : $t;
    }

    /** Deskripsi ringkas satu baris item: "Nama" (2 Unit x USD 1,000.00, diskon 5%, PPN 11%). */
    private static function itemText(array $it, string $cur): string
    {
        $d = self::trimNum($it['qty_order']) . ' ' . ($it['unit'] ?? '') . ' x ' . self::money($it['price'], $cur);
        if ((float) ($it['discount_percent'] ?? 0) > 0) {
            $d .= ', diskon ' . self::trimNum($it['discount_percent']) . '%';
        }
        if (!empty($it['ppn_enabled'])) {
            $d .= ', PPN ' . self::trimNum($it['ppn_percent'] ?? 0) . '%';
        }
        return '"' . $it['item_name'] . '" (' . trim($d) . ')';
    }

    /**
     * Bandingkan baris item lama vs baru. Cocok berdasarkan master Barang (item_id) atau nama; baris yang
     * tidak punya pasangan dipasangkan berurutan sebagai "diganti" (A -> B), sisanya "ditambah"/"dihapus".
     * @return string[] baris perubahan (tanpa bullet)
     */
    public static function diffItems(array $old, array $new, string $oldCur, string $newCur): array
    {
        $key = fn(array $it) => !empty($it['item_id']) ? 'id:' . (int) $it['item_id'] : 'n:' . mb_strtolower(trim((string) $it['item_name']));
        $oldBy = [];
        foreach ($old as $it) {
            $oldBy[$key($it)][] = $it;
        }
        $lines = [];
        $unmatchedNew = [];
        foreach ($new as $it) {
            $k = $key($it);
            if (!empty($oldBy[$k])) {
                $o = array_shift($oldBy[$k]);
                $ch = [];
                if (trim((string) $o['item_name']) !== trim((string) $it['item_name'])) {
                    $ch[] = 'Nama "' . $o['item_name'] . '" -> "' . $it['item_name'] . '"';
                }
                if (abs((float) $o['qty_order'] - (float) $it['qty_order']) > 0.0001) {
                    $ch[] = 'Qty ' . self::trimNum($o['qty_order']) . ' -> ' . self::trimNum($it['qty_order']);
                }
                if (trim((string) $o['unit']) !== trim((string) $it['unit'])) {
                    $ch[] = 'Satuan ' . ($o['unit'] ?: '-') . ' -> ' . ($it['unit'] ?: '-');
                }
                if (abs((float) $o['price'] - (float) $it['price']) > 0.004) {
                    $ch[] = 'Harga ' . self::money($o['price'], $oldCur) . ' -> ' . self::money($it['price'], $newCur);
                }
                if (abs((float) ($o['discount_percent'] ?? 0) - (float) ($it['discount_percent'] ?? 0)) > 0.004) {
                    $ch[] = 'Diskon ' . self::trimNum($o['discount_percent'] ?? 0) . '% -> ' . self::trimNum($it['discount_percent'] ?? 0) . '%';
                }
                $op = !empty($o['ppn_enabled']) ? self::trimNum($o['ppn_percent'] ?? 0) . '%' : 'tanpa PPN';
                $np = !empty($it['ppn_enabled']) ? self::trimNum($it['ppn_percent'] ?? 0) . '%' : 'tanpa PPN';
                if ($op !== $np) {
                    $ch[] = 'PPN ' . $op . ' -> ' . $np;
                }
                if ($ch) {
                    $lines[] = 'Item "' . $it['item_name'] . '": ' . implode('; ', $ch);
                }
            } else {
                $unmatchedNew[] = $it;
            }
        }
        $unmatchedOld = [];
        foreach ($oldBy as $rows) {
            foreach ($rows as $o) {
                $unmatchedOld[] = $o;
            }
        }
        $pairs = min(count($unmatchedOld), count($unmatchedNew));
        for ($i = 0; $i < $pairs; $i++) {
            $lines[] = 'Item diganti: ' . self::itemText($unmatchedOld[$i], $oldCur) . ' -> ' . self::itemText($unmatchedNew[$i], $newCur);
        }
        for ($i = $pairs; $i < count($unmatchedNew); $i++) {
            $lines[] = 'Item ditambah: ' . self::itemText($unmatchedNew[$i], $newCur);
        }
        for ($i = $pairs; $i < count($unmatchedOld); $i++) {
            $lines[] = 'Item dihapus: ' . self::itemText($unmatchedOld[$i], $oldCur);
        }
        return $lines;
    }

    /** Bandingkan biaya tambahan (cost_name + amount). */
    public static function diffExtraCosts(array $old, array $new, string $oldCur, string $newCur): array
    {
        $oldBy = [];
        foreach ($old as $c) {
            $oldBy[mb_strtolower(trim((string) $c['cost_name']))][] = $c;
        }
        $lines = [];
        $newLeft = [];
        foreach ($new as $c) {
            $k = mb_strtolower(trim((string) $c['cost_name']));
            if (!empty($oldBy[$k])) {
                $o = array_shift($oldBy[$k]);
                if (abs((float) $o['amount'] - (float) $c['amount']) > 0.004) {
                    $lines[] = 'Biaya tambahan "' . $c['cost_name'] . '": ' . self::money($o['amount'], $oldCur) . ' -> ' . self::money($c['amount'], $newCur);
                }
            } else {
                $newLeft[] = $c;
            }
        }
        $oldLeft = [];
        foreach ($oldBy as $rows) {
            foreach ($rows as $o) {
                $oldLeft[] = $o;
            }
        }
        $pairs = min(count($oldLeft), count($newLeft));
        for ($i = 0; $i < $pairs; $i++) {
            $lines[] = 'Biaya tambahan diganti: "' . $oldLeft[$i]['cost_name'] . '" (' . self::money($oldLeft[$i]['amount'], $oldCur) . ') -> "' . $newLeft[$i]['cost_name'] . '" (' . self::money($newLeft[$i]['amount'], $newCur) . ')';
        }
        for ($i = $pairs; $i < count($newLeft); $i++) {
            $lines[] = 'Biaya tambahan ditambah: "' . $newLeft[$i]['cost_name'] . '" (' . self::money($newLeft[$i]['amount'], $newCur) . ')';
        }
        for ($i = $pairs; $i < count($oldLeft); $i++) {
            $lines[] = 'Biaya tambahan dihapus: "' . $oldLeft[$i]['cost_name'] . '" (' . self::money($oldLeft[$i]['amount'], $oldCur) . ')';
        }
        return $lines;
    }

    /**
     * Bandingkan data umum PO. $old/$new = [label => nilai tampilan string]; hanya yang berbeda dikembalikan
     * ("Label: lama -> baru"). Catatan panjang dipotong supaya riwayat tetap ringkas.
     */
    public static function diffFields(array $old, array $new): array
    {
        $lines = [];
        foreach ($new as $label => $nv) {
            $ov = $old[$label] ?? '';
            if ((string) $ov === (string) $nv) {
                continue;
            }
            $long = $label === 'Catatan';
            $fmt = fn($v) => $long ? '"' . self::clip($v) . '"' : (trim((string) $v) === '' ? '(kosong)' : (string) $v);
            $lines[] = $label . ': ' . $fmt($ov) . ' -> ' . $fmt($nv);
        }
        return $lines;
    }

    /** Gabungkan baris perubahan jadi 1 deskripsi riwayat (judul + butir "- "). */
    public static function composeChangeDescription(array $lines): string
    {
        if (!$lines) {
            return 'Data Purchase Order disimpan tanpa perubahan data';
        }
        return "Data Purchase Order diperbarui:\n" . implode("\n", array_map(fn($l) => '- ' . $l, $lines));
    }

    public function timelineByPo(int $poId): array
    {
        $sql = "SELECT h.*, u.full_name
                FROM purchase_order_history h
                LEFT JOIN users u ON u.id = h.created_by
                WHERE h.purchase_order_id = :po_id
                ORDER BY h.created_at DESC";
        return $this->db->fetchAll($sql, ['po_id' => $poId]);
    }
}
