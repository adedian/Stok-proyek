<?php
require_once ROOT_PATH . '/core/Model.php';
require_once ROOT_PATH . '/app/models/StockTransaction.php';

/**
 * Model Inventory
 * Di Phase 6 ini baru dipakai untuk MENGKREDIT stok otomatis saat barang diterima
 * (supaya Phase 8 - Pengeluaran Barang - punya stok riil untuk dikurangi).
 * CRUD lengkap + halaman kartu stok akan dibangun di Phase 9.
 */
class Inventory extends Model
{
    protected string $table = 'inventory';
    protected bool $softDelete = true;

    /**
     * Cari baris inventory berdasarkan kombinasi nama barang + satuan + project + scope.
     * Item dianggap "sama" kalau nama & satuan identik dalam project & scope yang sama.
     * $projectId NULL dipakai untuk bucket stok Kantor (tidak terikat project manapun).
     */
    public function findByItemProject(string $itemName, string $unit, ?int $projectId, string $stockScope = 'proyek')
    {
        $sql = "SELECT * FROM inventory
                 WHERE item_name = :item_name AND unit = :unit AND stock_scope = :stock_scope
                   AND deleted_at IS NULL";
        $params = ['item_name' => $itemName, 'unit' => $unit, 'stock_scope' => $stockScope];

        if ($projectId === null) {
            $sql .= " AND project_id IS NULL";
        } else {
            $sql .= " AND project_id = :project_id";
            $params['project_id'] = $projectId;
        }
        $sql .= " LIMIT 1";

        return $this->db->fetchOne($sql, $params);
    }

    /**
     * Tambah stok masuk (dari penerimaan barang) + catat ke kartu stok (stock_transactions).
     * Kalau item belum ada di inventory project/scope tsb, otomatis dibuatkan barisnya.
     */
    public function creditStock(
        string $itemName,
        string $unit,
        ?int $projectId,
        float $qty,
        string $referenceType,
        int $referenceId,
        string $transactionDate,
        ?int $userId,
        string $stockScope = 'proyek'
    ): void {
        if ($qty <= 0) {
            return;
        }

        $existing = $this->findByItemProject($itemName, $unit, $projectId, $stockScope);

        if ($existing) {
            $qtyBefore = (float) $existing['qty_available'];
            $qtyAfter = $qtyBefore + $qty;
            $this->updateById((int) $existing['id'], ['qty_available' => $qtyAfter]);
            $inventoryId = (int) $existing['id'];
        } else {
            $qtyBefore = 0;
            $qtyAfter = $qty;
            $inventoryId = $this->create([
                'item_name'     => $itemName,
                'unit'          => $unit,
                'project_id'    => $projectId,
                'stock_scope'   => $stockScope,
                'qty_available' => $qtyAfter,
                'min_stock'     => 0,
                'created_by'    => $userId,
            ]);
        }

        $stockTransaction = new StockTransaction();
        $stockTransaction->log(
            $inventoryId,
            'in',
            $referenceType,
            $referenceId,
            $qty,
            $qtyBefore,
            $qtyAfter,
            $transactionDate,
            $userId
        );
    }

    /**
     * Batalkan kredit stok (dipakai saat penerimaan barang diedit/dihapus, atau saat
     * validasi mengoreksi status jadi "Barang Lain" -- lihat ValidationController).
     */
    public function reverseCredit(
        string $itemName,
        string $unit,
        ?int $projectId,
        float $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId,
        string $stockScope = 'proyek'
    ): void {
        if ($qty <= 0) {
            return;
        }

        $existing = $this->findByItemProject($itemName, $unit, $projectId, $stockScope);
        if (!$existing) {
            return;
        }

        $qtyBefore = (float) $existing['qty_available'];
        $qtyAfter = max(0, $qtyBefore - $qty);
        $this->updateById((int) $existing['id'], ['qty_available' => $qtyAfter]);

        $stockTransaction = new StockTransaction();
        $stockTransaction->log(
            (int) $existing['id'],
            'adjustment',
            $referenceType,
            $referenceId,
            -$qty,
            $qtyBefore,
            $qtyAfter,
            date('Y-m-d H:i:s'),
            $userId,
            'Koreksi otomatis karena penerimaan barang terkait diedit/dihapus'
        );
    }

    /**
     * Kurangi stok (dipakai untuk pengeluaran barang / stock out).
     * Melempar RuntimeException kalau stok tidak mencukupi -- WAJIB dicek
     * sebelum barang dianggap boleh keluar (aturan Phase 8).
     */
    public function debitStock(
        int $inventoryId,
        float $qty,
        string $referenceType,
        int $referenceId,
        string $transactionDate,
        ?int $userId,
        string $notes = ''
    ): void {
        if ($qty <= 0) {
            throw new RuntimeException('Qty yang dikeluarkan harus lebih dari 0.');
        }

        $existing = $this->find($inventoryId);
        if (!$existing) {
            throw new RuntimeException('Data stok barang tidak ditemukan.');
        }

        $qtyBefore = (float) $existing['qty_available'];

        if ($qty > $qtyBefore) {
            throw new RuntimeException(
                "Stok tidak mencukupi untuk '{$existing['item_name']}'. "
                . "Tersedia " . number_format($qtyBefore, 2, ',', '.') . " {$existing['unit']}, "
                . "diminta " . number_format($qty, 2, ',', '.') . " {$existing['unit']}."
            );
        }

        $qtyAfter = $qtyBefore - $qty;
        $this->updateById($inventoryId, ['qty_available' => $qtyAfter]);

        $stockTransaction = new StockTransaction();
        $stockTransaction->log(
            $inventoryId,
            'out',
            $referenceType,
            $referenceId,
            $qty,
            $qtyBefore,
            $qtyAfter,
            $transactionDate,
            $userId,
            $notes
        );
    }

    /**
     * Kembalikan stok yang sebelumnya sudah dikeluarkan (dipakai saat stock_out
     * diedit/dihapus, supaya angka stok tidak dobel-kurang).
     */
    public function restoreStock(
        int $inventoryId,
        float $qty,
        string $referenceType,
        int $referenceId,
        ?int $userId,
        string $notes = 'Koreksi otomatis karena pengeluaran barang terkait diedit/dihapus'
    ): void {
        if ($qty <= 0) {
            return;
        }

        $existing = $this->find($inventoryId);
        if (!$existing) {
            return;
        }

        $qtyBefore = (float) $existing['qty_available'];
        $qtyAfter = $qtyBefore + $qty;
        $this->updateById($inventoryId, ['qty_available' => $qtyAfter]);

        $stockTransaction = new StockTransaction();
        $stockTransaction->log(
            $inventoryId,
            'adjustment',
            $referenceType,
            $referenceId,
            $qty,
            $qtyBefore,
            $qtyAfter,
            date('Y-m-d H:i:s'),
            $userId,
            $notes
        );
    }

    /**
     * Daftar item yang punya stok (qty_available > 0) di sebuah project,
     * dipakai untuk dropdown "Pilih Barang" di form pengeluaran barang.
     */
    public function listByProject(int $projectId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM inventory
             WHERE project_id = :project_id AND deleted_at IS NULL AND qty_available > 0
             ORDER BY item_name ASC",
            ['project_id' => $projectId]
        );
    }

    /**
     * Daftar item stok Kantor (project_id NULL) yang ada stoknya -- dipakai untuk
     * dropdown "Pilih Barang" di form pengeluaran barang saat scope-nya Kantor.
     */
    public function listByOfficeScope(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM inventory
             WHERE stock_scope = 'kantor' AND deleted_at IS NULL AND qty_available > 0
             ORDER BY item_name ASC"
        );
    }

    /**
     * Semua item yang ada stoknya (qty_available > 0) LINTAS scope/project --
     * dipakai dropdown "Barang" saat Pengeluaran Barang tujuannya Client
     * (Invoice). ROOT FIX: sebelumnya cuma pakai listByOfficeScope() (Stok
     * Kantor saja), tapi barang yang dibeli lewat PO biasanya masuk ke bucket
     * stok PROJECT (bukan Kantor) saat diterima -- jadi barang yang jelas ada
     * di PO/Penerimaan Barang tidak pernah muncul di sini. Barang untuk Client
     * bisa saja diambil dari stok project mana pun, bukan cuma Kantor.
     */
    public function listAllWithStock(): array
    {
        return $this->db->fetchAll(
            "SELECT inv.*, p.project_name
             FROM inventory inv
             LEFT JOIN projects p ON p.id = inv.project_id
             WHERE inv.deleted_at IS NULL AND inv.qty_available > 0
             ORDER BY inv.item_name ASC"
        );
    }

    /**
     * Semua item inventory di sebuah project (termasuk yang qty_available = 0),
     * dipakai untuk prefill form stock opname -- opname harus tetap menghitung
     * item yang di sistem sudah nol, siapa tahu fisiknya ternyata ada.
     *
     * @deprecated dipertahankan untuk kompatibilitas, tapi tidak difilter per stock_scope
     * (lihat forOpname()) -- gunakan forOpname('proyek', $projectId) untuk kebutuhan baru.
     */
    public function allByProject(int $projectId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM inventory
             WHERE project_id = :project_id AND deleted_at IS NULL
             ORDER BY item_name ASC",
            ['project_id' => $projectId]
        );
    }

    /**
     * Semua item inventory untuk SATU bucket Stock Opname: 'proyek'+project tertentu,
     * atau 'kantor' (project_id NULL, atau project tertentu kalau stok kantor itu memang
     * dicatat terikat ke suatu project). Termasuk qty_available = 0 -- opname harus tetap
     * menghitung item yang di sistem sudah nol, siapa tahu fisiknya ternyata ada.
     *
     * ROOT FIX: allByProject() lama cuma filter project_id tanpa peduli stock_scope,
     * jadi item stock_scope='kantor' (project_id NULL, dari Penerimaan Barang
     * "Pemakai/Internal" -> Kategori Stok "Stok Kantor") TIDAK PERNAH bisa cocok
     * dengan project manapun -- makanya barang itu ada & benar di "Stok Barang" tapi
     * tidak pernah muncul sebagai dasar Stock Opname. Method ini menangani eksplisit
     * kedua bucket sesuai stock_scope yang sama dipakai di Inventory & Goods Receipt.
     */
    public function forOpname(string $stockScope, ?int $projectId): array
    {
        $sql = "SELECT * FROM inventory WHERE deleted_at IS NULL AND stock_scope = :stock_scope";
        $params = ['stock_scope' => $stockScope];

        if ($projectId !== null) {
            $sql .= " AND project_id = :project_id";
            $params['project_id'] = $projectId;
        } else {
            $sql .= " AND project_id IS NULL";
        }

        $sql .= " ORDER BY item_name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Hapus baris inventory (soft delete) -- khusus Super Admin, dipakai untuk
     * membersihkan baris stok yang sudah tidak relevan (mis. barang lama yang
     * sudah dihapus dari Master Data / Barang, atau data keliru).
     * Kalau qty_available belum 0, catat dulu transaksi 'adjustment' penutup
     * (qty -> 0) supaya kartu stok tetap punya jejak audit -- sesuai aturan
     * "jangan pernah ubah stok tanpa mencatat transaksi adjustment".
     */
    public function deleteWithAdjustment(
        int $inventoryId,
        ?int $userId,
        string $notes = 'Baris stok dihapus manual oleh Super Admin'
    ): void {
        $existing = $this->find($inventoryId);
        if (!$existing) {
            return;
        }

        $qtyBefore = (float) $existing['qty_available'];
        if ($qtyBefore != 0) {
            $stockTransaction = new StockTransaction();
            $stockTransaction->log(
                $inventoryId,
                'adjustment',
                'inventory_delete',
                $inventoryId,
                -$qtyBefore,
                $qtyBefore,
                0,
                date('Y-m-d H:i:s'),
                $userId,
                $notes
            );
        }

        $this->deleteById($inventoryId);
    }

    /**
     * Kartu stok realtime -- daftar semua item + nama project, dengan filter opsional.
     * stock_filter: 'low' (qty_available <= min_stock, tapi masih > 0) atau 'empty' (qty_available <= 0)
     */
    public function listWithFilters(array $filters = []): array
    {
        // LEFT JOIN items lewat nama (inventory tidak punya FK ke items, cocokkan
        // by name) -- untuk menampilkan Kode Barang kalau ada di master, dan supaya
        // Stok Minimum SELALU dibaca dari master Barang (i.min_stock), bukan dari
        // inv.min_stock (kolom lama yang di-hardcode 0 tiap kali baris inventory
        // baru dibuat via creditStock() dan tidak pernah ada UI untuk mengubahnya --
        // itu sebabnya Stok Barang/Laporan sebelumnya selalu menampilkan 0 walau
        // Master Data > Barang sudah punya Stok Min). COALESCE ke inv.min_stock
        // hanya untuk barang "di luar PO"/hasil validasi tanpa entri master (item_code
        // NULL) supaya tidak ujug-ujug jadi 0 tanpa alasan. ROOT FIX: sinkronkan
        // Stok Minimum di semua halaman (Stok Barang, Kartu Stok, Laporan Stok
        // Barang) ke satu sumber kebenaran yang sama dengan Master Data & Dashboard.
        $sql = "SELECT inv.*, p.project_name, i.item_code,
                       COALESCE(i.min_stock, inv.min_stock, 0) AS min_stock
                FROM inventory inv
                LEFT JOIN projects p ON p.id = inv.project_id
                LEFT JOIN items i ON i.item_name = inv.item_name AND i.deleted_at IS NULL
                WHERE inv.deleted_at IS NULL";
        $params = [];

        if (!empty($filters['project_id'])) {
            $sql .= " AND inv.project_id = :project_id";
            $params['project_id'] = $filters['project_id'];
        }
        if (!empty($filters['stock_scope'])) {
            $sql .= " AND inv.stock_scope = :stock_scope";
            $params['stock_scope'] = $filters['stock_scope'];
        }
        if (!empty($filters['keyword'])) {
            $sql .= " AND inv.item_name LIKE :kw";
            $params['kw'] = '%' . $filters['keyword'] . '%';
        }
        // 'low'/'empty' dipakai Kartu Stok realtime (InventoryController) -- JANGAN diubah.
        // 'zero'/'nonzero' khusus Laporan Stok Barang (requirement filter "Stok = 0"/"Stok != 0").
        if (($filters['stock_filter'] ?? '') === 'low') {
            // Pakai ekspresi yang sama dengan kolom min_stock di SELECT (COALESCE ke
            // master Barang) -- alias SELECT tidak bisa dipakai di WHERE di MySQL.
            $sql .= " AND inv.qty_available <= COALESCE(i.min_stock, inv.min_stock, 0) AND inv.qty_available > 0";
        } elseif (($filters['stock_filter'] ?? '') === 'empty') {
            $sql .= " AND inv.qty_available <= 0"; // perilaku asli Kartu Stok realtime, JANGAN diubah
        } elseif (($filters['stock_filter'] ?? '') === 'zero') {
            $sql .= " AND inv.qty_available = 0";
        } elseif (($filters['stock_filter'] ?? '') === 'nonzero') {
            $sql .= " AND inv.qty_available != 0";
        }

        // Filter status barang master (aktif/discontinue) -- items.status sudah ada,
        // tidak perlu kolom baru. Barang tanpa entri master (item_code NULL, mis. hasil
        // validasi "Barang Lain" tanpa katalog) dianggap "aktif" secara default -- tidak
        // bisa dianggap discontinue karena memang tidak ada status master untuk barang itu.
        if (($filters['item_status'] ?? '') === 'active') {
            $sql .= " AND (i.status = 'active' OR i.status IS NULL)";
        } elseif (($filters['item_status'] ?? '') === 'discontinue') {
            $sql .= " AND i.status = 'inactive'";
        }

        // Baris yang dicentang user di layar Laporan Stok Barang (Cetak Detail/Rekap,
        // pilih per barang) -- backend TETAP memvalidasi: ID di-cast ke int (buang yang
        // bukan angka/negatif) dan dibatasi WHERE inv.id IN (...), bukan sekadar
        // dipercaya dari frontend. ID yang tidak valid/sudah dihapus otomatis tidak match.
        // Placeholder bernama (:id0, :id1, ...) -- BUKAN "?" -- karena query ini sudah
        // pakai named placeholder di atas (:project_id dkk) dan PDO tidak mengizinkan
        // named & positional placeholder dicampur dalam satu prepared statement.
        if (!empty($filters['ids']) && is_array($filters['ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $filters['ids']), fn($id) => $id > 0)));
            if (!empty($ids)) {
                $idPlaceholders = [];
                foreach ($ids as $i => $id) {
                    $key = "sel_id{$i}";
                    $idPlaceholders[] = ":{$key}";
                    $params[$key] = $id;
                }
                $sql .= " AND inv.id IN (" . implode(',', $idPlaceholders) . ")";
            }
        }

        // ROOT FIX (Laporan Stok Barang: barang tanpa mutasi pada periode tidak boleh
        // tampil): dulu listWithFilters() TIDAK PERNAH menyaring berdasarkan tanggal
        // sama sekali -- date_from/date_to hanya dipakai belakangan untuk MENGHITUNG
        // ulang saldo per baris (stockMutationReport/stockDetailReport), bukan untuk
        // menentukan baris mana yang berhak muncul -- jadi barang tanpa mutasi tetap
        // ikut tampil dengan angka nol. Di sini ditambahkan EXISTS ke stock_transactions
        // (via grup item_name+unit+project_id+stock_scope yang sama, konsisten dengan
        // logic "matchingInventoryIds" di seluruh model ini, supaya histori tetap utuh
        // walau baris inventory pernah dihapus & dibuat ulang) khusus kalau date_from/
        // date_to memang dikirim. Kartu Stok realtime (InventoryController::index())
        // TIDAK PERNAH mengirim date_from/date_to, jadi filter ini otomatis tidak aktif
        // di halaman itu -- tidak ada regresi ke halaman tsb.
        $dateFrom = trim($filters['date_from'] ?? '');
        $dateTo = trim($filters['date_to'] ?? '');
        if ($dateFrom !== '' || $dateTo !== '') {
            $existsSql = " AND EXISTS (
                SELECT 1 FROM stock_transactions st
                JOIN inventory inv2 ON inv2.id = st.inventory_id
                WHERE inv2.item_name = inv.item_name AND inv2.unit = inv.unit AND inv2.stock_scope = inv.stock_scope
                  AND (inv2.project_id = inv.project_id OR (inv2.project_id IS NULL AND inv.project_id IS NULL))";
            if ($dateFrom !== '') {
                $existsSql .= " AND st.transaction_date >= :mut_date_from";
                $params['mut_date_from'] = $dateFrom;
            }
            if ($dateTo !== '') {
                // Boundary inclusive: lihat ROOT FIX yang sama di stockMutationReport().
                $existsSql .= " AND st.transaction_date < DATE_ADD(:mut_date_to, INTERVAL 1 DAY)";
                $params['mut_date_to'] = $dateTo;
            }
            $existsSql .= ")";
            $sql .= $existsSql;
        }

        $sql .= " ORDER BY p.project_name ASC, inv.item_name ASC";

        return $this->db->fetchAll($sql, $params);
    }

    public function findWithRelations(int $id)
    {
        // COALESCE ke master Barang (i.min_stock) -- sama seperti listWithFilters(),
        // supaya kartu Stok Minimum di halaman Mutasi konsisten dengan Stok Barang.
        $sql = "SELECT inv.*, p.project_name,
                       COALESCE(i.min_stock, inv.min_stock, 0) AS min_stock
                FROM inventory inv
                LEFT JOIN projects p ON p.id = inv.project_id
                LEFT JOIN items i ON i.item_name = inv.item_name AND i.deleted_at IS NULL
                WHERE inv.id = :id AND inv.deleted_at IS NULL";
        return $this->db->fetchOne($sql, ['id' => $id]);
    }

    /**
     * Laporan mutasi stok per barang: Saldo Awal, Mutasi Masuk, Mutasi Keluar, Saldo
     * Akhir -- dihitung dari stock_transactions (kartu stok), bukan angka statis.
     * Saldo Akhir = Saldo Awal + Mutasi Masuk - Mutasi Keluar. Transaksi 'adjustment'
     * (mis. koreksi validasi/opname) dilipat ke Masuk (kalau qty positif) atau Keluar
     * (kalau qty negatif) supaya rumus di atas tetap balance.
     */
    public function stockMutationReport(array $filters = []): array
    {
        $rows = $this->listWithFilters($filters);
        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';

        foreach ($rows as &$row) {
            // Cocokkan berdasarkan item_name+unit+project_id+stock_scope (bukan cuma
            // inventory_id baris ini) supaya riwayat tetap lengkap walau baris stok
            // sebelumnya pernah dihapus & dibuat ulang (lihat deleteWithAdjustment()).
            $matchSql = "SELECT id FROM inventory WHERE item_name = :item_name AND unit = :unit AND stock_scope = :stock_scope"
                . ($row['project_id'] === null ? " AND project_id IS NULL" : " AND project_id = :project_id");
            $matchParams = ['item_name' => $row['item_name'], 'unit' => $row['unit'], 'stock_scope' => $row['stock_scope']];
            if ($row['project_id'] !== null) {
                $matchParams['project_id'] = $row['project_id'];
            }
            $matchingIds = array_column($this->db->fetchAll($matchSql, $matchParams), 'id');
            if (empty($matchingIds)) {
                $matchingIds = [(int) $row['id']];
            }
            $idPlaceholders = implode(',', array_fill(0, count($matchingIds), '?'));

            $awalSql = "SELECT COALESCE(SUM(CASE WHEN transaction_type = 'out' THEN -qty ELSE qty END), 0) AS saldo
                         FROM stock_transactions WHERE inventory_id IN ({$idPlaceholders})";
            $awalParams = $matchingIds;
            if ($dateFrom !== '') {
                $awalSql .= " AND transaction_date < ?";
                $awalParams[] = $dateFrom;
            } else {
                // Tanpa filter tanggal, seluruh riwayat termasuk dalam periode -> saldo awal 0
                $awalSql .= " AND 1=0";
            }
            $saldoAwal = (float) ($this->db->fetchOne($awalSql, $awalParams)['saldo'] ?? 0);

            $mutasiSql = "SELECT
                    COALESCE(SUM(CASE WHEN transaction_type = 'in' OR (transaction_type = 'adjustment' AND qty > 0) THEN qty ELSE 0 END), 0) AS masuk,
                    COALESCE(SUM(CASE WHEN transaction_type = 'out' THEN qty WHEN transaction_type = 'adjustment' AND qty < 0 THEN -qty ELSE 0 END), 0) AS keluar
                FROM stock_transactions WHERE inventory_id IN ({$idPlaceholders})";
            $mutasiParams = $matchingIds;
            if ($dateFrom !== '') {
                $mutasiSql .= " AND transaction_date >= ?";
                $mutasiParams[] = $dateFrom;
            }
            if ($dateTo !== '') {
                // ROOT FIX (rentang tanggal harus inclusive): transaction_date DATETIME
                // dibandingkan "<= date_to" akan dibaca sebagai "<= date_to 00:00:00" --
                // transaksi (terutama 'adjustment' dari reverseCredit/restoreStock/
                // adjustToActual, yang dicatat dengan jam sungguhan, bukan tengah malam)
                // di siang/sore tanggal akhir jadi ikut hilang. Pakai batas awal HARI
                // SETELAHNYA (eksklusif) supaya seluruh tanggal akhir tercakup.
                $mutasiSql .= " AND transaction_date < DATE_ADD(?, INTERVAL 1 DAY)";
                $mutasiParams[] = $dateTo;
            }
            $mutasi = $this->db->fetchOne($mutasiSql, $mutasiParams);

            // Tanggal transaksi TERAKHIR barang ini dalam periode yang sedang difilter --
            // dipakai untuk kolom "Tanggal" di Laporan Stok Barang. Batas sama persis dengan
            // query mutasi di atas (termasuk fix inclusive end-date) supaya konsisten.
            $lastDateSql = "SELECT MAX(transaction_date) AS last_date
                FROM stock_transactions WHERE inventory_id IN ({$idPlaceholders})";
            $lastDateParams = $matchingIds;
            if ($dateFrom !== '') {
                $lastDateSql .= " AND transaction_date >= ?";
                $lastDateParams[] = $dateFrom;
            }
            if ($dateTo !== '') {
                $lastDateSql .= " AND transaction_date < DATE_ADD(?, INTERVAL 1 DAY)";
                $lastDateParams[] = $dateTo;
            }
            $lastDate = $this->db->fetchOne($lastDateSql, $lastDateParams)['last_date'] ?? null;

            $row['saldo_awal'] = $saldoAwal;
            $row['mutasi_masuk'] = (float) ($mutasi['masuk'] ?? 0);
            $row['mutasi_keluar'] = (float) ($mutasi['keluar'] ?? 0);
            $row['saldo_akhir'] = $dateTo !== ''
                ? $saldoAwal + $row['mutasi_masuk'] - $row['mutasi_keluar']
                : (float) $row['qty_available'];
            $row['last_transaction_date'] = $lastDate ? substr($lastDate, 0, 10) : null;
        }

        return $rows;
    }

    /**
     * Cari semua inventory_id yang "sama" secara logis dengan $row (item_name+unit+
     * project_id+stock_scope) -- dipakai stockDetailReport()/stockRecapReport() supaya
     * histori tetap lengkap walau baris inventory pernah dihapus & dibuat ulang.
     * Duplikat kecil dari logic inline di stockMutationReport() (sengaja tidak
     * di-refactor ke situ supaya method itu tidak disentuh sama sekali -- lihat plan).
     */
    private function matchingInventoryIds(array $row): array
    {
        $matchSql = "SELECT id FROM inventory WHERE item_name = :item_name AND unit = :unit AND stock_scope = :stock_scope"
            . ($row['project_id'] === null ? " AND project_id IS NULL" : " AND project_id = :project_id");
        $matchParams = ['item_name' => $row['item_name'], 'unit' => $row['unit'], 'stock_scope' => $row['stock_scope']];
        if ($row['project_id'] !== null) {
            $matchParams['project_id'] = $row['project_id'];
        }
        $ids = array_column($this->db->fetchAll($matchSql, $matchParams), 'id');
        return empty($ids) ? [(int) $row['id']] : $ids;
    }

    /**
     * Resolusi "No Bukti" satu baris stock_transactions ke label yang dipahami user:
     * goods_receipt/stock_opname pakai nomor dokumen asli, stock_out/adjustment lain
     * pakai label singkat (stock_out tidak punya kolom nomor dokumen sendiri).
     */
    private function resolveNoBukti(array $t): string
    {
        if (($t['reference_type'] ?? '') === 'goods_receipt' && !empty($t['gr_number'])) {
            return $t['gr_number'];
        }
        if (($t['reference_type'] ?? '') === 'stock_opname' && !empty($t['opname_number'])) {
            return $t['opname_number'];
        }
        if (($t['reference_type'] ?? '') === 'kas' && !empty($t['kas_no_bukti'])) {
            return $t['kas_no_bukti'];
        }
        if (($t['transaction_type'] ?? '') === 'out') {
            return 'BK';
        }
        if (($t['transaction_type'] ?? '') === 'adjustment') {
            return 'BM';
        }
        return '-';
    }

    /**
     * Cetak Detail (Gambar 2): per barang, daftar transaksi mutasi dalam periode dengan
     * Saldo Awal/Akhir PER TRANSAKSI langsung dari kolom qty_before/qty_after yang sudah
     * tersimpan di stock_transactions (tidak dihitung ulang). Barang tanpa mutasi dalam
     * periode tidak disertakan (tidak ada baris untuk dicetak).
     */
    public function stockDetailReport(array $filters = []): array
    {
        $rows = $this->listWithFilters($filters);
        $dateFrom = $filters['date_from'] ?? '';
        $dateTo = $filters['date_to'] ?? '';
        $groups = [];

        foreach ($rows as $row) {
            $matchingIds = $this->matchingInventoryIds($row);
            $idPlaceholders = implode(',', array_fill(0, count($matchingIds), '?'));

            $sql = "SELECT st.*, gr.receipt_number AS gr_number, so.opname_number AS opname_number,
                           ct.no_bukti AS kas_no_bukti
                    FROM stock_transactions st
                    LEFT JOIN goods_receipts gr ON gr.id = st.reference_id AND st.reference_type = 'goods_receipt'
                    LEFT JOIN stock_opname so ON so.id = st.reference_id AND st.reference_type = 'stock_opname'
                    LEFT JOIN cash_transactions ct ON ct.id = st.reference_id AND st.reference_type = 'kas'
                    WHERE st.inventory_id IN ({$idPlaceholders})";
            $params = $matchingIds;
            if ($dateFrom !== '') {
                $sql .= " AND st.transaction_date >= ?";
                $params[] = $dateFrom;
            }
            if ($dateTo !== '') {
                // Lihat catatan ROOT FIX di stockMutationReport() -- batas eksklusif
                // awal hari berikutnya supaya seluruh tanggal akhir tercakup.
                $sql .= " AND st.transaction_date < DATE_ADD(?, INTERVAL 1 DAY)";
                $params[] = $dateTo;
            }
            $sql .= " ORDER BY st.transaction_date ASC, st.id ASC";
            $transactions = $this->db->fetchAll($sql, $params);

            if (empty($transactions)) {
                continue;
            }

            $lines = [];
            foreach ($transactions as $t) {
                $qty = (float) $t['qty'];
                $lines[] = [
                    'transaction_date' => $t['transaction_date'],
                    'no_bukti'         => $this->resolveNoBukti($t),
                    'saldo_awal'       => (float) $t['qty_before'],
                    'in_qty'           => ($t['transaction_type'] === 'in' || ($t['transaction_type'] === 'adjustment' && $qty > 0)) ? $qty : 0.0,
                    'out_qty'          => ($t['transaction_type'] === 'out' || ($t['transaction_type'] === 'adjustment' && $qty < 0)) ? abs($qty) : 0.0,
                    'saldo_akhir'      => (float) $t['qty_after'],
                ];
            }

            $groups[] = [
                'item_code'    => $row['item_code'],
                'item_name'    => $row['item_name'],
                'unit'         => $row['unit'],
                'project_name' => $row['project_name'],
                'lines'        => $lines,
                'saldo_akhir'  => (float) end($transactions)['qty_after'],
            ];
        }

        return $groups;
    }

    /**
     * Cetak Rekap (Gambar 3): reuse stockMutationReport() apa adanya (rumus saldo tidak
     * diduplikasi). Kolom pertama cetak rekap adalah "No" (nomor urut baris) -- rekap ini
     * 1 baris = 1 barang, jadi referensi dokumen tunggal per baris tidak relevan di sini
     * (beda dari Cetak Detail yang 1 baris = 1 transaksi, di situ "No Bukti" tetap dipakai).
     */
    public function stockRecapReport(array $filters = []): array
    {
        return $this->stockMutationReport($filters);
    }

    /**
     * Sesuaikan stok sistem supaya sama dengan hasil hitung fisik (stock opname).
     * Selisihnya (positif atau negatif) dicatat sebagai transaksi 'adjustment'.
     */
    public function adjustToActual(
        int $inventoryId,
        float $qtyActual,
        string $referenceType,
        int $referenceId,
        ?int $userId,
        string $notes = 'Penyesuaian stok opname'
    ): void {
        $existing = $this->find($inventoryId);
        if (!$existing) {
            return;
        }

        $qtyBefore = (float) $existing['qty_available'];
        $diff = $qtyActual - $qtyBefore;
        if ($diff == 0) {
            return;
        }

        $this->updateById($inventoryId, ['qty_available' => $qtyActual]);

        $stockTransaction = new StockTransaction();
        $stockTransaction->log(
            $inventoryId,
            'adjustment',
            $referenceType,
            $referenceId,
            $diff,
            $qtyBefore,
            $qtyActual,
            date('Y-m-d H:i:s'),
            $userId,
            $notes
        );
    }
}
