<?php
/**
 * ============================================================================
 *  TAMPILAN CETAK SEMENTARA -- "Cetak Stok Tersedia" (Laporan Stok Barang)
 * ============================================================================
 *  File INI SAJA yang perlu diganti saat template cetak final diberikan.
 *  Logika data/filter/hak akses ada di ReportController::printStockAvailable()
 *  dan Inventory::availableStockReport() -- TIDAK perlu diubah untuk memasang template.
 *
 *  Variabel yang tersedia:
 *    $rows         array  baris stok tersedia (> 0), urutan = Laporan Stok Barang. Tiap baris:
 *                         item_code, item_name, project_name, unit, saldo_akhir (= stok tersedia,
 *                         angka yang sama dengan layar laporan), min_stock, stock_type, id.
 *    $meta         array  company_name, printed_at ("12-10-2026 10:48 WIB"), printed_by,
 *                         filters [[label, nilai], ...], total_rows.
 *    $emptyMessage string pesan bila $rows kosong.
 *  Helper: e(), formatQtyPrint() (di bawah), printedAtLabel(), printedByLabel().
 *
 *  Kolom sementara mengikuti contoh "Laporan Stok Barang - Rekap": No, Kode Barang,
 *  Nama Barang, Stock Tersedia (Qty, Satuan) -- ditambah kolom Project agar barang yang sama
 *  di beberapa project tidak membingungkan. Ini BUKAN desain final.
 * ============================================================================
 */
if (!function_exists('formatQtyPrint')) {
    function formatQtyPrint($value): string
    {
        $value = (float) $value;
        if ($value == (int) $value) {
            return number_format($value, 0, ',', '.');
        }
        return number_format($value, 2, ',', '.');
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak Stok Tersedia (tampilan sementara)</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #212529; margin: 0; padding: 16px; }
        .temp-banner { background: #fff3cd; border: 1px solid #ffecb5; color: #664d03; padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; }
        .toolbar { margin-bottom: 12px; display: flex; gap: 8px; flex-wrap: wrap; }
        .toolbar button, .toolbar a { font-size: 13px; padding: 6px 12px; border: 1px solid #adb5bd; background: #fff; border-radius: 4px; cursor: pointer; color: #212529; text-decoration: none; }
        h2 { margin: 0 0 2px; font-size: 16px; text-align: center; }
        .company { text-align: center; margin-bottom: 8px; color: #0d6efd; font-weight: bold; }
        .filters { margin-bottom: 8px; color: #495057; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #adb5bd; padding: 4px 6px; text-align: left; }
        th { background: #f1f3f5; text-align: center; }
        td.num { text-align: right; }
        td.ctr { text-align: center; }
        .note { margin-top: 10px; text-align: right; font-size: 10px; color: #6c757d; }
        .empty { padding: 16px; text-align: center; color: #6c757d; border: 1px dashed #adb5bd; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            thead { display: table-header-group; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <!-- TEMPORARY: tampilan sementara sampai template final diberikan. Ganti seluruh file ini. -->
    <div class="temp-banner no-print">
        <strong>Tampilan sementara.</strong> Tata letak cetak ini belum final dan akan diganti dengan template yang disiapkan.
        Data (filter, angka stok, hak akses) sudah final dan sama dengan Laporan Stok Barang.
    </div>
    <div class="toolbar no-print">
        <button type="button" onclick="window.print()">Cetak</button>
        <a href="javascript:history.back()">Kembali</a>
    </div>

    <h2>Laporan Stok Barang - Stok Tersedia</h2>
    <div class="company"><?= e($meta['company_name']) ?></div>

    <?php if (!empty($meta['filters'])): ?>
        <div class="filters">
            Filter:
            <?php foreach ($meta['filters'] as $i => [$label, $value]): ?>
                <?= $i > 0 ? ' | ' : '' ?><strong><?= e($label) ?></strong>: <?= e($value) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <div class="empty"><?= e($emptyMessage) ?></div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th rowspan="2" style="width: 36px;">No</th>
                    <th rowspan="2">Kode Barang</th>
                    <th rowspan="2">Nama Barang</th>
                    <th rowspan="2">Project</th>
                    <th colspan="2">Stock Tersedia</th>
                </tr>
                <tr>
                    <th>Qty</th>
                    <th>Satuan</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr>
                        <td class="ctr"><?= $i + 1 ?></td>
                        <td><?= e($r['item_code'] ?: '-') ?></td>
                        <td><?= e($r['item_name']) ?></td>
                        <td><?= e($r['project_name'] ?? '-') ?></td>
                        <td class="num"><?= e(formatQtyPrint($r['saldo_akhir'])) ?></td>
                        <td><?= e($r['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="note"><?= e($meta['printed_at']) ?>, <?= e($meta['printed_by']) ?></div>
</body>
</html>
