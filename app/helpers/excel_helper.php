<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Helper export Excel BERGAYA (modul Laporan) -- beda dari exportCsv() generik
 * (ReportController) yang cuma dump text/csv polos. Ini bikin file .xlsx ASLI
 * (judul + subjudul + periode di atas tabel, header kolom bold+border, sel
 * angka/rupiah/persen numerik asli bukan string) via PhpSpreadsheet, supaya
 * hasil unduhan langsung siap pakai tanpa perlu dirapikan manual dulu --
 * sesuai contoh "Laporan Rekap PO" yang diminta user.
 *
 * @param string $title       Judul laporan (baris 1, dicetak tebal oranye)
 * @param string $companyName Nama perusahaan (baris 2, biru)
 * @param string $periodText  Teks periode, mis. "Periode : 01 Agustus 2026 - 31 Agustus 2026" (baris 3, biru)
 * @param array  $columns     Definisi kolom SETELAH kolom "No" bawaan:
 *                             ['field'=>.., 'label'=>.., 'format'=>'text|date|number|rupiah|percent',
 *                              'align'=>'end' (opsional), 'width'=>int (opsional)]
 * @param array  $rows        Baris data, array asosiatif per baris (key = 'field' di atas)
 * @param string $filename    Nama file unduhan, TANPA ekstensi
 */
function streamExcelReport(string $title, string $companyName, string $periodText, array $columns, array $rows, string $filename): void
{
    $colCount = count($columns) + 1; // +1 kolom "No"
    $lastColLetter = Coordinate::stringFromColumnIndex($colCount);

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // --- 3 baris judul, merge selebar tabel ---
    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->setCellValue('A1', $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('C55A11');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->mergeCells("A2:{$lastColLetter}2");
    $sheet->setCellValue('A2', $companyName);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('0070C0');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->mergeCells("A3:{$lastColLetter}3");
    $sheet->setCellValue('A3', $periodText);
    $sheet->getStyle('A3')->getFont()->setSize(11)->getColor()->setRGB('0070C0');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- Header kolom ---
    $headerRow = 5;
    $sheet->setCellValue('A' . $headerRow, 'No');
    $sheet->getColumnDimension('A')->setWidth(5);

    $colIndex = 2;
    foreach ($columns as $col) {
        $letter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($letter . $headerRow, $col['label']);
        $sheet->getColumnDimension($letter)->setWidth($col['width'] ?? 18);
        $colIndex++;
    }

    $headerRange = "A{$headerRow}:{$lastColLetter}{$headerRow}";
    $sheet->getStyle($headerRange)->getFont()->setBold(true);
    $sheet->getStyle($headerRange)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

    // --- Baris data ---
    $rowNum = $headerRow + 1;
    foreach ($rows as $i => $row) {
        $sheet->setCellValue('A' . $rowNum, $i + 1);
        $sheet->getStyle('A' . $rowNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $colIndex = 2;
        foreach ($columns as $col) {
            $letter = Coordinate::stringFromColumnIndex($colIndex);
            $cell = $letter . $rowNum;
            $value = $row[$col['field']] ?? null;

            switch ($col['format'] ?? 'text') {
                case 'date':
                    $sheet->setCellValue($cell, $value ? formatTanggal($value) : '-');
                    break;
                case 'datetime':
                    $sheet->setCellValue($cell, $value ? formatTanggal(substr((string) $value, 0, 10)) . ' ' . substr((string) $value, 11, 5) : '-');
                    break;
                case 'number':
                    $sheet->setCellValue($cell, $value !== null ? (float) $value : 0);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
                    break;
                case 'rupiah':
                    $sheet->setCellValue($cell, $value !== null ? (float) $value : 0);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"Rp" #,##0');
                    break;
                case 'percent':
                    $sheet->setCellValue($cell, $value !== null ? (float) $value : 0);
                    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('0.00"%"');
                    break;
                default:
                    $sheet->setCellValue($cell, $value !== null && $value !== '' ? $value : '-');
            }

            if (($col['align'] ?? '') === 'end') {
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $colIndex++;
        }
        $rowNum++;
    }

    // --- Baris TOTAL: SUM tiap kolom nominal (format 'rupiah') dari $rows yang
    // sama persis dengan yang sudah ditampilkan (sudah kena filter aktif) --
    // TIDAK query ulang ke DB, supaya SUM selalu match dengan isi tabel di atasnya.
    $sumCols = array_filter($columns, fn($c) => !empty($c['sum']));
    if (!empty($sumCols) && !empty($rows)) {
        $totalRow = $rowNum;
        $sheet->setCellValue('A' . $totalRow, 'TOTAL');
        $sheet->getStyle('A' . $totalRow)->getFont()->setBold(true);

        $colIndex = 2;
        foreach ($columns as $col) {
            $letter = Coordinate::stringFromColumnIndex($colIndex);
            if (!empty($col['sum'])) {
                $sum = array_sum(array_map(fn($r) => (float) ($r[$col['field']] ?? 0), $rows));
                $cell = $letter . $totalRow;
                $sheet->setCellValue($cell, $sum);
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('"Rp" #,##0');
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $colIndex++;
        }
        $sheet->getStyle("A{$totalRow}:{$lastColLetter}{$totalRow}")
            ->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
        $rowNum++;
    }

    $lastDataRow = max($rowNum - 1, $headerRow);
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$lastDataRow}")
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

    $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->setTitle('Laporan');

    // --- Notes "[tgl jam WIB], [user cetak]" di POJOK KANAN BAWAH: pakai footer
    // cetak asli Excel (muncul di dasar SETIAP halaman saat di-print) + tetap
    // ditulis sebagai baris kecil rata kanan supaya kelihatan juga waktu file
    // cuma dibuka (tidak dicetak). ---
    _excelPrintFooter($sheet);

    $noteRow = $lastDataRow + 3;
    $sheet->mergeCells("A{$noteRow}:{$lastColLetter}{$noteRow}");
    $sheet->setCellValue('A' . $noteRow, printedAtLabel() . ', ' . printedByLabel());
    $sheet->getStyle('A' . $noteRow)->getFont()->setSize(8)->getColor()->setRGB('999999');
    $sheet->getStyle('A' . $noteRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

/**
 * ============================================================
 * EXPORT EXCEL LAPORAN STOK BARANG -- Detail & Rekap (Revisi 8 #9-#13).
 *
 * Struktur, kolom, grouping, & angka WAJIB sama persis dengan cetak PDF-nya
 * (app/views/report/_stock_detail_print.php & _stock_recap_print.php). Data
 * masuk lewat parameter $groups/$rows yang datang dari method model yang SAMA
 * dipakai PDF (Inventory::stockDetailReport()/stockRecapReport()), dengan
 * $filters yang identik -- jadi filter periode/barang/"Stok != 0"/dll otomatis
 * konsisten antara PDF & Excel, tidak ada query terpisah di sini.
 * ============================================================
 */

/** Qty numerik untuk sel Excel: bilangan bulat tanpa desimal, selain itu 2 desimal
 *  -- mencerminkan formatQtyPrint() di kedua template cetak PDF stok. */
function _stockExcelQty($sheet, string $cell, $value): void
{
    $sheet->setCellValue($cell, $value !== null ? (float) $value : 0);
    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.###');
    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

/** 3 baris judul (judul/perusahaan/periode) di atas tabel -- identik gaya dengan
 *  streamExcelReport(). Return nomor baris header tabel (baris 5). */
function _stockExcelTitleBlock($sheet, string $title, string $companyName, string $periodText, string $lastColLetter): int
{
    $sheet->mergeCells("A1:{$lastColLetter}1");
    $sheet->setCellValue('A1', $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('C55A11');
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->mergeCells("A2:{$lastColLetter}2");
    $sheet->setCellValue('A2', $companyName);
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('0070C0');
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->mergeCells("A3:{$lastColLetter}3");
    $sheet->setCellValue('A3', $periodText);
    $sheet->getStyle('A3')->getFont()->setSize(11)->getColor()->setRGB('0070C0');
    $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    return 5;
}

/**
 * Notes "[tgl jam WIB], [user cetak]" di POJOK KANAN BAWAH:
 * - footer cetak asli Excel (dasar SETIAP halaman saat di-print)
 * - + satu baris kecil rata kanan supaya kelihatan juga waktu file dibuka biasa.
 */
function _stockExcelNotes($sheet, int $startRow, string $lastColLetter): void
{
    _excelPrintFooter($sheet);

    $sheet->mergeCells("A{$startRow}:{$lastColLetter}{$startRow}");
    $sheet->setCellValue('A' . $startRow, printedAtLabel() . ', ' . printedByLabel());
    $sheet->getStyle('A' . $startRow)->getFont()->setSize(8)->getColor()->setRGB('999999');
    $sheet->getStyle('A' . $startRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

/** Set footer cetak asli Excel: bagian kanan (&R) berisi notes tanggal/jam/user. */
function _excelPrintFooter($sheet): void
{
    $text = str_replace('&', '&&', printedAtLabel() . ', ' . printedByLabel());
    $sheet->getHeaderFooter()->setOddFooter('&R&8&K808080' . $text);
    $sheet->getHeaderFooter()->setEvenFooter('&R&8&K808080' . $text);
}

function _stockExcelStream(Spreadsheet $spreadsheet, string $filename): void
{
    $spreadsheet->getActiveSheet()->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

/**
 * Excel DETAIL Stok -- cerminan app/views/report/_stock_detail_print.php:
 * per barang, satu baris per transaksi mutasi, kolom Saldo Awal / In / Out /
 * Saldo Akhir masing-masing (Qty + Satuan), lalu baris ringkasan "Saldo Akhir".
 *
 * @param array $groups Hasil Inventory::stockDetailReport() -- tiap elemen:
 *              item_code, item_name, unit, project_name, saldo_akhir, lines[]
 *              (transaction_date, no_bukti, saldo_awal, in_qty, out_qty, saldo_akhir)
 */
function streamStockDetailExcel(array $groups, string $companyName, string $periodText, string $filename): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Detail Stok');

    $lastColLetter = 'L'; // 12 kolom: 4 info + 4x(Qty,Satuan)
    $headerRow = _stockExcelTitleBlock($sheet, 'Laporan Stok Barang - Detail', $companyName, $periodText, $lastColLetter);
    $sub = $headerRow + 1;

    // Header 2 baris (mengikuti thead PDF Detail)
    foreach (['A' => 'Tanggal', 'B' => 'No Bukti', 'C' => 'Kode Barang', 'D' => 'Nama Barang'] as $col => $label) {
        $sheet->setCellValue($col . $headerRow, $label);
        $sheet->mergeCells("{$col}{$headerRow}:{$col}{$sub}");
    }
    $pairs = ['E' => 'Saldo Awal', 'G' => 'In', 'I' => 'Out', 'K' => 'Saldo Akhir'];
    foreach ($pairs as $startCol => $label) {
        $endCol = chr(ord($startCol) + 1);
        $sheet->setCellValue($startCol . $headerRow, $label);
        $sheet->mergeCells("{$startCol}{$headerRow}:{$endCol}{$headerRow}");
        $sheet->setCellValue($startCol . $sub, 'Qty');
        $sheet->setCellValue($endCol . $sub, 'Satuan');
    }
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$sub}")->getFont()->setBold(true);
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$sub}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    foreach (['A' => 16, 'B' => 16, 'C' => 15, 'D' => 34, 'E' => 12, 'F' => 9, 'G' => 12, 'H' => 9, 'I' => 12, 'J' => 9, 'K' => 12, 'L' => 9] as $c => $w) {
        $sheet->getColumnDimension($c)->setWidth($w);
    }

    $row = $sub + 1;
    $firstDataRow = $row;
    foreach ($groups as $g) {
        foreach ($g['lines'] as $line) {
            $sheet->setCellValue('A' . $row, formatTanggal(substr((string) $line['transaction_date'], 0, 10)));
            $sheet->setCellValue('B' . $row, $line['no_bukti'] !== '' ? $line['no_bukti'] : '-');
            $sheet->setCellValue('C' . $row, $g['item_code'] ?: '-');
            $sheet->setCellValue('D' . $row, $g['item_name']);
            _stockExcelQty($sheet, 'E' . $row, $line['saldo_awal']);
            $sheet->setCellValue('F' . $row, $g['unit']);
            if ((float) $line['in_qty'] > 0) {
                _stockExcelQty($sheet, 'G' . $row, $line['in_qty']);
                $sheet->setCellValue('H' . $row, $g['unit']);
            }
            if ((float) $line['out_qty'] > 0) {
                _stockExcelQty($sheet, 'I' . $row, $line['out_qty']);
                $sheet->setCellValue('J' . $row, $g['unit']);
            }
            _stockExcelQty($sheet, 'K' . $row, $line['saldo_akhir']);
            $sheet->setCellValue('L' . $row, $g['unit']);
            $row++;
        }
        // Baris ringkasan "Saldo Akhir" per barang (mengikuti summary-row PDF Detail)
        $sheet->setCellValue('A' . $row, 'Saldo Akhir - ' . ($g['item_code'] ?: $g['item_name']));
        $sheet->mergeCells("A{$row}:J{$row}");
        $sheet->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        _stockExcelQty($sheet, 'K' . $row, $g['saldo_akhir']);
        $sheet->setCellValue('L' . $row, $g['unit']);
        $row++;
    }
    if ($row === $firstDataRow) {
        $sheet->setCellValue('A' . $row, 'Tidak ada mutasi stok pada periode ini.');
        $sheet->mergeCells("A{$row}:L{$row}");
        $row++;
    }

    $lastDataRow = $row - 1;
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$lastDataRow}")
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    _stockExcelNotes($sheet, $lastDataRow + 3, $lastColLetter);

    _stockExcelStream($spreadsheet, $filename);
}

/**
 * ============================================================
 * EXPORT EXCEL LAPORAN KAS (Revisi 9) -- format buku kas sesuai contoh user:
 * Tgl | No Bukti | Uraian | Qty | Satuan | Masuk | Keluar | Saldo Akhir,
 * dengan baris "Saldo Awal" di atas dan "Saldo Akhir" di bawah.
 *
 * @param array $ledger Hasil CashTransaction::reportLedger():
 *              ['saldo_awal'=>float, 'saldo_akhir'=>float, 'rows'=>[
 *                 ['trx_date','no_bukti','uraian','qty','satuan','masuk','keluar','saldo'], ...]]
 * ============================================================
 */
function streamCashReportExcel(array $ledger, string $companyName, string $periodText, string $filename): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Laporan Kas');

    $lastColLetter = 'H'; // 8 kolom
    $headerRow = _stockExcelTitleBlock($sheet, 'Laporan Kas', $companyName, $periodText, $lastColLetter);

    $labels = ['A' => 'Tgl', 'B' => 'No Bukti', 'C' => 'Uraian', 'D' => 'Qty', 'E' => 'Satuan', 'F' => 'Masuk', 'G' => 'Keluar', 'H' => 'Saldo Akhir'];
    foreach ($labels as $col => $label) {
        $sheet->setCellValue($col . $headerRow, $label);
    }
    foreach (['A' => 12, 'B' => 16, 'C' => 40, 'D' => 10, 'E' => 15, 'F' => 16, 'G' => 16, 'H' => 18] as $c => $w) {
        $sheet->getColumnDimension($c)->setWidth($w);
    }
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->getFont()->setBold(true);
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$headerRow}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $money = '#,##0';
    $row = $headerRow + 1;

    // Saldo Awal
    $sheet->setCellValue('A' . $row, 'Saldo Awal');
    $sheet->mergeCells("A{$row}:G{$row}");
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->setCellValue('H' . $row, (float) $ledger['saldo_awal']);
    $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode($money);
    $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
    $row++;

    foreach ($ledger['rows'] as $r) {
        $sheet->setCellValue('A' . $row, $r['trx_date'] !== '' ? date('j-M-y', strtotime($r['trx_date'])) : '');
        $sheet->setCellValue('B' . $row, $r['no_bukti']);
        $sheet->setCellValue('C' . $row, $r['uraian']);
        $sheet->setCellValue('D' . $row, (float) $r['qty']);
        $sheet->setCellValue('E' . $row, (float) $r['satuan']);
        if ((float) $r['masuk'] > 0) {
            $sheet->setCellValue('F' . $row, (float) $r['masuk']);
        }
        if ((float) $r['keluar'] > 0) {
            $sheet->setCellValue('G' . $row, (float) $r['keluar']);
        }
        $sheet->setCellValue('H' . $row, (float) $r['saldo']);
        $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0.##');
        $sheet->getStyle("E{$row}:H{$row}")->getNumberFormat()->setFormatCode($money);
        $row++;
    }

    // Saldo Akhir
    $sheet->setCellValue('A' . $row, 'Saldo Akhir');
    $sheet->mergeCells("A{$row}:G{$row}");
    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->setCellValue('H' . $row, (float) $ledger['saldo_akhir']);
    $sheet->getStyle('H' . $row)->getNumberFormat()->setFormatCode($money);
    $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);

    $lastDataRow = $row;
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$lastDataRow}")
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    _stockExcelNotes($sheet, $lastDataRow + 3, $lastColLetter);

    _stockExcelStream($spreadsheet, $filename);
}

/**
 * Excel REKAP Stok -- cerminan app/views/report/_stock_recap_print.php:
 * satu baris per barang, ringkasan Saldo Awal / In (total masuk) / Out (total
 * keluar) / Saldo Akhir (masing-masing Qty + Satuan).
 *
 * @param array $rows Hasil Inventory::stockRecapReport() -- item_code, item_name,
 *              unit, saldo_awal, mutasi_masuk, mutasi_keluar, saldo_akhir
 */
function streamStockRecapExcel(array $rows, string $companyName, string $periodText, string $filename): void
{
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rekap Stok');

    $lastColLetter = 'K'; // 11 kolom: No + Kode + Nama + 4x(Qty,Satuan)
    $headerRow = _stockExcelTitleBlock($sheet, 'Laporan Stok Barang - Rekap', $companyName, $periodText, $lastColLetter);
    $sub = $headerRow + 1;

    foreach (['A' => 'No', 'B' => 'Kode Barang', 'C' => 'Nama Barang'] as $col => $label) {
        $sheet->setCellValue($col . $headerRow, $label);
        $sheet->mergeCells("{$col}{$headerRow}:{$col}{$sub}");
    }
    $pairs = ['D' => 'Saldo Awal', 'F' => 'In', 'H' => 'Out', 'J' => 'Saldo Akhir'];
    foreach ($pairs as $startCol => $label) {
        $endCol = chr(ord($startCol) + 1);
        $sheet->setCellValue($startCol . $headerRow, $label);
        $sheet->mergeCells("{$startCol}{$headerRow}:{$endCol}{$headerRow}");
        $sheet->setCellValue($startCol . $sub, 'Qty');
        $sheet->setCellValue($endCol . $sub, 'Satuan');
    }
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$sub}")->getFont()->setBold(true);
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$sub}")->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    foreach (['A' => 6, 'B' => 15, 'C' => 36, 'D' => 12, 'E' => 9, 'F' => 12, 'G' => 9, 'H' => 12, 'I' => 9, 'J' => 12, 'K' => 9] as $c => $w) {
        $sheet->getColumnDimension($c)->setWidth($w);
    }

    $row = $sub + 1;
    if (empty($rows)) {
        $sheet->setCellValue('A' . $row, 'Tidak ada data barang yang cocok dengan filter ini.');
        $sheet->mergeCells("A{$row}:K{$row}");
        $row++;
    }
    foreach ($rows as $i => $r) {
        $sheet->setCellValue('A' . $row, $i + 1);
        $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('B' . $row, $r['item_code'] ?: '-');
        $sheet->setCellValue('C' . $row, $r['item_name']);
        _stockExcelQty($sheet, 'D' . $row, $r['saldo_awal']);
        $sheet->setCellValue('E' . $row, $r['unit']);
        _stockExcelQty($sheet, 'F' . $row, $r['mutasi_masuk']);
        $sheet->setCellValue('G' . $row, $r['unit']);
        _stockExcelQty($sheet, 'H' . $row, $r['mutasi_keluar']);
        $sheet->setCellValue('I' . $row, $r['unit']);
        _stockExcelQty($sheet, 'J' . $row, $r['saldo_akhir']);
        $sheet->setCellValue('K' . $row, $r['unit']);
        $row++;
    }

    $lastDataRow = $row - 1;
    $sheet->getStyle("A{$headerRow}:{$lastColLetter}{$lastDataRow}")
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    _stockExcelNotes($sheet, $lastDataRow + 3, $lastColLetter);

    _stockExcelStream($spreadsheet, $filename);
}
