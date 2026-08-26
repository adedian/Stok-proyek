<?php
require_once ROOT_PATH . '/core/Database.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';
require_once ROOT_PATH . '/app/models/Item.php';

/**
 * DashboardStat
 * Bukan model CRUD biasa -- khusus kumpulan query agregat untuk kartu dashboard.
 * Query dibuat defensif (pakai IFNULL/COALESCE) karena di Phase 3 tabel
 * transaksional (purchase_orders, dll) sudah ada strukturnya dari Phase 2
 * tapi datanya masih kosong sampai modul terkait dibangun di phase berikutnya.
 */
class DashboardStat
{
    private Database $db;

    public function __construct()
    {
        $this->db = new Database();
    }

    public function totalPO(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM purchase_orders WHERE deleted_at IS NULL"
        )['total'] ?? 0);
    }

    public function barangMenungguDatang(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM purchase_orders
             WHERE status IN ('approved','partial_received') AND deleted_at IS NULL"
        )['total'] ?? 0);
    }

    public function barangDiterimaHariIni(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM goods_receipts
             WHERE receipt_date = CURDATE() AND deleted_at IS NULL"
        )['total'] ?? 0);
    }

    public function barangKeluarHariIni(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM stock_out
             WHERE out_date = CURDATE() AND deleted_at IS NULL"
        )['total'] ?? 0);
    }

    /**
     * Dipakai sebagai pembanding trend "Barang Diterima Hari Ini" vs kemarin.
     */
    public function barangDiterimaKemarin(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM goods_receipts
             WHERE receipt_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND deleted_at IS NULL"
        )['total'] ?? 0);
    }

    /**
     * Dipakai sebagai pembanding trend "Barang Keluar Hari Ini" vs kemarin.
     */
    public function barangKeluarKemarin(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM stock_out
             WHERE out_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND deleted_at IS NULL"
        )['total'] ?? 0);
    }

    /**
     * Stok tersedia DIPECAH per satuan (bukan 1 angka gabungan) -- SUM lintas
     * satuan berbeda (m + Roll + Pcs + Set, dst) tidak punya arti kuantitas
     * yang valid, jadi kartu KPI "Stok Tersedia" di dashboard butuh breakdown
     * per unit, bukan total tunggal. Urut dari yang paling banyak.
     */
    public function stokTersediaPerSatuan(): array
    {
        return $this->db->fetchAll(
            "SELECT unit, COALESCE(SUM(qty_available),0) AS total
             FROM inventory WHERE deleted_at IS NULL
             GROUP BY unit ORDER BY total DESC"
        );
    }

    /**
     * Invoice Keluar (sales_invoices, satu-satunya alur invoice yang masih aktif
     * dipakai -- modul Invoice AP lama sudah 0 data) TIDAK punya kolom status
     * pending/valid sama sekali. Proxy terdekat untuk "belum tertagih": belum
     * pernah muncul di Tanda Terima manapun (collection_receipt_items), dan
     * Tanda Terima-nya sendiri belum dibatalkan (soft-deleted). Definisi yang
     * SAMA PERSIS dipakai lagi di SalesInvoice::TERTAGIH_EXISTS (kolom
     * is_tertagih + filter billing_status di list Invoice Keluar) supaya kartu
     * dashboard ini & badge "Status Tagih" di layar selalu konsisten.
     */
    public function invoiceBelumTertagih(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM sales_invoices si
             WHERE si.deleted_at IS NULL
             AND NOT EXISTS (
                 SELECT 1 FROM collection_receipt_items cri
                 JOIN collection_receipts cr ON cr.id = cri.collection_receipt_id AND cr.deleted_at IS NULL
                 WHERE cri.sales_invoice_id = si.id
             )"
        )['total'] ?? 0);
    }

    public function pembelianOffline(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM offline_purchases WHERE deleted_at IS NULL"
        )['total'] ?? 0);
    }

    /**
     * Jumlah item penerimaan barang yang selisih (kurang/lebih/barang_lain) & belum divalidasi.
     * Dipakai sebagai badge notifikasi di dashboard & sidebar (Phase 7).
     */
    public function barangSelisihBelumValidasi(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM goods_receipt_items gri
             JOIN goods_receipts gr ON gr.id = gri.goods_receipt_id AND gr.deleted_at IS NULL
             WHERE gri.comparison_status != 'sesuai' AND gri.validated_at IS NULL"
        )['total'] ?? 0);
    }

    /**
     * Jumlah PO yang masih menunggu approval -- dipakai untuk banner notifikasi
     * "PO Belum Diproses" di dashboard (bisa dimatikan lewat Pengaturan Notifikasi).
     */
    public function poBelumDiproses(): int
    {
        return (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM purchase_orders
             WHERE status = 'waiting_approval' AND deleted_at IS NULL"
        )['total'] ?? 0);
    }

    /**
     * Ringkasan peringatan untuk notification bell di topbar -- versi ringkas
     * dari alert yang sama dipakai di dashboard, dicek ulang di sini supaya
     * muncul di SEMUA halaman (bukan cuma saat buka dashboard). Dibatasi role
     * yang relevan dan bisa dimatikan lewat Pengaturan Notifikasi.
     */
    public function topbarSummary(): array
    {
        $settingModel = new SystemSetting();
        $items = [];

        if ($settingModel->getBool('notify_selisih_barang', true) && hasRole([ROLE_SUPER_ADMIN, ROLE_GUDANG])) {
            $count = $this->barangSelisihBelumValidasi();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-exclamation-triangle-fill', 'variant' => 'warning',
                    'title' => 'Selisih Barang',
                    'desc' => "{$count} item penerimaan barang belum divalidasi.",
                    'module' => 'validation',
                ];
            }
        }

        if ($settingModel->getBool('notify_stok_minimum', true) && hasRole([ROLE_SUPER_ADMIN, ROLE_GUDANG])) {
            $count = (new Item())->belowMinStockCount();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-exclamation-octagon-fill', 'variant' => 'danger',
                    'title' => 'Stok Minimum',
                    'desc' => "{$count} barang di bawah stok minimum.",
                    'module' => 'master_data',
                ];
            }
        }

        if ($settingModel->getBool('notify_po_belum_diproses', true) && hasRole([ROLE_SUPER_ADMIN, ROLE_FINANCE])) {
            $count = $this->poBelumDiproses();
            if ($count > 0) {
                $items[] = [
                    'icon' => 'bi-hourglass-split', 'variant' => 'info',
                    'title' => 'PO Belum Diproses',
                    'desc' => "{$count} Purchase Order menunggu approval.",
                    'module' => 'purchase_order',
                ];
            }
        }

        return $items;
    }

    /**
     * Data grafik "Aktivitas Stok" (PO / Barang Diterima / Barang Keluar) untuk Chart.js.
     * period: '7d' | '30d' | 'month' (bulan ini) | 'year' (tahun ini, dikelompokkan per bulan).
     * Query dibuat dengan generate_series manual (loop PHP) supaya hari/bulan tanpa data
     * tetap tampil sebagai 0 di grafik, bukan hilang dari sumbu X.
     */
    public function chartData(string $period): array
    {
        if ($period === 'year') {
            return $this->chartDataByMonth();
        }

        $days = $period === '30d' ? 30 : ($period === 'month' ? (int) date('j') - 1 : 6);
        $start = $period === 'month'
            ? date('Y-m-01')
            : date('Y-m-d', strtotime("-{$days} days"));

        $poRows = $this->db->fetchAll(
            "SELECT DATE(created_at) AS d, COUNT(*) AS total FROM purchase_orders
             WHERE deleted_at IS NULL AND created_at >= :start GROUP BY DATE(created_at)",
            ['start' => $start . ' 00:00:00']
        );
        $receiptRows = $this->db->fetchAll(
            "SELECT receipt_date AS d, COUNT(*) AS total FROM goods_receipts
             WHERE deleted_at IS NULL AND receipt_date >= :start GROUP BY receipt_date",
            ['start' => $start]
        );
        $outRows = $this->db->fetchAll(
            "SELECT out_date AS d, COUNT(*) AS total FROM stock_out
             WHERE deleted_at IS NULL AND out_date >= :start GROUP BY out_date",
            ['start' => $start]
        );

        $poByDate = array_column($poRows, 'total', 'd');
        $receiptByDate = array_column($receiptRows, 'total', 'd');
        $outByDate = array_column($outRows, 'total', 'd');

        $labels = [];
        $po = [];
        $diterima = [];
        $keluar = [];

        $cursor = strtotime($start);
        $today = strtotime(date('Y-m-d'));
        while ($cursor <= $today) {
            $key = date('Y-m-d', $cursor);
            $labels[] = date('d/m', $cursor);
            $po[] = (int) ($poByDate[$key] ?? 0);
            $diterima[] = (int) ($receiptByDate[$key] ?? 0);
            $keluar[] = (int) ($outByDate[$key] ?? 0);
            $cursor = strtotime('+1 day', $cursor);
        }

        return ['labels' => $labels, 'po' => $po, 'diterima' => $diterima, 'keluar' => $keluar];
    }

    /**
     * Grafik dikelompokkan per bulan untuk filter "Tahun Ini".
     */
    private function chartDataByMonth(): array
    {
        $start = date('Y-01-01');

        $poRows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS m, COUNT(*) AS total FROM purchase_orders
             WHERE deleted_at IS NULL AND created_at >= :start GROUP BY m",
            ['start' => $start . ' 00:00:00']
        );
        $receiptRows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(receipt_date, '%Y-%m') AS m, COUNT(*) AS total FROM goods_receipts
             WHERE deleted_at IS NULL AND receipt_date >= :start GROUP BY m",
            ['start' => $start]
        );
        $outRows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(out_date, '%Y-%m') AS m, COUNT(*) AS total FROM stock_out
             WHERE deleted_at IS NULL AND out_date >= :start GROUP BY m",
            ['start' => $start]
        );

        $poByMonth = array_column($poRows, 'total', 'm');
        $receiptByMonth = array_column($receiptRows, 'total', 'm');
        $outByMonth = array_column($outRows, 'total', 'm');

        $bulanIndo = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $labels = [];
        $po = [];
        $diterima = [];
        $keluar = [];

        $currentMonth = (int) date('n');
        for ($m = 1; $m <= $currentMonth; $m++) {
            $key = date('Y') . '-' . str_pad((string) $m, 2, '0', STR_PAD_LEFT);
            $labels[] = $bulanIndo[$m - 1];
            $po[] = (int) ($poByMonth[$key] ?? 0);
            $diterima[] = (int) ($receiptByMonth[$key] ?? 0);
            $keluar[] = (int) ($outByMonth[$key] ?? 0);
        }

        return ['labels' => $labels, 'po' => $po, 'diterima' => $diterima, 'keluar' => $keluar];
    }

    public function getAllStats(): array
    {
        return [
            'total_po'               => $this->totalPO(),
            'menunggu_datang'        => $this->barangMenungguDatang(),
            'diterima_hari_ini'      => $this->barangDiterimaHariIni(),
            'keluar_hari_ini'        => $this->barangKeluarHariIni(),
            'stok_tersedia_per_satuan' => $this->stokTersediaPerSatuan(),
            'invoice_pending'        => $this->invoiceBelumTertagih(),
            'pembelian_offline'      => $this->pembelianOffline(),
            'selisih_belum_validasi' => $this->barangSelisihBelumValidasi(),
        ];
    }
}
