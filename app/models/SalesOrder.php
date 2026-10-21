<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/DocumentNumber.php';

/**
 * SalesOrder (Order Penjualan) -- pesanan customer berdasarkan PO Customer.
 *
 * PRINSIP DATA (penting, jangan dilanggar saat memodifikasi):
 *   - Order TIDAK memotong dan TIDAK mereservasi stok. Stok hanya berkurang lewat
 *     Pengeluaran Barang (stock_out) yang dibuat oleh SalesOrderFulfillment::fulfill().
 *   - "Jumlah Terpenuhi" TIDAK disimpan. Selalu dihitung dari stock_out yang menunjuk ke
 *     sales_order_item_id (belum dihapus), jadi tidak bisa menyimpang dari transaksi stok.
 *   - "Jumlah Ditagih" dihitung dari sales_invoice_items (invoice belum dihapus).
 *   - Stok Tersedia = SUM(inventory.qty_available) untuk nama barang master yang sama
 *     (pola sama dengan Stok Barang / Item::buildListQuery) -- dibaca LIVE setiap kali.
 *   - Kolom `status` hanya menyimpan draft | aktif | dibatalkan. Status yang dilihat user
 *     (Menunggu Proses, Siap Diproses, Menunggu Stok, Diproses Sebagian, Menunggu Dokumen,
 *     Selesai) DIHITUNG di SQL (lihat orderSelectSql()), sehingga selalu ikut perubahan stok,
 *     pemenuhan, invoice, dan surat jalan tanpa perlu sinkronisasi manual.
 */
class SalesOrder extends Model
{
    protected string $table = 'sales_orders';

    public const DRAFT = 'draft';
    public const ACTIVE = 'aktif';
    public const CANCELLED = 'dibatalkan';

    /** Toleransi pembulatan qty (kolom DECIMAL(15,2)). */
    public const EPS = 0.004;

    public const STATUS_LABELS = [
        'draft'             => 'Draft',
        'menunggu_proses'   => 'Menunggu Proses',
        'siap_diproses'     => 'Siap Diproses',
        'menunggu_stok'     => 'Menunggu Stok',
        'diproses_sebagian' => 'Diproses Sebagian',
        'menunggu_dokumen'  => 'Menunggu Dokumen',
        'selesai'           => 'Selesai',
        'dibatalkan'        => 'Dibatalkan',
    ];

    public const STATUS_BADGES = [
        'draft'             => 'secondary',
        'menunggu_proses'   => 'warning text-dark',
        'siap_diproses'     => 'info text-dark',
        'menunggu_stok'     => 'danger',
        'diproses_sebagian' => 'primary',
        'menunggu_dokumen'  => 'dark',
        'selesai'           => 'success',
        'dibatalkan'        => 'secondary',
    ];

    public const STOCK_LABELS = [
        'terpenuhi'           => 'Terpenuhi',
        'stok_tersedia'       => 'Stok Tersedia',
        'stok_sebagian'       => 'Stok Sebagian',
        'stok_tidak_tersedia' => 'Stok Tidak Tersedia',
    ];

    public const STOCK_BADGES = [
        'terpenuhi'           => 'success',
        'stok_tersedia'       => 'info text-dark',
        'stok_sebagian'       => 'warning text-dark',
        'stok_tidak_tersedia' => 'danger',
    ];

    /** Status dokumen turunan (Invoice / Surat Jalan) -- dihitung PHP dari angka agregat. */
    public const DOC_LABELS = [
        'belum_ada' => 'Belum Ada',
        'sebagian'  => 'Sebagian',
        'lengkap'   => 'Lengkap',
    ];

    public const DOC_BADGES = [
        'belum_ada' => 'light text-muted border',
        'sebagian'  => 'warning text-dark',
        'lengkap'   => 'success',
    ];

    /** Status PENGIRIMAN barang -- terpisah dari status order, invoice & dokumen. Dihitung dari stock_out (sumber kebenaran). */
    public const SHIP_LABELS = [
        'belum_dikirim'   => 'Belum Dikirim',
        'dikirim_sebagian' => 'Dikirim Sebagian',
        'selesai_dikirim' => 'Selesai Dikirim',
    ];

    public const SHIP_BADGES = [
        'belum_dikirim'   => 'light text-muted border',
        'dikirim_sebagian' => 'warning text-dark',
        'selesai_dikirim' => 'success',
    ];

    public static function statusLabel(?string $s): string
    {
        return self::STATUS_LABELS[$s] ?? (string) $s;
    }

    public static function statusBadge(?string $s): string
    {
        return self::STATUS_BADGES[$s] ?? 'secondary';
    }

    public static function stockLabel(?string $s): string
    {
        return self::STOCK_LABELS[$s] ?? '-';
    }

    public static function stockBadge(?string $s): string
    {
        return self::STOCK_BADGES[$s] ?? 'secondary';
    }

    // ------------------------------------------------------------------
    // Penomoran
    // ------------------------------------------------------------------

    /** Pratinjau nomor berikutnya TANPA menaikkan counter (untuk form Tambah). */
    public function previewNumber(?string $date = null): string
    {
        return (new DocumentNumber())->preview('sales_order', 'prefix_op', $date, 'SR.HME');
    }

    /** Nomor sebenarnya; atomik (SELECT ... FOR UPDATE), dipanggil di dalam transaksi store(). */
    public function nextNumber(?string $date = null): string
    {
        return (new DocumentNumber())->next('sales_order', 'prefix_op', $date, 'SR.HME');
    }

    // ------------------------------------------------------------------
    // Perhitungan nilai (satu sumber kebenaran: dipakai form, simpan, invoice)
    // ------------------------------------------------------------------

    /** Harga setelah diskon & subtotal baris. Dibulatkan 2 desimal supaya cocok dengan baris Invoice Keluar. */
    public static function calcLine(float $qty, float $unitPrice, float $discountPercent): array
    {
        $discountPercent = max(0.0, min(100.0, $discountPercent));
        $net = round($unitPrice * (1 - $discountPercent / 100), 2);
        return [
            'discount_percent' => round($discountPercent, 2),
            'net_price'        => $net,
            'subtotal'         => round($qty * $net, 2),
        ];
    }

    public static function calcTotals(array $lines, float $ppnPercent): array
    {
        $subtotal = 0.0;
        foreach ($lines as $line) {
            $subtotal += $line['subtotal'];
        }
        $subtotal = round($subtotal, 2);
        $ppn = round($subtotal * $ppnPercent / 100, 2);
        return ['subtotal' => $subtotal, 'ppn_amount' => $ppn, 'total_amount' => round($subtotal + $ppn, 2)];
    }

    // ------------------------------------------------------------------
    // Query: daftar & detail dengan agregat pemenuhan/stok/dokumen
    // ------------------------------------------------------------------

    /** Join dasar progres per baris order (dipakai agregat order & detail baris). */
    private const LINE_JOINS = "
        JOIN items it ON it.id = soi.item_id
        LEFT JOIN (
            SELECT sales_order_item_id, SUM(qty) AS q
              FROM stock_out
             WHERE deleted_at IS NULL AND sales_order_item_id IS NOT NULL
             GROUP BY sales_order_item_id
        ) f ON f.sales_order_item_id = soi.id
        LEFT JOIN (
            SELECT sii.sales_order_item_id, SUM(sii.qty) AS q
              FROM sales_invoice_items sii
              JOIN sales_invoices si ON si.id = sii.sales_invoice_id AND si.deleted_at IS NULL
             WHERE sii.sales_order_item_id IS NOT NULL
             GROUP BY sii.sales_order_item_id
        ) iv ON iv.sales_order_item_id = soi.id
        LEFT JOIN (
            SELECT item_name, SUM(qty_available) AS q
              FROM inventory
             WHERE deleted_at IS NULL
             GROUP BY item_name
        ) st ON st.item_name = it.item_name";

    /**
     * SELECT order + agregat + status tampilan. Tidak ada WHERE di sini (ditambahkan pemanggil).
     * Status tampilan (display_status) & status stok (stock_status) dihitung di SQL supaya bisa
     * difilter/di-paginasi tanpa menarik semua baris ke PHP.
     */
    private function orderSelectSql(): string
    {
        $e = self::EPS;
        return "SELECT so.*, c.client_name, c.client_code, u.full_name AS created_by_name,
                       COALESCE(agg.line_count, 0)            AS line_count,
                       COALESCE(agg.ordered_qty, 0)           AS ordered_qty,
                       COALESCE(agg.fulfilled_qty, 0)         AS fulfilled_qty,
                       COALESCE(agg.remaining_qty, 0)         AS remaining_qty,
                       COALESCE(agg.invoiced_qty, 0)          AS invoiced_qty,
                       COALESCE(agg.invoice_gap, 0)           AS invoice_gap,
                       COALESCE(agg.lines_open, 0)            AS lines_open,
                       COALESCE(agg.lines_open_with_stock, 0) AS lines_open_with_stock,
                       COALESCE(agg.lines_open_stock_full, 0) AS lines_open_stock_full,
                       COALESCE(sj.rows_total, 0)             AS sj_rows_total,
                       COALESCE(sj.rows_on_dn, 0)             AS sj_rows_on_dn,
                       CASE
                         WHEN so.status = 'dibatalkan' THEN 'dibatalkan'
                         WHEN so.status = 'draft' THEN 'draft'
                         WHEN COALESCE(agg.line_count, 0) = 0 THEN 'menunggu_proses'
                         WHEN agg.lines_open = 0 THEN
                              CASE WHEN agg.invoice_gap <= {$e}
                                    AND COALESCE(sj.rows_total, 0) = COALESCE(sj.rows_on_dn, 0)
                                   THEN 'selesai' ELSE 'menunggu_dokumen' END
                         WHEN agg.fulfilled_qty > {$e} THEN 'diproses_sebagian'
                         WHEN so.stock_checked_at IS NULL THEN 'menunggu_proses'
                         WHEN agg.lines_open_with_stock > 0 THEN 'siap_diproses'
                         ELSE 'menunggu_stok'
                       END AS display_status,
                       CASE
                         WHEN so.status = 'dibatalkan' THEN NULL
                         WHEN COALESCE(agg.line_count, 0) = 0 OR agg.lines_open = 0 THEN 'terpenuhi'
                         WHEN agg.lines_open_stock_full = agg.lines_open THEN 'stok_tersedia'
                         WHEN agg.lines_open_with_stock > 0 THEN 'stok_sebagian'
                         ELSE 'stok_tidak_tersedia'
                       END AS stock_status
                  FROM sales_orders so
                  JOIN clients c ON c.id = so.client_id
                  LEFT JOIN users u ON u.id = so.created_by
                  LEFT JOIN (
                      SELECT soi.sales_order_id,
                             COUNT(*) AS line_count,
                             SUM(soi.qty) AS ordered_qty,
                             SUM(COALESCE(f.q, 0)) AS fulfilled_qty,
                             SUM(GREATEST(soi.qty - COALESCE(f.q, 0), 0)) AS remaining_qty,
                             SUM(COALESCE(iv.q, 0)) AS invoiced_qty,
                             SUM(GREATEST(COALESCE(f.q, 0) - COALESCE(iv.q, 0), 0)) AS invoice_gap,
                             SUM(soi.qty - COALESCE(f.q, 0) > {$e}) AS lines_open,
                             SUM(soi.qty - COALESCE(f.q, 0) > {$e} AND COALESCE(st.q, 0) > {$e}) AS lines_open_with_stock,
                             SUM(soi.qty - COALESCE(f.q, 0) > {$e} AND COALESCE(st.q, 0) >= soi.qty - COALESCE(f.q, 0)) AS lines_open_stock_full
                        FROM sales_order_items soi " . self::LINE_JOINS . "
                       GROUP BY soi.sales_order_id
                  ) agg ON agg.sales_order_id = so.id
                  LEFT JOIN (
                      SELECT sales_order_id, COUNT(*) AS rows_total, SUM(delivery_note_id IS NOT NULL) AS rows_on_dn
                        FROM stock_out
                       WHERE deleted_at IS NULL AND sales_order_id IS NOT NULL
                       GROUP BY sales_order_id
                  ) sj ON sj.sales_order_id = so.id";
    }

    /** WHERE bagian dalam (kolom tabel asli) + parameter. Status tampilan difilter di luar. */
    private function innerFilters(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['keyword'])) {
            [$ss, $sp] = SmartSearch::clause(
                $filters['keyword'],
                [
                    'c.client_name',
                    "(SELECT GROUP_CONCAT(x.item_name, ' ', x.item_code SEPARATOR ' ') FROM sales_order_items x WHERE x.sales_order_id = so.id)",
                ],
                ['so.order_number', 'so.po_number'],
                'sokw'
            );
            if ($ss !== '') {
                $where[] = $ss;
                $params += $sp;
            }
        }
        if (!empty($filters['client_id'])) {
            $where[] = 'so.client_id = :f_client';
            $params['f_client'] = (int) $filters['client_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'so.order_date >= :f_from';
            $params['f_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'so.order_date <= :f_to';
            $params['f_to'] = $filters['date_to'];
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    private function outerFilters(array $filters): array
    {
        $where = [];
        $params = [];
        if (!empty($filters['status']) && isset(self::STATUS_LABELS[$filters['status']])) {
            $where[] = 't.display_status = :f_status';
            $params['f_status'] = $filters['status'];
        }
        if (!empty($filters['stock_status']) && isset(self::STOCK_LABELS[$filters['stock_status']])) {
            $where[] = 't.stock_status = :f_stock';
            $params['f_stock'] = $filters['stock_status'];
        }
        return [$where ? ' WHERE ' . implode(' AND ', $where) : '', $params];
    }

    public function countFiltered(array $filters): int
    {
        [$iw, $ip] = $this->innerFilters($filters);
        [$ow, $op] = $this->outerFilters($filters);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM (" . $this->orderSelectSql() . $iw . ") t" . $ow,
            $ip + $op
        );
        return (int) ($row['n'] ?? 0);
    }

    public function listPaginated(array $filters, int $limit, int $offset): array
    {
        [$iw, $ip] = $this->innerFilters($filters);
        [$ow, $op] = $this->outerFilters($filters);
        $limit = max(1, $limit);
        $offset = max(0, $offset);
        return $this->db->fetchAll(
            "SELECT t.* FROM (" . $this->orderSelectSql() . $iw . ") t" . $ow
            . " ORDER BY t.order_date DESC, t.id DESC LIMIT {$limit} OFFSET {$offset}",
            $ip + $op
        );
    }

    /** Satu order + semua agregat/status tampilan. */
    public function findWithAggregates(int $id)
    {
        return $this->db->fetchOne(
            "SELECT t.* FROM (" . $this->orderSelectSql() . " WHERE so.id = :oid) t",
            ['oid' => $id]
        );
    }

    /** Baris barang + progres (terpenuhi, sisa, ditagih, dapat ditagih) + stok tersedia LIVE. */
    public function itemsWithProgress(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT soi.*,
                    COALESCE(f.q, 0)  AS fulfilled_qty,
                    GREATEST(soi.qty - COALESCE(f.q, 0), 0) AS remaining_qty,
                    COALESCE(iv.q, 0) AS invoiced_qty,
                    GREATEST(COALESCE(f.q, 0) - COALESCE(iv.q, 0), 0) AS invoiceable_qty,
                    COALESCE(st.q, 0) AS stock_qty
               FROM sales_order_items soi " . self::LINE_JOINS . "
              WHERE soi.sales_order_id = :oid
              ORDER BY soi.id ASC",
            ['oid' => $orderId]
        );
    }

    /** Status Invoice: dihitung relatif terhadap jumlah PESANAN. */
    public static function invoiceStatus(array $order): string
    {
        $invoiced = (float) $order['invoiced_qty'];
        if ($invoiced <= self::EPS) {
            return 'belum_ada';
        }
        return $invoiced + self::EPS >= (float) $order['ordered_qty'] ? 'lengkap' : 'sebagian';
    }

    /** Status Surat Jalan: semua baris pengeluaran sudah masuk SJ DAN seluruh pesanan sudah dikeluarkan = lengkap. */
    public static function deliveryStatus(array $order): string
    {
        $onDn = (int) $order['sj_rows_on_dn'];
        if ($onDn === 0) {
            return 'belum_ada';
        }
        $allRows = $onDn === (int) $order['sj_rows_total'];
        return ($allRows && (float) $order['remaining_qty'] <= self::EPS) ? 'lengkap' : 'sebagian';
    }

    /**
     * Status Pengiriman dari agregat order (fulfilled_qty = total stock_out yang MASIH ada, remaining_qty = sisa pesanan).
     * Tidak disimpan: koreksi pemenuhan (stock_out masuk Tempat Sampah) otomatis menghitung ulang status ini.
     */
    public static function shipStatus(array $order): string
    {
        if ((float) ($order['fulfilled_qty'] ?? 0) <= self::EPS) {
            return 'belum_dikirim';
        }
        return (float) ($order['remaining_qty'] ?? 0) <= self::EPS ? 'selesai_dikirim' : 'dikirim_sebagian';
    }

    /**
     * Order yang bisa dipilih di form Surat Jalan: Order aktif (bukan draft/batal) yang masih punya sisa pesanan.
     * $includeDone = juga yang sudah selesai (khusus lihat/audit -- pemanggil WAJIB menolak pengiriman barunya).
     * Pencarian: No. Order / PO Customer / nama customer. Selalu dibatasi $limit baris.
     * Urutan: yang sudah dikirim sebagian dulu, lalu yang paling lama menunggu.
     */
    public function searchForShipment(string $keyword, int $limit = 20, bool $includeDone = false): array
    {
        $e = self::EPS;
        $params = [];
        $where = "so.status = 'aktif'";
        $keyword = trim($keyword);
        if ($keyword !== '') {
            [$ss, $sp] = SmartSearch::clause($keyword, ['c.client_name'], ['so.order_number', 'so.po_number'], 'shipkw');
            if ($ss !== '') {
                $where .= " AND {$ss}";
                $params += $sp;
            }
        }
        $having = $includeDone ? '' : " AND COALESCE(agg.remaining, 0) > {$e}";
        $limit = max(1, min(50, $limit));

        return $this->db->fetchAll(
            "SELECT so.id, so.order_number, so.order_date, so.po_number, so.po_date, c.client_name,
                    COALESCE(agg.ordered, 0) AS ordered_qty, COALESCE(agg.shipped, 0) AS shipped_qty,
                    COALESCE(agg.remaining, 0) AS remaining_qty
               FROM sales_orders so
               JOIN clients c ON c.id = so.client_id
               LEFT JOIN (
                    SELECT soi.sales_order_id, SUM(soi.qty) AS ordered, SUM(COALESCE(f.q, 0)) AS shipped,
                           SUM(GREATEST(soi.qty - COALESCE(f.q, 0), 0)) AS remaining
                      FROM sales_order_items soi
                      LEFT JOIN (SELECT sales_order_item_id, SUM(qty) AS q FROM stock_out
                                  WHERE deleted_at IS NULL AND sales_order_item_id IS NOT NULL
                                  GROUP BY sales_order_item_id) f ON f.sales_order_item_id = soi.id
                     GROUP BY soi.sales_order_id
               ) agg ON agg.sales_order_id = so.id
              WHERE {$where}{$having}
              ORDER BY (COALESCE(agg.shipped, 0) > 0) DESC, so.order_date ASC, so.id ASC
              LIMIT {$limit}",
            $params
        );
    }

    /** Jumlah baris pengeluaran order ini yang belum masuk Surat Jalan manapun (pemenuhan lewat halaman Proses). */
    public function ungroupedStockOutRows(int $orderId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM stock_out WHERE sales_order_id = :o AND deleted_at IS NULL AND delivery_note_id IS NULL",
            ['o' => $orderId]
        );
        return (int) ($row['n'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Data pendukung
    // ------------------------------------------------------------------

    /**
     * Master Barang aktif + stok tersedia LIVE (untuk dropdown form). $includeItemIds = barang yang
     * dipakai order yang sedang diedit walau sudah nonaktif/dihapus dari master.
     */
    public function itemCatalog(array $includeItemIds = []): array
    {
        $rows = $this->db->fetchAll(
            "SELECT i.id, i.item_code, i.item_name, i.specification, u.unit_name,
                    COALESCE(st.q, 0) AS stock_qty
               FROM items i
               JOIN units u ON u.id = i.unit_id
               LEFT JOIN (SELECT item_name, SUM(qty_available) AS q FROM inventory WHERE deleted_at IS NULL GROUP BY item_name) st
                      ON st.item_name = i.item_name
              WHERE i.deleted_at IS NULL AND i.status = 'active'
              ORDER BY i.item_name ASC"
        );
        $have = array_map('intval', array_column($rows, 'id'));
        foreach ($includeItemIds as $id) {
            $id = (int) $id;
            if ($id > 0 && !in_array($id, $have, true)) {
                $extra = $this->db->fetchOne(
                    "SELECT i.id, i.item_code, i.item_name, i.specification, u.unit_name,
                            COALESCE((SELECT SUM(qty_available) FROM inventory WHERE item_name = i.item_name AND deleted_at IS NULL), 0) AS stock_qty
                       FROM items i JOIN units u ON u.id = i.unit_id WHERE i.id = :id",
                    ['id' => $id]
                );
                if ($extra) {
                    $rows[] = $extra;
                    $have[] = $id;
                }
            }
        }
        return $rows;
    }

    /** Info satu barang master + stok LIVE (endpoint AJAX form). */
    public function itemStockInfo(int $itemId)
    {
        return $this->db->fetchOne(
            "SELECT i.id, i.item_code, i.item_name, i.specification, u.unit_name,
                    COALESCE((SELECT SUM(qty_available) FROM inventory WHERE item_name = i.item_name AND deleted_at IS NULL), 0) AS stock_qty
               FROM items i JOIN units u ON u.id = i.unit_id
              WHERE i.id = :id AND i.deleted_at IS NULL",
            ['id' => $itemId]
        );
    }

    /** Baris-baris inventory (sumber stok) yang masih berisi untuk satu nama barang master. */
    public function stockBuckets(string $itemName): array
    {
        return $this->db->fetchAll(
            "SELECT inv.id, inv.unit, inv.qty_available, inv.stock_scope, inv.project_id, p.project_name
               FROM inventory inv
               LEFT JOIN projects p ON p.id = inv.project_id
              WHERE inv.item_name = :n AND inv.deleted_at IS NULL AND inv.qty_available > 0
              ORDER BY (inv.stock_scope = 'kantor') DESC, inv.qty_available DESC, inv.id ASC",
            ['n' => $itemName]
        );
    }

    /** Nomor PO customer yang sama untuk customer yang sama (order tidak dibatalkan) -- cegah input ganda. */
    public function poTaken(int $clientId, string $poNumber, ?int $excludeId = null): ?array
    {
        $sql = "SELECT id, order_number FROM sales_orders
                 WHERE client_id = :c AND po_number = :p AND status <> 'dibatalkan'";
        $params = ['c' => $clientId, 'p' => $poNumber];
        if ($excludeId) {
            $sql .= " AND id <> :x";
            $params['x'] = $excludeId;
        }
        $sql .= " LIMIT 1";
        return $this->db->fetchOne($sql, $params) ?: null;
    }

    /** Kunci baris order (SELECT ... FOR UPDATE) -- serialisasi semua proses atas order yang sama. */
    public function lockForUpdate(int $id)
    {
        return $this->db->fetchOne("SELECT * FROM sales_orders WHERE id = :id FOR UPDATE", ['id' => $id]);
    }

    /** Total terpenuhi satu baris order SAAT INI (baca terbaru; dipanggil setelah order dikunci). */
    public function fulfilledQty(int $orderItemId): float
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(qty), 0) AS q FROM stock_out WHERE sales_order_item_id = :i AND deleted_at IS NULL",
            ['i' => $orderItemId]
        );
        return (float) ($row['q'] ?? 0);
    }

    /** Total ditagih satu baris order SAAT INI (invoice belum dihapus). */
    public function invoicedQty(int $orderItemId, ?int $excludeInvoiceId = null): float
    {
        $sql = "SELECT COALESCE(SUM(sii.qty), 0) AS q
                  FROM sales_invoice_items sii
                  JOIN sales_invoices si ON si.id = sii.sales_invoice_id AND si.deleted_at IS NULL
                 WHERE sii.sales_order_item_id = :i";
        $params = ['i' => $orderItemId];
        if ($excludeInvoiceId) {
            $sql .= " AND si.id <> :x";
            $params['x'] = $excludeInvoiceId;
        }
        $row = $this->db->fetchOne($sql, $params);
        return (float) ($row['q'] ?? 0);
    }

    // ------------------------------------------------------------------
    // Baris order (simpan / ganti)
    // ------------------------------------------------------------------

    public function itemsRaw(int $orderId): array
    {
        return $this->db->fetchAll("SELECT * FROM sales_order_items WHERE sales_order_id = :id ORDER BY id ASC", ['id' => $orderId]);
    }

    public function insertItem(int $orderId, array $line): int
    {
        return $this->db->insert('sales_order_items', [
            'sales_order_id'   => $orderId,
            'item_id'          => $line['item_id'],
            'item_code'        => $line['item_code'],
            'item_name'        => $line['item_name'],
            'specification'    => $line['specification'] ?: null,
            'unit'             => $line['unit'],
            'qty'              => $line['qty'],
            'unit_price'       => $line['unit_price'],
            'discount_percent' => $line['discount_percent'],
            'net_price'        => $line['net_price'],
            'subtotal'         => $line['subtotal'],
        ]);
    }

    public function updateItem(int $itemRowId, array $line): void
    {
        $this->db->update('sales_order_items', [
            'item_id'          => $line['item_id'],
            'item_code'        => $line['item_code'],
            'item_name'        => $line['item_name'],
            'specification'    => $line['specification'] ?: null,
            'unit'             => $line['unit'],
            'qty'              => $line['qty'],
            'unit_price'       => $line['unit_price'],
            'discount_percent' => $line['discount_percent'],
            'net_price'        => $line['net_price'],
            'subtotal'         => $line['subtotal'],
        ], 'id = :pk', ['pk' => $itemRowId]);
    }

    public function deleteItem(int $itemRowId): void
    {
        $this->db->query("DELETE FROM sales_order_items WHERE id = :id", ['id' => $itemRowId]);
    }

    // ------------------------------------------------------------------
    // Pemenuhan, dokumen terkait, riwayat
    // ------------------------------------------------------------------

    /** Pemenuhan (dokumen pengeluaran) milik order + baris barang & status Surat Jalannya. */
    public function fulfillmentsWithRows(int $orderId): array
    {
        $fulfs = $this->db->fetchAll(
            "SELECT f.*, cu.full_name AS created_by_name, xu.full_name AS cancelled_by_name
               FROM sales_order_fulfillments f
               LEFT JOIN users cu ON cu.id = f.created_by
               LEFT JOIN users xu ON xu.id = f.cancelled_by
              WHERE f.sales_order_id = :oid
              ORDER BY f.id DESC",
            ['oid' => $orderId]
        );
        foreach ($fulfs as &$f) {
            $f['rows'] = $this->db->fetchAll(
                "SELECT so.id, so.qty, so.delivery_note_id, soi.item_name, soi.unit, soi.item_code,
                        dn.delivery_number, inv.stock_scope, p.project_name AS source_project
                   FROM stock_out so
                   JOIN sales_order_items soi ON soi.id = so.sales_order_item_id
                   JOIN inventory inv ON inv.id = so.inventory_id
                   LEFT JOIN projects p ON p.id = inv.project_id
                   LEFT JOIN delivery_notes dn ON dn.id = so.delivery_note_id AND dn.deleted_at IS NULL
                  WHERE so.sales_order_fulfillment_id = :fid AND so.deleted_at IS NULL
                  ORDER BY so.id ASC",
                ['fid' => $f['id']]
            );
        }
        unset($f);
        return $fulfs;
    }

    public function invoicesByOrder(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT si.id, si.invoice_number, si.invoice_date, si.document_kind, si.total_amount,
                    EXISTS (SELECT 1 FROM collection_receipt_items cri
                              JOIN collection_receipts cr ON cr.id = cri.collection_receipt_id AND cr.deleted_at IS NULL
                             WHERE cri.sales_invoice_id = si.id) AS is_billed
               FROM sales_invoices si
              WHERE si.sales_order_id = :oid AND si.deleted_at IS NULL
              ORDER BY si.invoice_date DESC, si.id DESC",
            ['oid' => $orderId]
        );
    }

    public function deliveryNotesByOrder(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT dn.id, dn.delivery_number, dn.delivery_date,
                    (SELECT COUNT(*) FROM stock_out so WHERE so.delivery_note_id = dn.id AND so.deleted_at IS NULL) AS item_count
               FROM delivery_notes dn
              WHERE dn.sales_order_id = :oid AND dn.deleted_at IS NULL
              ORDER BY dn.delivery_date DESC, dn.id DESC",
            ['oid' => $orderId]
        );
    }

    /** Jumlah pemenuhan yang masih aktif (belum dikoreksi) -- penentu boleh/tidaknya edit penuh & pembatalan. */
    public function activeFulfillmentCount(int $orderId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM sales_order_fulfillments WHERE sales_order_id = :o AND status = 'aktif'",
            ['o' => $orderId]
        );
        return (int) ($row['n'] ?? 0);
    }

    public function invoiceCount(int $orderId): int
    {
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM sales_invoices WHERE sales_order_id = :o AND deleted_at IS NULL",
            ['o' => $orderId]
        );
        return (int) ($row['n'] ?? 0);
    }

    public function addHistory(int $orderId, ?int $userId, string $action, ?string $notes = null): void
    {
        $this->db->insert('sales_order_history', [
            'sales_order_id' => $orderId,
            'user_id'        => $userId,
            'role_name'      => $userId ? (function_exists('roleLabelMap') ? (roleLabelMap()[currentUserRole()] ?? null) : null) : null,
            'action'         => $action,
            'notes'          => $notes,
        ]);
    }

    public function history(int $orderId): array
    {
        return $this->db->fetchAll(
            "SELECT h.*, u.full_name
               FROM sales_order_history h
               LEFT JOIN users u ON u.id = h.user_id
              WHERE h.sales_order_id = :oid
              ORDER BY h.id DESC",
            ['oid' => $orderId]
        );
    }

    public const HISTORY_LABELS = [
        'create'        => 'Order dibuat',
        'release'       => 'Dikirim ke Accounting',
        'update'        => 'Order diubah',
        'cancel'        => 'Order dibatalkan',
        'check_stock'   => 'Stok diperiksa',
        'fulfill'       => 'Pemenuhan diproses',
        'reverse'       => 'Pemenuhan dikoreksi',
        'invoice'       => 'Invoice diterbitkan',
        'void_invoice'  => 'Invoice dibatalkan',
        'delivery_note' => 'Surat Jalan diterbitkan',
        'complete'      => 'Order selesai',
    ];
}
