<?php

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * EXPORT EXCEL "PERMINTAAN OTORISASI" (Pengajuan Pembayaran) -- Laporan Request Budget.
 *
 * Mengikuti template Excel dari Accounting ("Pengajuan Pembayaran.xlsx", sheet tahun):
 *   - Judul "PERMINTAAN OTORISASI" + "TGL. dd Bulan yyyy" (kolom B).
 *   - Kolom: B No | C Rekening Sumber | D Jenis Transaksi | E Rekening Tujuan |
 *     F Jumlah Pembayaran | (G,H tersembunyi seperti template) | I Keterangan | J Kategori.
 *   - Baris induk (bernomor) berisi Keterangan + Kategori (Project). Bila request punya >1 item,
 *     Jumlah induk = SUM(baris anak) dan tiap item jadi baris anak (kolom E + F).
 *   - Baris TOTAL kuning, kotak TOTAL, lalu blok saldo hijau (diisi manual oleh Accounting;
 *     "Sisa Saldo" otomatis = Saldo BCA - TOTAL begitu Saldo diisi).
 *   Rekening Sumber & Jenis Transaksi dikosongkan (diisi Accounting; tidak ada datanya di aplikasi).
 *
 * @param array  $blocks   [['keterangan','kategori','rekening','total','items'=>[['label','amount'],..]], ..]
 * @param string $dateText mis. "TGL. 01 Oktober 2026"
 * @param string $filename nama file TANPA ekstensi
 */
function streamPengajuanPembayaran(array $blocks, string $dateText, string $filename): void
{
    $nf = '_-* #,##0_-;\-* #,##0_-;_-* "-"??_-;_-@_-';
    $nfRed = '_(* #,##0_);_(* \(#,##0\);_(* "-"??_);_(@_)';
    $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(date('Y'));
    $sheet->setShowGridlines(false);

    foreach (['A' => 9.14, 'B' => 5.43, 'C' => 22.86, 'D' => 23.86, 'E' => 64.14, 'F' => 24, 'G' => 24, 'H' => 24, 'I' => 55.57, 'J' => 58.86] as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
    $sheet->getColumnDimension('G')->setVisible(false);
    $sheet->getColumnDimension('H')->setVisible(false);

    // --- Judul ---
    $sheet->setCellValue('B4', 'PERMINTAAN OTORISASI');
    $sheet->setCellValue('B5', $dateText);
    $sheet->getStyle('B4:B5')->getFont()->setName('Comic Sans MS')->setSize(11)->setBold(true);
    $sheet->getStyle('B4:B5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension(4)->setRowHeight(18);
    $sheet->getRowDimension(5)->setRowHeight(18);

    // --- Header kolom (baris 7) ---
    $hdr = ['B' => 'No', 'C' => 'Rekening Sumber', 'D' => 'Jenis Transaksi', 'E' => 'Rekening Tujuan', 'F' => 'Jumlah Pembayaran', 'I' => 'Keterangan', 'J' => 'Kategori'];
    foreach ($hdr as $col => $label) {
        $sheet->setCellValue($col . '7', $label);
    }
    $sheet->getStyle('B7:J7')->applyFromArray($thin);
    $sheet->getStyle('B7:J7')->getFont()->setName('Aptos Narrow')->setSize(12)->setBold(true);
    $sheet->getStyle('B7:J7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('B4E5A2');
    $sheet->getStyle('B7:J7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getRowDimension(7)->setRowHeight(15.75);

    // --- Data ---
    $dataStyle = function (int $r, bool $child = false) use ($sheet, $thin, $nf) {
        $sheet->getStyle("B{$r}:J{$r}")->applyFromArray($thin);
        $sheet->getStyle("B{$r}:J{$r}")->getFont()->setName('Aptos Narrow')->setSize(11);
        $sheet->getStyle("B{$r}:J{$r}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        $sheet->getStyle("I{$r}:J{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(true);
        $sheet->getStyle("F{$r}")->getNumberFormat()->setFormatCode($nf);
        if ($child) {
            $sheet->getStyle("F{$r}")->getFont()->setSize(10);
        }
    };
    $lines = fn(string $s, int $perLine) => max(1, (int) ceil(mb_strlen($s) / $perLine));

    $r = 8;
    $n = 0;
    foreach ($blocks as $b) {
        $n++;
        $items = $b['items'] ?? [];
        $dataStyle($r);
        $sheet->setCellValue("B{$r}", $n);
        $sheet->setCellValueExplicit("I{$r}", (string) $b['keterangan'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("J{$r}", (string) $b['kategori'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $h = max(15.75, 15 * max($lines((string) $b['keterangan'], 58), $lines((string) $b['kategori'], 60)));
        if (count($items) >= 2) {
            $sheet->setCellValueExplicit("E{$r}", (string) ($b['rekening'] ?? ''), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $k = count($items);
            $sheet->setCellValue("F{$r}", '=SUM(F' . ($r + 1) . ':F' . ($r + $k) . ')');
            $sheet->getStyle("F{$r}")->getFont()->setSize(10); // seperti template: subtotal induk berfont 10
            $sheet->getRowDimension($r)->setRowHeight($h);
            $r++;
            foreach ($items as $it) {
                $dataStyle($r, true);
                $sheet->setCellValueExplicit("E{$r}", (string) $it['label'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $sheet->setCellValue("F{$r}", (float) $it['amount']);
                $sheet->getRowDimension($r)->setRowHeight(max(15.75, 15 * $lines((string) $it['label'], 70)));
                $r++;
            }
        } else {
            $eText = ($b['rekening'] ?? '') !== '' ? (string) $b['rekening'] : (string) ($items[0]['label'] ?? '');
            $sheet->setCellValueExplicit("E{$r}", $eText, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->setCellValue("F{$r}", (float) $b['total']);
            $sheet->getRowDimension($r)->setRowHeight(max($h, 15 * $lines($eText, 70)));
            $r++;
        }
    }
    $lastData = max($r - 1, 8);

    // --- TOTAL (kuning) ---
    $tot = $r + 1;
    $sheet->setCellValue("E{$tot}", 'TOTAL');
    $sheet->setCellValue("F{$tot}", '=SUMIF(B8:B' . $lastData . ',">0",F8:F' . $lastData . ')');
    $sheet->getStyle("E{$tot}:F{$tot}")->getFont()->setName('Aptos Narrow')->setSize(12)->setBold(true);
    $sheet->getStyle("E{$tot}:F{$tot}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
    $sheet->getStyle("E{$tot}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("F{$tot}")->getNumberFormat()->setFormatCode($nf);
    $sheet->getStyle("E{$tot}:F{$tot}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("F{$tot}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("F{$tot}")->getBorders()->getLeft()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle("F{$tot}")->getBorders()->getRight()->setBorderStyle(Border::BORDER_THIN);
    $sheet->getRowDimension($tot)->setRowHeight(15.75);

    // --- Kotak TOTAL ---
    $box = $tot + 4;
    $sheet->setCellValue("E{$box}", 'TOTAL');
    $sheet->setCellValue("F{$box}", "=+F{$tot}");
    $sheet->getStyle("E{$box}:F{$box}")->applyFromArray($thin);
    $sheet->getStyle("E{$box}:F{$box}")->getFont()->setName('Aptos Narrow')->setSize(14)->setBold(true);
    $sheet->getStyle("E{$box}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle("F{$box}")->getNumberFormat()->setFormatCode($nf);
    $sheet->getStyle("E{$box}:F{$box}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension($box)->setRowHeight(18.75);

    // --- Blok saldo (diisi manual Accounting) ---
    $s1 = $box + 1;
    $greenRow = function (int $row) use ($sheet, $nfRed) {
        $sheet->getStyle("E{$row}:F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('92D050');
        $sheet->getStyle("E{$row}")->getFont()->setName('Comic Sans MS')->setSize(11);
        $sheet->getStyle("F{$row}")->getFont()->setName('Comic Sans MS')->setSize(12)->getColor()->setRGB('FF0000');
        $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode($nfRed);
        foreach (['E', 'F'] as $cc) { // seperti template: tiap sel bergaris bawah/kiri/kanan
            $bd = $sheet->getStyle("{$cc}{$row}")->getBorders();
            $bd->getBottom()->setBorderStyle(Border::BORDER_THIN);
            $bd->getLeft()->setBorderStyle(Border::BORDER_THIN);
            $bd->getRight()->setBorderStyle(Border::BORDER_THIN);
        }
        $sheet->getStyle("E{$row}:F{$row}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($row)->setRowHeight(19.5);
    };
    foreach ([$s1 => 'Saldo BCA Hr tgl. ', $s1 + 1 => 'Saldo BCA Loan tgl. ', $s1 + 2 => 'Penarikan Loan '] as $row => $label) {
        $sheet->setCellValue("E{$row}", $label);
        $greenRow($row);
    }
    $sisa = $s1 + 4;
    $sheet->setCellValue("E{$sisa}", 'Sisa Saldo setelah pembayaran ');
    $sheet->getStyle("E{$sisa}")->getFont()->setName('Aptos Narrow')->setSize(16)->setBold(true);
    $sheet->getRowDimension($sisa)->setRowHeight(21);
    $sv = $sisa + 1;
    $sheet->setCellValue("E{$sv}", "=+E{$s1}");
    $sheet->setCellValue("F{$sv}", "=IF(F{$s1}=\"\",\"\",F{$s1}-F{$tot})");
    $greenRow($sv);

    // --- Cetak ---
    $ps = $sheet->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
    $ps->setPaperSize(PageSetup::PAPERSIZE_LEGAL);
    $ps->setFitToWidth(1)->setFitToHeight(0);
    $ps->setFitToPage(true);
    $ps->setPrintArea("B4:J{$sv}");
    $ps->setRowsToRepeatAtTopByStartAndEnd(7, 7);
    $sheet->freezePane('C8');
    _excelPrintFooter($sheet);

    $noteRow = $sv + 2;
    $sheet->setCellValue("I{$noteRow}", printedAtLabel() . ', ' . printedByLabel());
    $sheet->getStyle("I{$noteRow}")->getFont()->setSize(8)->getColor()->setRGB('999999');
    $sheet->getStyle("I{$noteRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}
