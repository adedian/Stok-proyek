<?php

/**
 * Penulis PDF TABEL cepat untuk laporan besar (Laporan Stok Barang: Cetak Detail,
 * Cetak Rekap, Cetak Stok Tersedia).
 *
 * Kenapa bukan Dompdf (HTML/CSS -> PDF)? Dompdf menghitung tata letak SETIAP sel tabel
 * lewat mesin CSS: ~10-12 ms dan ~30 KB RAM per baris 20-kolom. Di hosting, Cetak Detail
 * dengan data sebenarnya (ratusan baris) butuh puluhan detik, browser tidak menampilkan
 * apa-apa selama menunggu (unduhan baru mulai setelah PDF selesai), user menekan tombol
 * berulang -> proses menumpuk -> koneksi DB putus / unduhan macet. Di sini PDF ditulis
 * LANGSUNG dengan Dompdf\Cpdf (penulis PDF tingkat rendah yang sama dipakai Dompdf di
 * belakang layar): tata letak tabel dihitung sendiri (lebar kolom tetap, teks dibungkus
 * per kata), jadi ratusan baris selesai dalam hitungan milidetik dengan memori kecil.
 *
 * Tampilan meniru template HTML lama (_stock_*_print.php): A4 landscape, header 2 baris
 * berwarna, grup Saldo Awal / In / Out / Saldo Akhir + sub-kolom "Dengan Harga".
 */
class FastPdfTable
{
    public const PAGE_W = 841.89;   // A4 landscape (pt)
    public const PAGE_H = 595.28;

    private \Dompdf\Cpdf $c;
    private string $fontR;
    private string $fontB;
    private float $mL = 28.0;
    private float $mR = 28.0;
    private float $mT = 28.0;
    private float $mB = 36.0;
    private float $y;
    /** @var float[] lebar tiap kolom (pt) */
    private array $cols = [];
    private float $size = 7.0;
    private float $padX = 2.2;
    private float $padY = 2.6;
    /** @var callable|null menggambar header tabel (dipanggil di awal tiap halaman) */
    private $headerFn = null;
    private string $footer;
    /** @var float[] warna garis sel [r,g,b] */
    private array $border = [0.871, 0.886, 0.902];

    public function __construct(string $footer)
    {
        $opt = new \Dompdf\Options();
        $dir = rtrim($opt->getFontDir(), '/\\');
        $this->c = new \Dompdf\Cpdf([0, 0, self::PAGE_W, self::PAGE_H], true, $dir, sys_get_temp_dir());
        $this->c->addInfo('Producer', 'HEXA STOK');
        $this->fontR = $dir . '/Helvetica';
        $this->fontB = $dir . '/Helvetica-Bold';
        $this->c->selectFont($this->fontR);
        $this->footer = $footer;
        $this->y = $this->mT;
    }

    /** @param float[] $weights bobot relatif kolom -> dinormalkan ke lebar halaman terpakai */
    public function setColumns(array $weights): void
    {
        $usable = self::PAGE_W - $this->mL - $this->mR;
        $sum = array_sum($weights);
        $this->cols = array_map(fn($w) => $usable * $w / $sum, $weights);
    }

    public function setBorder(array $rgb): void
    {
        $this->border = $rgb;
    }

    public function setHeader(callable $fn): void
    {
        $this->headerFn = $fn;
    }

    public function lineHeight(): float
    {
        return $this->size * 1.22;
    }

    public static function hex(string $h): array
    {
        $h = ltrim($h, '#');
        return [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
    }

    private function font(bool $bold): void
    {
        $this->c->selectFont($bold ? $this->fontB : $this->fontR);
    }

    public function textWidth(string $t, float $size, bool $bold = false): float
    {
        $this->font($bold);
        return $this->c->getTextWidth($size, $t);
    }

    /** Ukuran font terbesar (<= $size, minimum 4.8) agar $text muat satu baris selebar $w. */
    private function fitSize(string $text, float $w, float $size, bool $bold): float
    {
        while ($size > 4.8 && $this->textWidth($text, $size, $bold) > $w) {
            $size -= 0.4;
        }
        return $size;
    }

    /** Bungkus teks per kata supaya muat $w; kata yang lebih lebar dari $w dipotong per karakter. */
    public function wrap(string $text, float $w, float $size, bool $bold = false): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [''];
        }
        $lines = [];
        $cur = '';
        foreach (explode(' ', $text) as $word) {
            $try = $cur === '' ? $word : $cur . ' ' . $word;
            if ($this->textWidth($try, $size, $bold) <= $w) {
                $cur = $try;
                continue;
            }
            if ($cur !== '') {
                $lines[] = $cur;
                $cur = '';
            }
            if ($this->textWidth($word, $size, $bold) <= $w) {
                $cur = $word;
                continue;
            }
            // kata terlalu panjang: potong per karakter
            $chunk = '';
            foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
                if ($chunk !== '' && $this->textWidth($chunk . $ch, $size, $bold) > $w) {
                    $lines[] = $chunk;
                    $chunk = '';
                }
                $chunk .= $ch;
            }
            $cur = $chunk;
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }
        return $lines ?: [''];
    }

    private function colX(int $i): float
    {
        $x = $this->mL;
        for ($k = 0; $k < $i; $k++) {
            $x += $this->cols[$k];
        }
        return $x;
    }

    private function colW(int $i, int $span): float
    {
        $w = 0.0;
        for ($k = $i; $k < $i + $span; $k++) {
            $w += $this->cols[$k];
        }
        return $w;
    }

    /**
     * Gambar satu sel (kotak + isi) pada posisi absolut.
     * $o: align l|r|c, bold, fill (#hex|null), size, color (#hex), lines (hasil wrap bila sudah ada)
     */
    private function drawCell(float $x, float $top, float $w, float $h, string $text, array $o = []): void
    {
        $size = $o['size'] ?? $this->size;
        $bold = !empty($o['bold']);
        $H = self::PAGE_H;
        if (!empty($o['fit']) && !isset($o['lines'])) {
            $size = $this->fitSize((string) $text, $w - 2 * $this->padX, $size, $bold);
        }

        if (!empty($o['fill'])) {
            $this->c->setColor(self::hex($o['fill']));
            $this->c->filledRectangle($x, $H - $top - $h, $w, $h);
        }
        if (($o['border'] ?? true) !== false) {
            $this->c->setStrokeColor($this->border);
            $this->c->setLineStyle(0.5, 'butt', 'miter');
            $this->c->rectangle($x, $H - $top - $h, $w, $h);
        }
        if ($text === '') {
            return;
        }

        $lines = $o['lines'] ?? $this->wrap($text, $w - 2 * $this->padX, $size, $bold);
        $lh = $size * 1.22;
        $block = count($lines) * $lh;
        $ty = $top + ($h - $block) / 2;                 // rata tengah vertikal
        $this->font($bold);
        $this->c->setColor(self::hex($o['color'] ?? '#212529'));
        $align = $o['align'] ?? 'l';
        foreach ($lines as $i => $ln) {
            if ($ln === '') {
                continue;
            }
            $tw = $this->c->getTextWidth($size, $ln);
            if ($align === 'r') {
                $tx = $x + $w - $this->padX - $tw;
            } elseif ($align === 'c') {
                $tx = $x + ($w - $tw) / 2;
            } else {
                $tx = $x + $this->padX;
            }
            $base = $ty + $i * $lh + $size * 0.86;      // garis dasar teks (dari atas halaman)
            $this->c->addText($tx, $H - $base, $size, $ln);
        }
    }

    /**
     * Tambah baris data. $cells: [['c'=>kolomAwal,'s'=>span,'t'=>teks,'align'=>,'bold'=>,'fill'=>], ...]
     * Kolom yang tak disebut digambar kosong (kotak saja) supaya garis tabel tetap utuh.
     */
    public function row(array $cells, ?string $defaultFill = null): void
    {
        $max = 1;
        foreach ($cells as $k => $cell) {
            $w = $this->colW($cell['c'], $cell['s'] ?? 1) - 2 * $this->padX;
            if (empty($cell['wrap'])) {
                // angka/satuan/kode: satu baris, font mengecil bila perlu (tidak dipecah di tengah angka)
                $cells[$k]['fit'] = true;
                continue;
            }
            $cells[$k]['lines'] = $this->wrap((string) $cell['t'], $w, $cell['size'] ?? $this->size, !empty($cell['bold']));
            $max = max($max, count($cells[$k]['lines']));
        }
        $h = $max * $this->lineHeight() + 2 * $this->padY;
        $this->ensureSpace($h);

        // Isi semua kolom (kosong) dulu supaya tidak ada celah garis
        $covered = [];
        foreach ($cells as $cell) {
            for ($k = $cell['c']; $k < $cell['c'] + ($cell['s'] ?? 1); $k++) {
                $covered[$k] = true;
            }
        }
        for ($k = 0; $k < count($this->cols); $k++) {
            if (!isset($covered[$k])) {
                $this->drawCell($this->colX($k), $this->y, $this->cols[$k], $h, '', ['fill' => $defaultFill]);
            }
        }
        foreach ($cells as $cell) {
            $span = $cell['s'] ?? 1;
            $o = $cell;
            $o['fill'] = $cell['fill'] ?? $defaultFill;
            $this->drawCell($this->colX($cell['c']), $this->y, $this->colW($cell['c'], $span), $h, (string) $cell['t'], $o);
        }
        $this->y += $h;
    }

    /**
     * Blok header 2 baris. $row1/$row2: sel dengan 'c','s','t','fill','align','bold','rows' (rowspan 1|2).
     * Sel dengan rows=2 hanya ditulis di $row1 dan menutup juga baris 2.
     */
    public function headerBlock(array $row1, array $row2): void
    {
        $h1 = $this->lineHeight() + 2 * $this->padY;
        $h2 = $h1;
        foreach ($row2 as $k => $cell) {
            if ((string) $cell['t'] === 'Harga Satuan') {      // label panjang -> 2 baris, bukan font mengecil
                $row2[$k]['lines'] = ['Harga', 'Satuan'];
                $h2 = $this->lineHeight() * 2 + 2 * $this->padY;
            }
        }
        $y = $this->y;
        foreach ($row1 as $cell) {
            $rows = $cell['rows'] ?? 1;
            $o = $cell + ['bold' => true, 'align' => 'c', 'fit' => true];
            $this->drawCell($this->colX($cell['c']), $y, $this->colW($cell['c'], $cell['s'] ?? 1), $rows === 2 ? $h1 + $h2 : $h1, (string) $cell['t'], $o);
        }
        foreach ($row2 as $cell) {
            $o = $cell + ['bold' => true, 'align' => 'c', 'fit' => true];
            $this->drawCell($this->colX($cell['c']), $y + $h1, $this->colW($cell['c'], $cell['s'] ?? 1), $h2, (string) $cell['t'], $o);
        }
        $this->y = $y + $h1 + $h2;
    }

    /** Teks bebas (judul, keterangan) di posisi kursor; menggeser kursor ke bawah. */
    public function text(string $t, float $size, array $o = []): void
    {
        $bold = !empty($o['bold']);
        $lh = $size * 1.3;
        $this->ensureSpace($lh, false);
        $this->font($bold);
        $this->c->setColor(self::hex($o['color'] ?? '#212529'));
        $tw = $this->c->getTextWidth($size, $t);
        $usable = self::PAGE_W - $this->mL - $this->mR;
        $x = ($o['align'] ?? 'l') === 'c' ? $this->mL + ($usable - $tw) / 2 : $this->mL;
        $this->c->addText($x, self::PAGE_H - ($this->y + $size * 0.9), $size, $t);
        $this->y += $lh + ($o['after'] ?? 0);
    }

    public function gap(float $pt): void
    {
        $this->y += $pt;
    }

    public function header(): void
    {
        if ($this->headerFn) {
            ($this->headerFn)($this);
        }
    }

    private function ensureSpace(float $h, bool $withHeader = true): void
    {
        if ($this->y + $h <= self::PAGE_H - $this->mB) {
            return;
        }
        $this->newPage($withHeader);
    }

    private function drawFooter(): void
    {
        $size = 7.0;
        $this->font(false);
        $this->c->setColor(self::hex('#999999'));
        $tw = $this->c->getTextWidth($size, $this->footer);
        $this->c->addText(self::PAGE_W - $this->mR - $tw, 16, $size, $this->footer);
    }

    private function newPage(bool $withHeader = true): void
    {
        $this->drawFooter();
        $this->c->newPage();
        $this->y = $this->mT;
        if ($withHeader) {
            $this->header();
        }
    }

    public function finish(): string
    {
        $this->drawFooter();
        return $this->c->output();
    }
}

/** Format angka qty seperti template lama: bulat tanpa desimal, selain itu 2 desimal (koma). */
function pdfFmtQty($value): string
{
    $value = (float) $value;
    if ($value == (int) $value) {
        return number_format($value, 0, ',', '.');
    }
    return number_format($value, 2, ',', '.');
}

function pdfFmtMoney($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    return number_format((float) $value, 0, ',', '.');
}

/** Kirim PDF biner ke browser sebagai unduhan (bersihkan buffer output dulu supaya berkas tak rusak). */
function streamPdfBinary(string $pdf, string $filename): void
{
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    header('Content-Type: application/pdf');
    header('Content-Length: ' . strlen($pdf));
    header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $filename) . '.pdf"');
    header('Cache-Control: private, no-transform');
    echo $pdf;
    flush();
    exit;
}

/**
 * Susun 2 baris header grup Saldo Awal / In / Out / Saldo Akhir (+ "Dengan Harga") mulai dari
 * kolom $first. Dipakai Detail & Rekap. Mengembalikan [row1, row2].
 */
function pdfStockGroupHeader(int $first, bool $showPrice): array
{
    $groups = [
        ['Saldo Awal', '#fde3d3', '#fef1e9'],
        ['In', '#d9f2e3', '#ecf9f1'],
        ['Out', '#d6e8fb', '#ebf4fd'],
        ['Saldo Akhir', '#fff3a3', '#fff9d1'],
    ];
    $row1 = [];
    $row2 = [];
    $c = $first;
    foreach ($groups as [$label, $f1, $f2]) {
        $row1[] = ['c' => $c, 's' => 2, 't' => $label, 'fill' => $f1];
        $row2[] = ['c' => $c, 't' => 'Qty', 'fill' => $f1, 'align' => 'r'];
        $row2[] = ['c' => $c + 1, 't' => 'Satuan', 'fill' => $f1];
        $c += 2;
        if ($showPrice) {
            $row1[] = ['c' => $c, 's' => 2, 't' => 'Dengan Harga', 'fill' => $f2];
            $row2[] = ['c' => $c, 't' => 'Harga Satuan', 'fill' => $f2, 'align' => 'r'];
            $row2[] = ['c' => $c + 1, 't' => 'Total', 'fill' => $f2, 'align' => 'r'];
            $c += 2;
        }
    }
    return [$row1, $row2];
}

/** Judul + baris meta periode (halaman pertama Detail/Rekap). */
function pdfStockTitle(FastPdfTable $p, string $title, string $dateFrom, string $dateTo): void
{
    $p->text($title, 14, ['bold' => true, 'after' => 2]);
    $p->text(
        'Periode: ' . ($dateFrom !== '' ? formatTanggal($dateFrom) : '(seluruh riwayat)') . ' - ' . ($dateTo !== '' ? formatTanggal($dateTo) : 'Sekarang')
        . '   |   Dicetak: ' . formatTanggal(date('Y-m-d')) . ' ' . date('H:i'),
        8,
        ['color' => '#6c757d', 'after' => 6]
    );
}

/**
 * Cetak Detail: per barang, daftar transaksi + baris "Saldo Akhir"; $groups dari
 * Inventory::stockDetailReport(). $grandTotals = ['in'=>,'out'=>,'akhir'=>] dari SEMUA barang.
 */
function fastPdfStockDetail(array $groups, string $dateFrom, string $dateTo, bool $showPrice, array $grandTotals): string
{
    $p = new FastPdfTable(printedAtLabel() . ', ' . printedByLabel());
    pdfStockTitle($p, 'Laporan Stok Barang - Cetak Detail', $dateFrom, $dateTo);
    if (empty($groups)) {
        $p->text('Tidak ada mutasi stok pada periode ini.', 9);
        return $p->finish();
    }

    // Bobot kolom: Tanggal, No Bukti, Kode, Nama + 4 grup (Qty, Satuan[, Harga, Total])
    $w = [6.6, 6.4, 8.4, 10.6];
    for ($g = 0; $g < 4; $g++) {
        $w = array_merge($w, $showPrice ? [2.9, 3.5, 5.2, 5.4] : [4.6, 4.4]);
    }
    $p->setColumns($w);
    $nc = count($w);
    $p->setHeader(function (FastPdfTable $p) use ($showPrice) {
        $fixed = ['#f1f3f5'];
        [$r1, $r2] = pdfStockGroupHeader(4, $showPrice);
        $head = [];
        foreach (['Tanggal', 'No Bukti', 'Kode Barang', 'Nama Barang'] as $i => $t) {
            $head[] = ['c' => $i, 't' => $t, 'fill' => $fixed[0], 'rows' => 2];
        }
        $p->headerBlock(array_merge($head, $r1), $r2);
    });
    $p->header();

    $perGroup = $showPrice ? 4 : 2;
    $akhirC = 4 + 3 * $perGroup;           // kolom pertama grup Saldo Akhir
    foreach ($groups as $g) {
        $unit = (string) $g['unit'];
        $code = ($g['item_code'] ?? '') !== '' ? (string) $g['item_code'] : '-';
        $label = stockReportItemLabel($g);
        foreach ($g['lines'] as $line) {
            $hasIn = (float) $line['in_qty'] > 0;
            $hasOut = (float) $line['out_qty'] > 0;
            $priced = $line['line_price'] !== null;
            $akhirPriced = $line['saldo_akhir_price'] !== null;
            $cells = [
                ['c' => 0, 't' => formatTanggal(substr((string) $line['transaction_date'], 0, 10)), 'wrap' => true],
                ['c' => 1, 't' => (string) $line['no_bukti'], 'wrap' => true],
                ['c' => 2, 't' => $code],
                ['c' => 3, 't' => $label, 'wrap' => true],
                ['c' => 4, 't' => pdfFmtQty($line['saldo_awal']), 'align' => 'r'],
                ['c' => 5, 't' => $unit],
            ];
            $c = 4 + $perGroup;            // grup In
            if ($hasIn) {
                $cells[] = ['c' => $c, 't' => pdfFmtQty($line['in_qty']), 'align' => 'r'];
                $cells[] = ['c' => $c + 1, 't' => $unit];
                if ($showPrice && $priced) {
                    $cells[] = ['c' => $c + 2, 't' => pdfFmtMoney($line['line_price']), 'align' => 'r'];
                    $cells[] = ['c' => $c + 3, 't' => pdfFmtMoney($line['in_value']), 'align' => 'r'];
                }
            }
            $c = 4 + 2 * $perGroup;        // grup Out
            if ($hasOut) {
                $cells[] = ['c' => $c, 't' => pdfFmtQty($line['out_qty']), 'align' => 'r'];
                $cells[] = ['c' => $c + 1, 't' => $unit];
                if ($showPrice && $priced) {
                    $cells[] = ['c' => $c + 2, 't' => pdfFmtMoney($line['line_price']), 'align' => 'r'];
                }
                if ($showPrice && $line['out_value'] !== null) {
                    $cells[] = ['c' => $c + 3, 't' => pdfFmtMoney($line['out_value']), 'align' => 'r'];
                }
            }
            $cells[] = ['c' => $akhirC, 't' => pdfFmtQty($line['saldo_akhir']), 'align' => 'r'];
            $cells[] = ['c' => $akhirC + 1, 't' => $unit];
            if ($showPrice && $akhirPriced) {
                $cells[] = ['c' => $akhirC + 2, 't' => pdfFmtMoney($line['saldo_akhir_price']), 'align' => 'r'];
                $cells[] = ['c' => $akhirC + 3, 't' => pdfFmtMoney($line['saldo_akhir_value']), 'align' => 'r'];
            }
            $p->row($cells);
        }

        // Baris ringkasan "Saldo Akhir" per barang
        $sum = [
            ['c' => 0, 's' => $akhirC, 't' => 'Saldo Akhir', 'align' => 'r', 'bold' => true],
            ['c' => $akhirC, 't' => pdfFmtQty($g['saldo_akhir']), 'align' => 'r', 'bold' => true],
            ['c' => $akhirC + 1, 't' => $unit, 'bold' => true],
        ];
        if ($showPrice) {
            if ($g['saldo_akhir_price'] !== null) {
                $sum[] = ['c' => $akhirC + 2, 't' => pdfFmtMoney($g['saldo_akhir_price']), 'align' => 'r', 'bold' => true];
            }
            if ($g['saldo_akhir_value'] !== null) {
                $sum[] = ['c' => $akhirC + 3, 't' => pdfFmtMoney($g['saldo_akhir_value']), 'align' => 'r', 'bold' => true];
            }
        }
        $p->row($sum, '#f8f9fa');
    }

    if ($showPrice) {
        $p->row([
            ['c' => 0, 's' => 10, 't' => 'TOTAL SALDO KESELURUHAN', 'align' => 'r', 'bold' => true],
            ['c' => 10, 's' => 2, 't' => pdfFmtMoney($grandTotals['in']), 'align' => 'r', 'bold' => true],
            ['c' => 14, 's' => 2, 't' => pdfFmtMoney($grandTotals['out']), 'align' => 'r', 'bold' => true],
            ['c' => 18, 's' => 2, 't' => pdfFmtMoney($grandTotals['akhir']), 'align' => 'r', 'bold' => true],
        ], '#fff3a3');
    }
    return $p->finish();
}

/** Cetak Rekap: 1 baris per barang; $rows dari Inventory::stockRecapReport(). */
function fastPdfStockRecap(array $rows, string $dateFrom, string $dateTo, bool $showPrice, array $grandTotals): string
{
    $p = new FastPdfTable(printedAtLabel() . ', ' . printedByLabel());
    pdfStockTitle($p, 'Laporan Stok Barang - Cetak Rekap', $dateFrom, $dateTo);
    if (empty($rows)) {
        $p->text('Tidak ada data barang yang cocok dengan filter ini.', 9);
        return $p->finish();
    }

    $w = [3.0, 8.6, 15.4];
    for ($g = 0; $g < 4; $g++) {
        $w = array_merge($w, $showPrice ? [3.0, 3.5, 5.3, 5.6] : [5.2, 4.8]);
    }
    $p->setColumns($w);
    $p->setHeader(function (FastPdfTable $p) use ($showPrice) {
        $head = [];
        foreach (['No', 'Kode Barang', 'Nama Barang'] as $i => $t) {
            $head[] = ['c' => $i, 't' => $t, 'fill' => '#f1f3f5', 'rows' => 2];
        }
        [$r1, $r2] = pdfStockGroupHeader(3, $showPrice);
        $p->headerBlock(array_merge($head, $r1), $r2);
    });
    $p->header();

    $perGroup = $showPrice ? 4 : 2;
    $cIn = 3 + $perGroup;
    $cOut = 3 + 2 * $perGroup;
    $cAkhir = 3 + 3 * $perGroup;
    foreach ($rows as $i => $r) {
        $unit = (string) $r['unit'];
        $priced = ($r['in_unit_price'] ?? null) !== null;
        $akhirPriced = ($r['saldo_akhir_price'] ?? null) !== null;
        $keluar = (float) $r['mutasi_keluar'];
        $cells = [
            ['c' => 0, 't' => (string) ($i + 1), 'align' => 'r'],
            ['c' => 1, 't' => ($r['item_code'] ?? '') !== '' ? (string) $r['item_code'] : '-'],
            ['c' => 2, 't' => stockReportItemLabel($r), 'wrap' => true],
            ['c' => 3, 't' => pdfFmtQty($r['saldo_awal']), 'align' => 'r'],
            ['c' => 4, 't' => $unit],
            ['c' => $cIn, 't' => pdfFmtQty($r['mutasi_masuk']), 'align' => 'r'],
            ['c' => $cIn + 1, 't' => $unit],
            ['c' => $cOut, 't' => pdfFmtQty($keluar), 'align' => 'r'],
            ['c' => $cOut + 1, 't' => $unit],
            ['c' => $cAkhir, 't' => pdfFmtQty($r['saldo_akhir']), 'align' => 'r'],
            ['c' => $cAkhir + 1, 't' => $unit],
        ];
        if ($showPrice) {
            if ($priced) {
                $cells[] = ['c' => $cIn + 2, 't' => pdfFmtMoney($r['in_unit_price']), 'align' => 'r'];
                $cells[] = ['c' => $cIn + 3, 't' => pdfFmtMoney($r['in_value']), 'align' => 'r'];
            }
            if ($akhirPriced && $keluar > 0) {
                $cells[] = ['c' => $cOut + 2, 't' => pdfFmtMoney($r['saldo_akhir_price']), 'align' => 'r'];
                $cells[] = ['c' => $cOut + 3, 't' => pdfFmtMoney($r['out_value']), 'align' => 'r'];
            }
            if ($akhirPriced) {
                $cells[] = ['c' => $cAkhir + 2, 't' => pdfFmtMoney($r['saldo_akhir_price']), 'align' => 'r'];
                $cells[] = ['c' => $cAkhir + 3, 't' => pdfFmtMoney($r['saldo_akhir_value']), 'align' => 'r'];
            }
        }
        $p->row($cells);
    }

    if ($showPrice) {
        $p->row([
            ['c' => 0, 's' => 9, 't' => 'TOTAL SALDO KESELURUHAN', 'align' => 'r', 'bold' => true],
            ['c' => 9, 's' => 2, 't' => pdfFmtMoney($grandTotals['in']), 'align' => 'r', 'bold' => true],
            ['c' => 13, 's' => 2, 't' => pdfFmtMoney($grandTotals['out']), 'align' => 'r', 'bold' => true],
            ['c' => 17, 's' => 2, 't' => pdfFmtMoney($grandTotals['akhir']), 'align' => 'r', 'bold' => true],
        ], '#fff3a3');
    }
    return $p->finish();
}

/** Cetak Stok Tersedia: No, Kode, Nama, Qty, Satuan (tanpa harga); template "Laporan Stok Barang - Rekap". */
function fastPdfStockAvailable(array $rows, string $companyName): string
{
    $p = new FastPdfTable(printedAtLabel() . ', ' . printedByLabel());
    $p->setBorder(FastPdfTable::hex('#333333'));
    $p->text('Laporan Stok Barang - Rekap', 15, ['bold' => true, 'color' => '#C55A11', 'align' => 'c']);
    $p->text($companyName, 12, ['bold' => true, 'color' => '#0070C0', 'align' => 'c', 'after' => 8]);
    if (empty($rows)) {
        $p->text('Tidak ada stok tersedia yang cocok dengan filter ini.', 9);
        return $p->finish();
    }

    $p->setColumns([4.0, 13.0, 63.0, 10.0, 10.0]);
    $p->setHeader(function (FastPdfTable $p) {
        $p->headerBlock(
            [
                ['c' => 0, 't' => 'No', 'rows' => 2],
                ['c' => 1, 't' => 'Kode Barang', 'rows' => 2],
                ['c' => 2, 't' => 'Nama Barang', 'rows' => 2],
                ['c' => 3, 's' => 2, 't' => 'Stock Tersedia'],
            ],
            [
                ['c' => 3, 't' => 'Qty'],
                ['c' => 4, 't' => 'Satuan'],
            ]
        );
    });
    $p->header();
    foreach ($rows as $i => $r) {
        $p->row([
            ['c' => 0, 't' => (string) ($i + 1), 'align' => 'c'],
            ['c' => 1, 't' => ($r['item_code'] ?? '') !== '' ? (string) $r['item_code'] : '-'],
            ['c' => 2, 't' => stockReportItemLabel($r), 'wrap' => true],
            ['c' => 3, 't' => pdfFmtQty($r['saldo_akhir']), 'align' => 'r'],
            ['c' => 4, 't' => (string) $r['unit']],
        ]);
    }
    return $p->finish();
}
