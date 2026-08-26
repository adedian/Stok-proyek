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

    // --- Notes kecil pojok kanan bawah: tanggal/jam cetak (WIB) + user login ---
    $noteRow = $lastDataRow + 3;
    $sheet->mergeCells("A{$noteRow}:{$lastColLetter}{$noteRow}");
    $sheet->setCellValue('A' . $noteRow, 'Tanggal & Jam: ' . printedAtLabel());
    $sheet->getStyle('A' . $noteRow)->getFont()->setSize(8)->getColor()->setRGB('999999');
    $sheet->getStyle('A' . $noteRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $noteRow2 = $noteRow + 1;
    $sheet->mergeCells("A{$noteRow2}:{$lastColLetter}{$noteRow2}");
    $sheet->setCellValue('A' . $noteRow2, 'Dicetak oleh: ' . printedByLabel());
    $sheet->getStyle('A' . $noteRow2)->getFont()->setSize(8)->getColor()->setRGB('999999');
    $sheet->getStyle('A' . $noteRow2)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
