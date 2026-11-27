<?php

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * EXPORT EXCEL "Rekap PPN" -- 2 sheet mengikuti Pengajuan fee.xlsx: "PPN Masukan" dan "PPN Keluaran".
 *   PPN Masukan : NO | NPWP | NAMA | FAKTUR | TGL | MASA | TAHUN | DPP | DPP NILAI LAIN | PPN | JUMLAH | PROJECT | KETERANGAN
 *                 + baris total, lalu blok ringkasan (Pajak Masukan / Keluaran / Kurang-Lebih Bayar / Lebih Bayar masa sebelumnya).
 *   PPN Keluaran: NO | TANGGAL | INVOICE | FAKTUR PAJAK | NAMA CUSTOMER | NPWP | NAMA BARANG | QTY | PRICELIST | DISKON |
 *                 HARGA SATUAN | SUBTOTAL | DPP | DPP NILAI LAIN | PPN | TOTAL | KETERANGAN (1 baris per barang).
 * Nilai = angka yang tampil di aplikasi (sudah dibulatkan per sen); baris total memakai rumus SUM.
 *
 * @param array  $masukan   baris ppn_masukan
 * @param array  $keluaran  header ppn_keluaran + kunci 'items'
 * @param array  $summary   hasil RekapPpn::summary()
 * @param string $label     mis. "Oktober 2026"
 */
function streamRekapPpn(array $masukan, array $keluaran, array $summary, string $label, string $filename): void
{
    $nf = '#,##0.00;[Red]\(#,##0.00\);"-"';
    $thin = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
    $head = function ($sheet, string $range) use ($thin) {
        $sheet->getStyle($range)->applyFromArray($thin);
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    };
    $text = fn($sheet, string $cell, ?string $v) => $sheet->setCellValueExplicit($cell, (string) $v, DataType::TYPE_STRING);
    $date = function ($sheet, string $cell, string $ymd) {
        // Hari penuh (tanpa pecahan jam) -- hindari bergeser sehari akibat zona waktu.
        $sheet->setCellValue($cell, (int) floor(XlDate::PHPToExcel(strtotime($ymd . ' 12:00:00'))));
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    };

    $wb = new Spreadsheet();

    // ================= PPN Masukan =================
    $sh = $wb->getActiveSheet();
    $sh->setTitle('PPN Masukan');
    foreach (['A' => 5, 'B' => 21, 'C' => 32, 'D' => 22, 'E' => 12, 'F' => 7, 'G' => 8, 'H' => 18, 'I' => 18, 'J' => 17, 'K' => 18, 'L' => 20, 'M' => 30] as $c => $w) {
        $sh->getColumnDimension($c)->setWidth($w);
    }
    $sh->setCellValue('A1', 'REKAP PPN MASUKAN - ' . strtoupper($label));
    $sh->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    foreach (['NO.', 'NPWP', 'NAMA', 'FAKTUR', 'TGL', 'MASA', 'TAHUN', 'DPP', 'DPP NILAI LAIN', 'PPN', 'JUMLAH', 'PROJECT', 'KETERANGAN'] as $i => $t) {
        $sh->setCellValue(chr(65 + $i) . '3', $t);
    }
    $head($sh, 'A3:M3');
    $r = 4;
    $first = $r;
    foreach ($masukan as $i => $m) {
        $sh->setCellValue("A{$r}", $i + 1);
        $text($sh, "B{$r}", $m['npwp']);
        $sh->setCellValue("C{$r}", $m['vendor_name']);
        $text($sh, "D{$r}", $m['faktur_number']);
        $date($sh, "E{$r}", $m['faktur_date']);
        $sh->setCellValue("F{$r}", (int) $m['masa']);
        $sh->setCellValue("G{$r}", (int) $m['tahun']);
        $sh->setCellValue("H{$r}", (float) $m['dpp']);
        $sh->setCellValue("I{$r}", (float) $m['dpp_nilai_lain']);
        $sh->setCellValue("J{$r}", (float) $m['ppn_amount']);
        $sh->setCellValue("K{$r}", (float) $m['total_amount']);
        $sh->setCellValue("L{$r}", $m['project_name']);
        $sh->setCellValue("M{$r}", $m['notes']);
        $r++;
    }
    $last = $r - 1;
    if ($masukan) {
        $sh->getStyle("A{$first}:M{$last}")->applyFromArray($thin);
        $sh->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("F{$first}:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sh->getStyle("H{$first}:K" . ($last + 1))->getNumberFormat()->setFormatCode($nf);
        foreach (['H', 'I', 'J', 'K'] as $c) {
            $sh->setCellValue("{$c}{$r}", "=SUM({$c}{$first}:{$c}{$last})");
        }
        $sh->getStyle("A{$r}:M{$r}")->getFont()->setBold(true);
        $sh->getStyle("A{$r}:M{$r}")->applyFromArray($thin);
        $sh->setCellValue("G{$r}", 'TOTAL');
        $sh->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $r++;
    }

    // Ringkasan (blok "HME" di bawah sheet PPN Masukan)
    $r += 2;
    $s = $summary;
    $sh->setCellValue("H{$r}", 'HME');
    $sh->setCellValue("I{$r}", 'RINGKASAN ' . strtoupper($label));
    $sh->getStyle("H{$r}:J{$r}")->getFont()->setBold(true);
    $lines = [
        ['PAJAK KELUARAN', (float) $s['keluaran']['ppn']],
        ['PAJAK MASUKAN', (float) $s['masukan']['ppn']],
        [$s['selisih'] >= 0 ? 'PPN KURANG BAYAR (Keluaran - Masukan)' : 'PPN LEBIH BAYAR (Keluaran - Masukan)', (float) $s['selisih']],
    ];
    if ($s['period'] !== null) {
        $lines[] = ['LEBIH BAYAR MASA SEBELUMNYA', (float) $s['carry']];
        $lines[] = [$s['setor'] >= 0 ? 'PPN YANG HARUS DISETOR' : 'LEBIH BAYAR (DIBAWA KE MASA BERIKUTNYA)', (float) $s['setor']];
    }
    foreach ($lines as $idx => [$t, $v]) {
        $r++;
        $sh->setCellValue("I{$r}", $t);
        $sh->setCellValue("J{$r}", $v);
        $sh->getStyle("J{$r}")->getNumberFormat()->setFormatCode($nf);
        if ($idx === count($lines) - 1) {
            $sh->getStyle("I{$r}:J{$r}")->getFont()->setBold(true);
        }
    }
    $sh->getColumnDimension('I')->setWidth(40);
    $sh->freezePane('A4');
    $ps = $sh->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    $ps->setFitToPage(true);

    // ================= PPN Keluaran =================
    $sk = $wb->createSheet();
    $sk->setTitle('PPN Keluaran');
    foreach (['A' => 5, 'B' => 12, 'C' => 24, 'D' => 22, 'E' => 32, 'F' => 21, 'G' => 40, 'H' => 8, 'I' => 16, 'J' => 9, 'K' => 16, 'L' => 17, 'M' => 17, 'N' => 17, 'O' => 16, 'P' => 17, 'Q' => 26] as $c => $w) {
        $sk->getColumnDimension($c)->setWidth($w);
    }
    $sk->setCellValue('A1', 'REKAP PPN KELUARAN - ' . strtoupper($label));
    $sk->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    foreach (['NO.', 'TANGGAL', 'INVOICE', 'FAKTUR PAJAK', 'NAMA CUSTOMER', 'NPWP', 'NAMA BARANG', 'QTY', 'PRICELIST', 'DISKON', 'HARGA SATUAN', 'SUBTOTAL', 'DPP', 'DPP NILAI LAIN', 'PPN', 'TOTAL', 'KETERANGAN'] as $i => $t) {
        $sk->setCellValue(chr(65 + $i) . '3', $t);
    }
    $head($sk, 'A3:Q3');
    $r = 4;
    $first = $r;
    foreach ($keluaran as $i => $k) {
        $items = $k['items'] ?: [null];
        foreach ($items as $j => $it) {
            if ($j === 0) {
                $sk->setCellValue("A{$r}", $i + 1);
                $date($sk, "B{$r}", $k['entry_date']);
                $sk->setCellValue("C{$r}", $k['invoice_number']);
                $text($sk, "D{$r}", $k['faktur_number']);
                $sk->setCellValue("E{$r}", $k['customer_name']);
                $text($sk, "F{$r}", $k['npwp']);
                $sk->setCellValue("Q{$r}", $k['notes']);
            }
            if ($it) {
                $sk->setCellValue("G{$r}", $it['item_name']);
                $sk->setCellValue("H{$r}", (float) $it['qty']);
                if ($it['pricelist'] !== null) {
                    $sk->setCellValue("I{$r}", (float) $it['pricelist']);
                    $disc = RekapPpn::discountPercent($it['pricelist'], $it['unit_price']);
                    if ($disc !== null) {
                        $sk->setCellValue("J{$r}", $disc / 100);
                    }
                }
                $sk->setCellValue("K{$r}", (float) $it['unit_price']);
                $sk->setCellValue("L{$r}", (float) $it['subtotal']);
                $sk->setCellValue("M{$r}", (float) $it['dpp']);
                $sk->setCellValue("N{$r}", (float) $it['dpp_nilai_lain']);
                $sk->setCellValue("O{$r}", (float) $it['ppn_amount']);
                $sk->setCellValue("P{$r}", (float) $it['total_amount']);
            }
            $r++;
        }
    }
    $last = $r - 1;
    if ($keluaran) {
        $sk->getStyle("A{$first}:Q{$last}")->applyFromArray($thin);
        $sk->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sk->getStyle("H{$first}:H" . ($last + 1))->getNumberFormat()->setFormatCode('#,##0.##');
        $sk->getStyle("I{$first}:I{$last}")->getNumberFormat()->setFormatCode($nf);
        $sk->getStyle("J{$first}:J{$last}")->getNumberFormat()->setFormatCode('0.00%');
        $sk->getStyle("K{$first}:P" . ($last + 1))->getNumberFormat()->setFormatCode($nf);
        foreach (['H', 'L', 'M', 'N', 'O', 'P'] as $c) {
            $sk->setCellValue("{$c}{$r}", "=SUM({$c}{$first}:{$c}{$last})");
        }
        $sk->getStyle("A{$r}:Q{$r}")->getFont()->setBold(true);
        $sk->getStyle("A{$r}:Q{$r}")->applyFromArray($thin);
        $sk->setCellValue("G{$r}", 'TOTAL');
        $sk->getStyle("G{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $sk->freezePane('A4');
    $ps = $sk->getPageSetup();
    $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    $ps->setFitToPage(true);

    $wb->setActiveSheetIndex(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');
    (new Xlsx($wb))->save('php://output');
    exit;
}
