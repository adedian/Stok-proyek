<?php
require_once ROOT_PATH . '/core/Controller.php';
require_once ROOT_PATH . '/core/Middleware.php';
require_once ROOT_PATH . '/app/models/PurchaseOrder.php';
require_once ROOT_PATH . '/app/models/Payment.php';
require_once ROOT_PATH . '/app/models/GoodsReceipt.php';
require_once ROOT_PATH . '/app/models/StockOut.php';
require_once ROOT_PATH . '/app/models/Inventory.php';
require_once ROOT_PATH . '/app/models/StockOpname.php';
require_once ROOT_PATH . '/app/models/SalesInvoice.php';
require_once ROOT_PATH . '/app/models/OfflinePurchase.php';
require_once ROOT_PATH . '/app/models/ActivityLog.php';
require_once ROOT_PATH . '/app/models/Project.php';
require_once ROOT_PATH . '/app/models/User.php';
require_once ROOT_PATH . '/app/models/SystemSetting.php';

/**
 * ReportController
 * Satu action per jenis laporan (Phase 12). Semua query REUSE method listWithRelations/
 * listWithFilters yang sudah ada di masing-masing model -- controller ini hanya menambah
 * filter tanggal seragam + memetakan hasilnya ke definisi kolom untuk view/CSV/PDF yang sama.
 */
class ReportController extends Controller
{
    public function __construct()
    {
        Middleware::requirePermission('report', 'view');
        $this->guardReportScope();
    }

    /**
     * Revisi 9: PIC Project & Project Manager hanya boleh Laporan Kartu Stok
     * (Stok Barang). Selain Super Admin/Purchase/Accounting, action laporan
     * lain (PO, Pembayaran, Invoice, Audit Trail, dst) ditolak server-side --
     * bukan sekadar disembunyikan di menu.
     */
    private function guardReportScope(): void
    {
        $role = currentUserRole();
        if (in_array($role, [ROLE_SUPER_ADMIN, ROLE_PURCHASE, ROLE_ACCOUNTING], true)) {
            return;
        }

        $action = $_GET['action'] ?? 'index';
        $stockOnly = [
            'index', 'inventory',
            'printStockDetail', 'printStockRecap',
            'exportStockDetail', 'exportStockRecap',
        ];
        $genericExport = ['exportExcel', 'exportPdf'];

        if (in_array($action, $stockOnly, true)) {
            return;
        }
        if (in_array($action, $genericExport, true) && ($_GET['type'] ?? '') === 'inventory') {
            return;
        }

        denyAccess('Laporan di luar cakupan role (hanya Laporan Kartu Stok yang diizinkan)');
    }

    public function index()
    {
        $this->view('report/index', ['pageTitle' => 'Laporan']);
    }

    public function po()
    {
        $this->renderReport('po');
    }

    public function payment()
    {
        $this->renderReport('payment');
    }

    public function goodsReceipt()
    {
        $this->renderReport('goodsReceipt');
    }

    public function stockOut()
    {
        $this->renderReport('stockOut');
    }

    public function inventory()
    {
        $this->renderReport('inventory');
    }

    public function stockOpname()
    {
        $this->renderReport('stockOpname');
    }

    public function invoice()
    {
        $this->renderReport('invoice');
    }

    public function offlinePurchase()
    {
        $this->renderReport('offlinePurchase');
    }

    public function activityLog()
    {
        $this->renderReport('activityLog');
    }

    /**
     * Export Excel BERGAYA (judul/nama perusahaan/periode + header bold+border,
     * sama seperti "Laporan Rekap PO") untuk 8 laporan generik lainnya (Pembayaran,
     * Penerimaan Barang, dst) -- reuse definisi kolom/rows dari buildReport() yang
     * sama dipakai tampilan layar & Export PDF, cuma gantikan exportCsv() lama
     * (text/csv polos, tidak bisa styling) dengan streamExcelReport() (excel_helper.php).
     * Laporan PO PUNYA tombol sendiri (exportPoDetail/exportPoRecap) karena
     * layoutnya beda (per-item vs per-PO), tidak lewat sini.
     */
    public function exportExcel(): void
    {
        $type = $_GET['type'] ?? '';
        $report = $this->buildReport($type, $_GET);

        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $dateFrom = trim($_GET['date_from'] ?? '');
        $dateTo = trim($_GET['date_to'] ?? '');
        $periodText = 'Periode : '
            . ($dateFrom ? formatTanggal($dateFrom) : 'Awal')
            . ' - '
            . ($dateTo ? formatTanggal($dateTo) : 'Sekarang');

        streamExcelReport($report['title'], $companyName, $periodText, $report['columns'], $report['rows'], $type . '_' . date('Ymd_His'));
    }

    /**
     * Export Excel "Laporan Rekap PO" - Detail Barang (Gambar 1 referensi):
     * satu baris per barang PO (Tanggal/Supplier/Kode Barang/Nama Barang/Qty/
     * Harga Satuan/Total/Pembuat PO), file .xlsx bergaya (bukan CSV polos).
     */
    public function exportPoDetail(): void
    {
        $filters = $this->poReportFilters($_GET);
        $rows = (new PurchaseOrder())->listItemsForReport($filters);
        $this->streamPoRecapExcel($filters, $this->poDetailColumns(), $rows, 'laporan_rekap_po_detail_barang');
    }

    /**
     * Export Excel "Laporan Rekap PO" - Rekap Pembayaran (Gambar 2 referensi):
     * satu baris per PO (Tanggal/Supplier/Nilai PO/Total dibayar/Sisa belum
     * dibayar/% blm dibayar), file .xlsx bergaya (bukan CSV polos).
     */
    public function exportPoRecap(): void
    {
        $filters = $this->poReportFilters($_GET);
        $rows = (new PurchaseOrder())->listRecapForReport($filters);
        $this->streamPoRecapExcel($filters, $this->poRecapColumns(), $rows, 'laporan_rekap_po_pembayaran');
    }

    /**
     * Versi PDF dari exportPoDetail() -- kolom & data SAMA PERSIS (poDetailColumns()
     * dipakai bareng), cuma output-nya PDF (Dompdf, landscape) bukan .xlsx.
     */
    public function printPoDetail(): void
    {
        $filters = $this->poReportFilters($_GET);
        $rows = (new PurchaseOrder())->listItemsForReport($filters);
        $this->streamPoRecapPdf($filters, $this->poDetailColumns(), $rows, 'laporan_rekap_po_detail_barang');
    }

    /**
     * Versi PDF dari exportPoRecap() -- kolom & data SAMA PERSIS (poRecapColumns()
     * dipakai bareng), cuma output-nya PDF (Dompdf, landscape) bukan .xlsx.
     */
    public function printPoRecap(): void
    {
        $filters = $this->poReportFilters($_GET);
        $rows = (new PurchaseOrder())->listRecapForReport($filters);
        $this->streamPoRecapPdf($filters, $this->poRecapColumns(), $rows, 'laporan_rekap_po_pembayaran');
    }

    public function exportPdf()
    {
        $type = $_GET['type'] ?? '';
        $report = $this->buildReport($type, $_GET);

        ob_start();
        $columns = $report['columns'];
        $rows = $report['rows'];
        $title = $report['title'];
        require ROOT_PATH . '/app/views/report/_pdf_table.php';
        $html = ob_get_clean();

        streamPdf($html, $type . '_' . date('Ymd_His'));
    }

    /**
     * Cetak Detail Laporan Stok Barang (Gambar 2 referensi) -- per transaksi mutasi,
     * beda dari exportPdf() generik yang dipakai 8 laporan lain.
     */
    public function printStockDetail(): void
    {
        $filters = $this->stockFilters($_GET);
        $model = new Inventory();
        $groups = $model->stockDetailReport($filters);
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $showPrice = $filters['show_price'];

        ob_start();
        require ROOT_PATH . '/app/views/report/_stock_detail_print.php';
        $html = ob_get_clean();

        streamPdf($html, 'laporan_stok_detail_' . date('Ymd_His'));
    }

    /**
     * Cetak Rekap Laporan Stok Barang (Gambar 3 referensi) -- ringkasan per barang.
     */
    public function printStockRecap(): void
    {
        $filters = $this->stockFilters($_GET);
        $model = new Inventory();
        $rows = $model->stockRecapReport($filters);
        $dateFrom = $filters['date_from'];
        $dateTo = $filters['date_to'];
        $showPrice = $filters['show_price'];

        ob_start();
        require ROOT_PATH . '/app/views/report/_stock_recap_print.php';
        $html = ob_get_clean();

        streamPdf($html, 'laporan_stok_rekap_' . date('Ymd_His'));
    }

    /**
     * Export Excel DETAIL Laporan Stok Barang -- struktur & angka SAMA PERSIS
     * dengan printStockDetail() (Gambar 2): pakai stockFilters($_GET) &
     * Inventory::stockDetailReport() yang identik, jadi filter periode/barang/
     * "Stok != 0"/pilihan baris ikut sama (Revisi 8 #9-#13).
     */
    public function exportStockDetail(): void
    {
        $filters = $this->stockFilters($_GET);
        $groups = (new Inventory())->stockDetailReport($filters);
        [$companyName, $periodText] = $this->stockReportHeaderMeta($filters);
        streamStockDetailExcel($groups, $companyName, $periodText, 'laporan_stok_detail_' . date('Ymd_His'), $filters['show_price']);
    }

    /**
     * Export Excel REKAP Laporan Stok Barang -- struktur & angka SAMA PERSIS
     * dengan printStockRecap() (Gambar 3): pakai stockFilters($_GET) &
     * Inventory::stockRecapReport() yang identik (Revisi 8 #9-#13).
     */
    public function exportStockRecap(): void
    {
        $filters = $this->stockFilters($_GET);
        $rows = (new Inventory())->stockRecapReport($filters);
        [$companyName, $periodText] = $this->stockReportHeaderMeta($filters);
        streamStockRecapExcel($rows, $companyName, $periodText, 'laporan_stok_rekap_' . date('Ymd_His'), $filters['show_price']);
    }

    /**
     * Nama perusahaan + baris periode untuk header Excel Stok -- format periode
     * dibuat dari date_from/date_to yang SAMA dengan yang dipakai template PDF
     * stok (_stock_detail_print.php/_stock_recap_print.php).
     */
    private function stockReportHeaderMeta(array $filters): array
    {
        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';
        $periodText = 'Periode : '
            . ($filters['date_from'] !== '' ? formatTanggal($filters['date_from']) : '(seluruh riwayat)')
            . ' - '
            . ($filters['date_to'] !== '' ? formatTanggal($filters['date_to']) : 'Sekarang');
        return [$companyName, $periodText];
    }

    // ================= Helper privat =================

    private function stockFilters(array $get): array
    {
        return [
            'project_id'   => trim($get['project_id'] ?? ''),
            'keyword'      => trim($get['keyword'] ?? ''),
            'stock_filter' => trim($get['stock_filter'] ?? ''),
            'item_status'  => trim($get['item_status'] ?? ''),
            'date_from'    => trim($get['date_from'] ?? ''),
            'date_to'      => trim($get['date_to'] ?? ''),
            // Tampilkan kolom "Dengan Harga" di Cetak/Export? Hanya role ber-izin
            // (report.stock_price = Super Admin & Accounting) yang boleh; role lain
            // SELALU tanpa harga, walau param di URL diutak-atik.
            'show_price'   => (can('report', 'stock_price') && trim($get['show_price'] ?? '1') !== '0'),
            // Baris yang dicentang user di layar Laporan Stok Barang (pilih per barang)
            // -- divalidasi ulang di Inventory::listWithFilters() (cast ke int + AND
            // inv.id IN (...)), bukan sekadar dipercaya dari frontend.
            'ids'          => $this->sanitizeIds($get['ids'] ?? []),
        ];
    }

    private function sanitizeIds($rawIds): array
    {
        if (!is_array($rawIds)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map('intval', $rawIds), fn($id) => $id > 0)));
    }

    private function renderReport(string $type): void
    {
        $report = $this->buildReport($type, $_GET);

        $this->view('report/_view', [
            'pageTitle'   => $report['title'],
            'reportKey'   => $type,
            'columns'     => $report['columns'],
            'rows'        => $report['rows'],
            'filterForm'  => $report['filterForm'],
            'filters'     => $report['filters'],
            'exportQuery' => $report['exportQuery'],
            'projects'    => (new Project())->activeList(),
        ]);
    }

    /**
     * Bangun definisi satu laporan: judul, kolom, baris data, dan info filter yang dipakai ulang
     * oleh tampilan layar, export CSV, dan export PDF supaya hasilnya selalu konsisten.
     */
    private function buildReport(string $type, array $get): array
    {
        $projectModel = new Project();
        $dateFrom = trim($get['date_from'] ?? '');
        $dateTo = trim($get['date_to'] ?? '');
        $projectId = trim($get['project_id'] ?? '');
        $keyword = trim($get['keyword'] ?? '');
        $status = trim($get['status'] ?? '');

        // Laporan Pembelian Offline (layar + Export Excel/PDF) hanya untuk yang
        // punya akses modul Pembelian Offline -- guardReportScope() cuma menyaring
        // per-role, Purchase/Accounting lolos di situ padahal aksesnya bisa dicabut.
        if ($type === 'offlinePurchase' && !can('offline_purchase', 'view')) {
            denyAccess('Tidak punya akses modul Pembelian Offline');
        }

        switch ($type) {
            case 'po':
                // Laporan Rekap PO: layar & Export PDF generik menampilkan versi PER ITEM
                // (satu baris = satu baris barang PO) -- sama dengan export "Detail Barang".
                // Export Excel-nya sendiri PAKAI 2 tombol khusus (exportPoDetail/exportPoRecap,
                // lihat _table.php), BUKAN exportCsv() generik, karena harus persis format
                // "Laporan Rekap PO" (judul/subjudul/periode + border) yang diminta user --
                // exportCsv() cuma dump text/csv polos, tidak bisa styling.
                // Laporan PO DETAIL: per baris item PO. Kolom + urutannya SATU sumber
                // (poDetailColumns()) dipakai bareng tampilan layar, Export Excel Detail,
                // & PDF Detail -- urutan WAJIB: No, Tanggal, No PO, Supplier, Kode Barang,
                // Nama Barang, Qty, Satuan, Harga Satuan, Total, Pembuat PO (Revisi 8 #1/#3).
                $model = new PurchaseOrder();
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'project_id' => $projectId, 'status' => $status, 'keyword' => $keyword];
                $rows = $model->listItemsForReport($filters);
                return [
                    'title' => 'Laporan PO - Detail Barang',
                    'columns' => $this->poDetailColumns(),
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'project' => true, 'status' => $model->statusLabels, 'keyword' => true],
                    'filters' => compact('dateFrom', 'dateTo', 'projectId', 'status', 'keyword'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'payment':
                $model = new Payment();
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'project_id' => $projectId, 'status' => $status, 'keyword' => $keyword];
                $rows = $model->listWithRelations($filters);
                foreach ($rows as &$r) {
                    $r['status_label'] = $model->statusLabels[$r['status']] ?? $r['status'];
                    $r['funding_source_label'] = $model->fundingSourceLabels[$r['funding_source']] ?? $r['funding_source'];
                }
                return [
                    'title' => 'Laporan Pembayaran',
                    'columns' => [
                        ['field' => 'payment_number', 'label' => 'No. Pembayaran'],
                        ['field' => 'po_number', 'label' => 'No. PO'],
                        ['field' => 'pembuat_po', 'label' => 'Pembuat PO'],
                        ['field' => 'project_name', 'label' => 'Project'],
                        ['field' => 'supplier_name', 'label' => 'Supplier'],
                        ['field' => 'payment_date', 'label' => 'Tanggal', 'format' => 'date'],
                        ['field' => 'termin', 'label' => 'Termin'],
                        ['field' => 'funding_source_label', 'label' => 'Sumber Dana'],
                        ['field' => 'method_name', 'label' => 'Metode'],
                        ['field' => 'status_label', 'label' => 'Status'],
                        ['field' => 'amount', 'label' => 'Nominal', 'format' => 'rupiah', 'align' => 'end', 'sum' => true],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'project' => true, 'status' => $model->statusLabels, 'keyword' => true],
                    'filters' => compact('dateFrom', 'dateTo', 'projectId', 'status', 'keyword'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'goodsReceipt':
                require_once ROOT_PATH . '/app/models/GoodsReceiptItem.php';
                $model = new GoodsReceiptItem();
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'project_id' => $projectId, 'keyword' => $keyword];
                $rows = $model->reportRows($filters);
                return [
                    'title' => 'Laporan Penerimaan Barang',
                    'columns' => [
                        ['field' => 'po_number', 'label' => 'No PO'],
                        ['field' => 'pembuat_po', 'label' => 'Pembuat PO'],
                        ['field' => 'supplier_name', 'label' => 'Supplier'],
                        ['field' => 'receipt_date', 'label' => 'Tgl', 'format' => 'date'],
                        ['field' => 'diterima_label', 'label' => 'Diterima'],
                        ['field' => 'item_name', 'label' => 'Nama Barang'],
                        ['field' => 'qty_received', 'label' => 'Qty', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'unit', 'label' => 'Satuan'],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'project' => true, 'keyword' => true],
                    'filters' => compact('dateFrom', 'dateTo', 'projectId', 'keyword'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'stockOut':
                $model = new StockOut();
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'project_id' => $projectId, 'keyword' => $keyword];
                $rows = $model->listWithRelations($filters);
                return [
                    'title' => 'Laporan Pengeluaran Barang',
                    'columns' => [
                        ['field' => 'out_date', 'label' => 'Tanggal', 'format' => 'date'],
                        ['field' => 'item_name', 'label' => 'Barang'],
                        ['field' => 'project_name', 'label' => 'Project'],
                        ['field' => 'destination', 'label' => 'Tujuan'],
                        ['field' => 'pic_name', 'label' => 'PIC'],
                        ['field' => 'qty', 'label' => 'Qty', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'unit', 'label' => 'Satuan'],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'project' => true, 'keyword' => true],
                    'filters' => compact('dateFrom', 'dateTo', 'projectId', 'keyword'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'inventory':
                $model = new Inventory();
                $stockFilter = trim($get['stock_filter'] ?? '');
                $itemStatus = trim($get['item_status'] ?? '');
                // Filter Kategori (jenis stok) -- HANYA memengaruhi tampilan layar,
                // TIDAK ikut ke Cetak/Export (stockFilters() & buildExportQuery()
                // sengaja tidak membawa 'stock_type').
                $stockTypeLabels = [
                    'stok_proyek'      => 'Stok Proyek',
                    'stok_lampu'       => 'Stok Lampu',
                    'inventory_kantor' => 'Inventory Kantor',
                ];
                $stockType = isset($stockTypeLabels[trim($get['stock_type'] ?? '')]) ? trim($get['stock_type']) : '';
                // Toggle "Tampilkan/Tanpa harga" HANYA untuk role ber-izin
                // (report.stock_price). Role lain: kontrol disembunyikan & output
                // Cetak/Export dipaksa tanpa harga (lihat stockFilters()).
                $canStockPrice = can('report', 'stock_price');
                $showPrice = ($canStockPrice && trim($get['show_price'] ?? '1') !== '0') ? '1' : '0';
                $filters = [
                    'project_id' => $projectId, 'keyword' => $keyword, 'stock_filter' => $stockFilter,
                    'item_status' => $itemStatus, 'date_from' => $dateFrom, 'date_to' => $dateTo,
                    'stock_type' => $stockType,
                ];
                $rows = $model->stockMutationReport($filters);
                foreach ($rows as &$r) {
                    // Status = level ITEM (total lintas project) -- definisi sama
                    // dengan alert "Stok Minimum" Dashboard & halaman Stok Barang.
                    $tot = (float) ($r['item_total_available'] ?? $r['qty_available']);
                    $min = (float) $r['min_stock'];
                    $r['status_label'] = $tot <= 0 ? 'Habis' : ($min > 0 && $tot <= $min ? 'Minimum' : 'Aman');
                }
                unset($r);
                return [
                    'title' => 'Laporan Stok Barang',
                    'columns' => [
                        ['field' => 'last_transaction_date', 'label' => 'Tanggal', 'format' => 'date'],
                        ['field' => 'item_name', 'label' => 'Barang'],
                        ['field' => 'item_code', 'label' => 'Kode Barang'],
                        ['field' => 'project_name', 'label' => 'Project'],
                        ['field' => 'saldo_awal', 'label' => 'Saldo Awal', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'mutasi_masuk', 'label' => 'Mutasi Masuk', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'mutasi_keluar', 'label' => 'Mutasi Keluar', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'saldo_akhir', 'label' => 'Saldo Akhir', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'unit', 'label' => 'Satuan'],
                        ['field' => 'status_label', 'label' => 'Status'],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'project' => true, 'keyword' => true, 'stockStatus' => true, 'itemStatus' => true, 'stockType' => $stockTypeLabels, 'priceMode' => $canStockPrice],
                    'filters' => compact('dateFrom', 'dateTo', 'projectId', 'keyword', 'stockFilter', 'itemStatus', 'stockType', 'showPrice'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'stockOpname':
                // ROOT FIX (audit sinkronisasi Stok Barang <-> Stok Opname <-> Laporan):
                // laporan versi lama cuma list header stock_opname (1 baris per opname,
                // TANPA barang sama sekali) -- makanya "tidak sesuai dengan barang yang
                // sebenarnya ada". Sekarang sumbernya StockOpnameItem::reportRows(), yang
                // SELALU baca qty_system/qty_actual/difference yang TERSIMPAN di
                // stock_opname_items (snapshot asli saat opname itu dibuat), bukan
                // dihitung ulang dari inventory hari ini -- histori lama harus tetap
                // menunjukkan angka saat itu walau stok sekarang sudah berubah/barangnya
                // sudah dihapus (lihat StockOpnameItem::reportRows()).
                require_once ROOT_PATH . '/app/models/StockOpnameItem.php';
                $opnameModel = new StockOpname();
                $itemModel = new StockOpnameItem();
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'project_id' => $projectId, 'status' => $status];
                $rows = $itemModel->reportRows($filters);
                foreach ($rows as &$r) {
                    $r['status_label'] = $opnameModel->statusLabels[$r['opname_status']] ?? $r['opname_status'];
                    $r['location_label'] = $r['project_name'] ?? 'Kantor';
                    // Tandai (bukan sembunyikan/hapus) barang yang master inventory-nya
                    // sudah soft-delete SETELAH opname ini dibuat -- histori tetap utuh,
                    // cuma dikasih info supaya tidak disangka bug seperti laporan ini.
                    if (!empty($r['inventory_deleted_at'])) {
                        $r['item_name'] .= ' (barang sudah dihapus)';
                    }
                }
                return [
                    'title' => 'Laporan Stok Opname',
                    'columns' => [
                        ['field' => 'opname_number', 'label' => 'No. Opname'],
                        ['field' => 'opname_date', 'label' => 'Tanggal', 'format' => 'date'],
                        ['field' => 'item_name', 'label' => 'Barang'],
                        ['field' => 'item_code', 'label' => 'Kode Barang'],
                        ['field' => 'qty_system', 'label' => 'Stok Sistem', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'qty_actual', 'label' => 'Stok Fisik', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'difference', 'label' => 'Selisih', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'unit', 'label' => 'Satuan'],
                        ['field' => 'status_label', 'label' => 'Status'],
                        ['field' => 'location_label', 'label' => 'Lokasi/Project'],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'project' => true, 'status' => $opnameModel->statusLabels],
                    'filters' => compact('dateFrom', 'dateTo', 'projectId', 'status'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'invoice':
                // Laporan Invoice Keluar (AR, HME -> client) -- lihat sales_invoices.
                // Laporan ini SEBELUMNYA melaporkan invoice AP (supplier -> HME, tabel
                // `invoices`); sekarang diganti supaya "Laporan Invoice" tidak lagi
                // duplikat/rancu dengan modul Invoice Keluar. Modul AP `invoices`
                // (sidebar "Invoice", validasi PO/pembayaran) TIDAK dihapus/diubah,
                // cuma tidak lagi punya entry laporan terpisah di sini.
                $model = new SalesInvoice();
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'keyword' => $keyword];
                $rows = $model->listWithRelations($filters);
                return [
                    'title' => 'Laporan Invoice Keluar',
                    'columns' => [
                        ['field' => 'invoice_number', 'label' => 'No. Invoice'],
                        ['field' => 'client_name', 'label' => 'Client'],
                        ['field' => 'project_name', 'label' => 'Project'],
                        ['field' => 'invoice_date', 'label' => 'Tanggal', 'format' => 'date'],
                        ['field' => 'subtotal', 'label' => 'Jumlah', 'format' => 'rupiah', 'align' => 'end'],
                        ['field' => 'dp_percentage', 'label' => 'DP %', 'format' => 'percent', 'align' => 'end'],
                        ['field' => 'dp_amount', 'label' => 'Tagihan DP', 'format' => 'rupiah', 'align' => 'end', 'sum' => true],
                        ['field' => 'ppn_amount', 'label' => 'PPN', 'format' => 'rupiah', 'align' => 'end', 'sum' => true],
                        ['field' => 'total_amount', 'label' => 'Total', 'format' => 'rupiah', 'align' => 'end', 'sum' => true],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'keyword' => true],
                    'filters' => compact('dateFrom', 'dateTo', 'keyword'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'offlinePurchase':
                require_once ROOT_PATH . '/app/models/OfflinePurchaseItem.php';
                $itemModel = new OfflinePurchaseItem();
                $offlinePurchaseModel = new OfflinePurchase();
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'project_id' => $projectId, 'keyword' => $keyword];
                $rows = $itemModel->reportRows($filters);
                foreach ($rows as &$r) {
                    $r['status_label'] = $offlinePurchaseModel->statusLabels[$r['status']] ?? $r['status'];
                }
                return [
                    'title' => 'Laporan Pembelian Offline',
                    'columns' => [
                        ['field' => 'purchase_number', 'label' => 'No. Pembelian'],
                        ['field' => 'purchase_date', 'label' => 'Tanggal', 'format' => 'date'],
                        ['field' => 'item_name', 'label' => 'Barang'],
                        ['field' => 'supplier_name', 'label' => 'Supplier'],
                        ['field' => 'project_name', 'label' => 'Project'],
                        ['field' => 'qty', 'label' => 'Qty', 'format' => 'number', 'align' => 'end'],
                        ['field' => 'unit', 'label' => 'Satuan'],
                        ['field' => 'price', 'label' => 'Harga', 'format' => 'rupiah', 'align' => 'end'],
                        ['field' => 'subtotal', 'label' => 'Total', 'format' => 'rupiah', 'align' => 'end', 'sum' => true],
                        ['field' => 'status_label', 'label' => 'Status'],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'project' => true, 'keyword' => true],
                    'filters' => compact('dateFrom', 'dateTo', 'projectId', 'keyword'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            case 'activityLog':
                $model = new ActivityLog();
                $userId = trim($get['user_id'] ?? '');
                // Nama GET param sengaja 'log_module', BUKAN 'module' -- 'module' sudah dipakai
                // router untuk routing (?module=report), jadi tidak boleh dipakai ulang di sini.
                $module = trim($get['log_module'] ?? '');
                $filters = ['date_from' => $dateFrom, 'date_to' => $dateTo, 'user_id' => $userId, 'module' => $module];
                $rows = $model->listWithFilters($filters);
                // Ganti slug modul/aksi jadi label manusiawi (tabel & filter).
                foreach ($rows as &$logRow) {
                    $logRow['module'] = activityLogModuleLabel((string) $logRow['module']);
                    $logRow['action'] = activityLogActionLabel((string) $logRow['action']);
                }
                unset($logRow);
                $moduleFilterOptions = [];
                foreach ($model->distinctModules() as $slug) {
                    $moduleFilterOptions[$slug] = activityLogModuleLabel((string) $slug);
                }
                asort($moduleFilterOptions);
                $userModel = new User();
                return [
                    'title' => 'Riwayat Aktivitas (Audit Log)',
                    'columns' => [
                        ['field' => 'created_at', 'label' => 'Waktu', 'format' => 'datetime'],
                        ['field' => 'full_name', 'label' => 'User'],
                        ['field' => 'module', 'label' => 'Modul'],
                        ['field' => 'action', 'label' => 'Aksi'],
                        ['field' => 'description', 'label' => 'Deskripsi'],
                        ['field' => 'ip_address', 'label' => 'IP'],
                    ],
                    'rows' => $rows,
                    'filterForm' => ['date' => true, 'user' => $userModel->activeList(), 'module' => $moduleFilterOptions],
                    'filters' => compact('dateFrom', 'dateTo', 'userId', 'module'),
                    'exportQuery' => $this->buildExportQuery($get),
                ];

            default:
                return [
                    'title' => 'Laporan',
                    'columns' => [],
                    'rows' => [],
                    'filterForm' => [],
                    'filters' => [],
                    'exportQuery' => '',
                ];
        }
    }

    /**
     * Definisi kolom "Laporan Rekap PO - Detail Barang" (Gambar 1 referensi) --
     * dipakai BARENG oleh exportPoDetail() (.xlsx) & printPoDetail() (PDF)
     * supaya kedua format selalu konsisten kalau kolomnya berubah.
     */
    private function poDetailColumns(): array
    {
        return [
            ['field' => 'po_date', 'label' => 'Tanggal', 'format' => 'date', 'width' => 16],
            ['field' => 'po_number', 'label' => 'No PO', 'width' => 20],
            ['field' => 'supplier_name', 'label' => 'Supplier', 'width' => 22],
            ['field' => 'kode_barang', 'label' => 'Kode Barang', 'width' => 14],
            ['field' => 'item_name', 'label' => 'Nama Barang', 'width' => 32],
            ['field' => 'qty_order', 'label' => 'Qty', 'format' => 'number', 'align' => 'end', 'width' => 10],
            ['field' => 'unit', 'label' => 'Satuan', 'width' => 12],
            ['field' => 'price', 'label' => 'Harga Satuan', 'format' => 'rupiah', 'align' => 'end', 'width' => 16],
            ['field' => 'subtotal', 'label' => 'Total', 'format' => 'rupiah', 'align' => 'end', 'width' => 16, 'sum' => true],
            ['field' => 'pembuat_po', 'label' => 'Pembuat PO', 'width' => 18],
        ];
    }

    /**
     * Definisi kolom "Laporan Rekap PO - Rekap Pembayaran" (Gambar 2 referensi) --
     * dipakai BARENG oleh exportPoRecap() (.xlsx) & printPoRecap() (PDF).
     */
    private function poRecapColumns(): array
    {
        // Laporan PO REKAP: 1 baris per PO, fokus nilai & pembayaran (BUKAN barang).
        // Urutan WAJIB (Revisi 8 #1): No, Tanggal, Supplier, Nilai PO, Total Dibayar,
        // Sisa Belum Dibayar, % Belum Bayar.
        return [
            ['field' => 'po_date', 'label' => 'Tanggal', 'format' => 'date', 'width' => 16],
            ['field' => 'supplier_name', 'label' => 'Supplier', 'width' => 22],
            ['field' => 'nilai_po', 'label' => 'Nilai PO', 'format' => 'rupiah', 'align' => 'end', 'width' => 18, 'sum' => true],
            ['field' => 'total_dibayar', 'label' => 'Total Dibayar', 'format' => 'rupiah', 'align' => 'end', 'width' => 18, 'sum' => true],
            ['field' => 'sisa_belum_dibayar', 'label' => 'Sisa Belum Dibayar', 'format' => 'rupiah', 'align' => 'end', 'width' => 20, 'sum' => true],
            ['field' => 'pct_belum_dibayar', 'label' => '% Belum Bayar', 'format' => 'percent', 'align' => 'end', 'width' => 14],
        ];
    }

    private function poReportFilters(array $get): array
    {
        return [
            'date_from'  => trim($get['date_from'] ?? ''),
            'date_to'    => trim($get['date_to'] ?? ''),
            'project_id' => trim($get['project_id'] ?? ''),
            'status'     => trim($get['status'] ?? ''),
            'keyword'    => trim($get['keyword'] ?? ''),
        ];
    }

    /**
     * Nama perusahaan (Pengaturan > Profil Perusahaan) + baris periode -- dipakai
     * BARENG oleh streamPoRecapExcel() & streamPoRecapPdf() supaya header
     * "Laporan Rekap PO" selalu identik di semua format export.
     */
    private function poRecapHeaderMeta(array $filters): array
    {
        $company = (new SystemSetting())->getGroup('company');
        $companyName = $company['company_name'] ?: 'Perusahaan';

        $periodText = 'Periode : '
            . (!empty($filters['date_from']) ? formatTanggal($filters['date_from']) : 'Awal')
            . ' - '
            . (!empty($filters['date_to']) ? formatTanggal($filters['date_to']) : 'Sekarang');

        return [$companyName, $periodText];
    }

    /**
     * Bungkus streamExcelReport() dengan judul "Laporan Rekap PO" + header
     * perusahaan/periode -- dipakai bareng oleh exportPoDetail() & exportPoRecap()
     * supaya header di kedua file .xlsx selalu identik.
     */
    private function streamPoRecapExcel(array $filters, array $columns, array $rows, string $filenamePrefix): void
    {
        [$companyName, $periodText] = $this->poRecapHeaderMeta($filters);
        streamExcelReport('Laporan Rekap PO', $companyName, $periodText, $columns, $rows, $filenamePrefix . '_' . date('Ymd_His'));
    }

    /**
     * Versi PDF dari streamPoRecapExcel() -- header (judul/perusahaan/periode)
     * & data SAMA, cuma dirender lewat _po_recap_pdf.php + Dompdf (streamPdf(),
     * sama seperti exportPdf() generik) bukan PhpSpreadsheet.
     */
    private function streamPoRecapPdf(array $filters, array $columns, array $rows, string $filenamePrefix): void
    {
        [$companyName, $periodText] = $this->poRecapHeaderMeta($filters);

        ob_start();
        $title = 'Laporan Rekap PO';
        require ROOT_PATH . '/app/views/report/_po_recap_pdf.php';
        $html = ob_get_clean();

        streamPdf($html, $filenamePrefix . '_' . date('Ymd_His'));
    }

    private function buildExportQuery(array $get): string
    {
        $params = $get;
        // 'stock_type' = filter Kategori Laporan Stok Barang: sengaja TIDAK ikut
        // ke tombol Cetak/Export supaya cetak & ekspor selalu lengkap semua jenis stok.
        unset($params['module'], $params['action'], $params['type'], $params['stock_type']);
        $query = http_build_query($params);
        return $query ? '&' . $query : '';
    }
}
