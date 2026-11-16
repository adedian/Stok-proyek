<?php

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * EXPORT EXCEL "PERMINTAAN OTORISASI" -- Laporan Pengajuan Budget.
 *
 * Isi identik dengan layar/cetak (data dari PengajuanBudget::buildReport()):
 *   kolom No | Rekening Sumber | Jenis Transaksi | Rekening Tujuan | Jumlah Pembayaran | Keterangan | Kategori,
 *   dikelompokkan per rekening sumber (warna dari Master Bank), nomor urut mulai 1 per kelompok,
 *   TOTAL per kelompok (rumus SUM), TOTAL KESELURUHAN (rumus), blok "Saldo ... tgl." (saldo aktual Bank)
 *   dan "Sisa Saldo setelah pembayaran" (rumus saldo - pengajuan yang belum direalisasikan) + catatan rekening.
 *
 * @param array  $report  hasil PengajuanBudget::buildReport()
 * @param string $periodLabel mis. "TGL. 29 Oktober 2026"
 * @param string $filterNote  ringkasan filter non-tanggal
 * @param string $filename    nama file TANPA ekstensi
 */
function streamPermintaanOtorisasi(array $report, string $periodLabel, string $filterNote, string $filename): void
{
    $hasCents = false;
    foreach ($report['groups'] as $g) {
        foreach ($g['rows'] as $r) {
            if (fmod((float) $r['amount'], 1) != 0.0) {
                $hasCents = true;
            }
        }
    }
    $nf = $hasCents ? '#,##0.00;[Red]-#,##0.00' : '#,##0;[Red]-#,##0';
    $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
    $argb = fn(string $hex) => ltrim($hex, '#');

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Permintaan Otorisasi');
    $sheet->setShowGridlines(false);
    foreach (['A' => 5, 'B' => 34, 'C' => 17, 'D' => 42, 'E' => 20, 'F' => 46, 'G' => 24, 'H' => 24] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
    $sheet->getParent()->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

    $sheet->setCellValue('A1', 'PERMINTAAN OTORISASI');
    $sheet->setCellValue('A2', $periodLabel);
    $sheet->setCellValue('A3', $filterNote);
    $sheet->getStyle('A1:A2')->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A3')->getFont()->setSize(8)->getColor()->setRGB('666666');

    // Header
    $hr = 5;
    foreach (['A' => 'No', 'B' => 'Rekening Sumber', 'C' => 'Jenis Transaksi', 'D' => 'Rekening Tujuan', 'E' => 'Jumlah Pembayaran', 'F' => 'Keterangan', 'G' => 'Kategori', 'H' => 'Project'] as $c => $label) {
        $sheet->setCellValue($c . $hr, $label);
    }
    $sheet->getStyle("A{$hr}:H{$hr}")->applyFromArray($thin);
    $sheet->getStyle("A{$hr}:H{$hr}")->getFont()->setBold(true);
    $sheet->getStyle("A{$hr}:H{$hr}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B4E5A2');
    $sheet->getStyle("A{$hr}:H{$hr}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $lines = fn(string $s, int $perLine) => max(1, (int) ceil(mb_strlen($s) / $perLine));
    $r = $hr + 1;
    $totalRows = [];
    foreach ($report['groups'] as $g) {
        $first = $r;
        foreach ($g['rows'] as $row) {
            $sheet->setCellValue("A{$r}", (int) $row['no']);
            $sheet->setCellValueExplicit("B{$r}", (string) $g['label'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("C{$r}", (string) $row['jenis_transaksi'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$r}", (string) $row['tujuan'], DataType::TYPE_STRING);
            $sheet->setCellValue("E{$r}", (float) $row['amount']);
            $sheet->setCellValueExplicit("F{$r}", (string) ($row['keterangan'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("G{$r}", (string) ($row['category_name'] ?? ''), DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("H{$r}", (string) ($row['project_name'] ?? ''), DataType::TYPE_STRING);
            $sheet->getStyle("A{$r}:H{$r}")->applyFromArray($thin);
            $sheet->getStyle("A{$r}:H{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
            $sheet->getStyle("A{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($nf);
            $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            foreach (['B', 'C'] as $cc) {
                $sheet->getStyle("{$cc}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($argb($g['color']));
                $sheet->getStyle("{$cc}{$r}")->getFont()->getColor()->setRGB($argb($g['text_color']));
            }
            $sheet->getRowDimension($r)->setRowHeight(max(15, 13.5 * max($lines((string) $row['keterangan'], 55), $lines((string) $row['tujuan'], 42), $lines($g['label'], 32))));
            $r++;
        }
        $last = $r - 1;
        $sheet->setCellValue("D{$r}", 'TOTAL');
        $sheet->setCellValue("E{$r}", "=SUM(E{$first}:E{$last})");
        $sheet->getStyle("B{$r}:H{$r}")->applyFromArray($thin);
        $sheet->getStyle("B{$r}:H{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($argb($g['color']));
        $sheet->getStyle("B{$r}:H{$r}")->getFont()->setBold(true)->getColor()->setRGB($argb($g['text_color']));
        $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($nf);
        $totalRows[] = $r;
        $r += 2; // satu baris kosong antar kelompok
    }

    // TOTAL KESELURUHAN
    $grandRow = $r;
    $sheet->setCellValue("D{$grandRow}", 'TOTAL KESELURUHAN');
    $sheet->setCellValue("E{$grandRow}", $totalRows ? '=' . implode('+', array_map(fn($x) => "E{$x}", $totalRows)) : 0);
    $sheet->getStyle("D{$grandRow}:E{$grandRow}")->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM]]]);
    $sheet->getStyle("D{$grandRow}:E{$grandRow}")->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle("D{$grandRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle("E{$grandRow}")->getNumberFormat()->setFormatCode($nf);
    $r += 2;

    // Blok saldo + sisa saldo
    $saldoRows = [];
    if ($report['saldo']) {
        $dLabel = date('d/m/Y', strtotime($report['saldo_date']));
        foreach ($report['saldo'] as $i => $s) {
            $sheet->setCellValueExplicit("D{$r}", 'Saldo ' . $s['bank_name'] . ' tgl. ' . $dLabel, DataType::TYPE_STRING);
            $sheet->setCellValue("E{$r}", (float) $s['saldo']);
            $sheet->getStyle("D{$r}:E{$r}")->applyFromArray($thin);
            $sheet->getStyle("D{$r}:E{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($argb($s['color']));
            $sheet->getStyle("D{$r}")->getFont()->getColor()->setRGB($argb($s['text_color']));
            $sheet->getStyle("E{$r}")->getFont()->setBold(true)->getColor()->setRGB('C00000');
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($nf);
            if ($s['note'] !== '') {
                $sheet->mergeCells("F{$r}:H{$r}");
                $sheet->setCellValueExplicit("F{$r}", (string) $s['note'], DataType::TYPE_STRING);
                $sheet->getStyle("F{$r}:H{$r}")->applyFromArray($thin);
                $sheet->getStyle("F{$r}:H{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($argb($s['color']));
                $sheet->getStyle("F{$r}")->getFont()->setSize(9)->getColor()->setRGB($argb($s['text_color']));
            }
            $saldoRows[$i] = $r;
            $r++;
        }
        $r++;
        $sheet->setCellValue("D{$r}", 'Sisa Saldo setelah pembayaran');
        $sheet->getStyle("D{$r}")->getFont()->setBold(true)->setSize(12);
        $r++;
        foreach ($report['saldo'] as $i => $s) {
            $sheet->setCellValueExplicit("D{$r}", 'Saldo ' . $s['bank_name'] . ' tgl. ' . $dLabel, DataType::TYPE_STRING);
            // rumus: saldo aktual - pengajuan yang belum direalisasikan (nilai tetap saat export)
            $sheet->setCellValue("E{$r}", '=E' . $saldoRows[$i] . '-' . number_format((float) $s['outstanding'], 2, '.', ''));
            $sheet->getStyle("D{$r}:E{$r}")->applyFromArray($thin);
            $sheet->getStyle("D{$r}:E{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($argb($s['color']));
            $sheet->getStyle("D{$r}")->getFont()->getColor()->setRGB($argb($s['text_color']));
            $sheet->getStyle("E{$r}")->getFont()->setBold(true)->getColor()->setRGB('C00000');
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode($nf);
            if ($s['realized'] > 0) {
                $sheet->setCellValueExplicit("F{$r}", 'Rp ' . number_format((float) $s['realized'], 0, '.', ',') . ' sudah direalisasikan (tercermin di saldo aktual, tidak dikurangkan lagi)', DataType::TYPE_STRING);
                $sheet->getStyle("F{$r}")->getFont()->setSize(8)->getColor()->setRGB('666666');
            }
            $r++;
        }
    }

    $r++;
    $sheet->setCellValue("H{$r}", printedAtLabel() . ', ' . printedByLabel());
    $sheet->getStyle("H{$r}")->getFont()->setSize(8)->getColor()->setRGB('999999');
    $sheet->getStyle("H{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $ps = $sheet->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
    $ps->setFitToWidth(1)->setFitToHeight(0);
    $ps->setFitToPage(true);
    $ps->setPrintArea("A1:H{$r}");
    $ps->setRowsToRepeatAtTopByStartAndEnd($hr, $hr);
    $sheet->freezePane('A' . ($hr + 1));
    _excelPrintFooter($sheet);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}
