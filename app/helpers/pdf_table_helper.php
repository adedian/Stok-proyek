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

    /** Ukuran font isi tabel (pt) + padding vertikal sel. */
    public function setSize(float $size, ?float $padY = null): void
    {
        $this->size = $size;
        if ($padY !== null) {
            $this->padY = $padY;
        }
    }

    /** Header 1 baris: sel 'c','s','t','fill','color','align'. */
    public function headerRow(array $cells): void
    {
        $h = $this->lineHeight() + 2 * $this->padY;
        foreach ($cells as $cell) {
            $o = $cell + ['bold' => true, 'align' => 'c', 'fit' => true];
            $this->drawCell($this->colX($cell['c']), $this->y, $this->colW($cell['c'], $cell['s'] ?? 1), $h, (string) $cell['t'], $o);
        }
        $this->y += $h;
    }

    /** Batang berwarna selebar tabel dengan teks (mis. judul kelompok "PIC: ..."). */
    public function bar(string $text, string $fill, string $color = '#ffffff', bool $withHeader = true): void
    {
        $h = $this->lineHeight() + 2 * $this->padY + 2;
        // jangan tinggalkan batang sendirian di dasar halaman: sertakan ruang utk header + 1 baris
        $this->ensureSpace($h + 2 * ($this->lineHeight() + 2 * $this->padY), false);
        $w = $this->colW(0, count($this->cols));
        $this->drawCell($this->mL, $this->y, $w, $h, $text, ['fill' => $fill, 'color' => $color, 'bold' => true, 'size' => $this->size + 0.5, 'border' => false, 'lines' => [$text]]);
        $this->y += $h;
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

/**
 * Pembuka satu KATEGORI di laporan stok: batang judul "Kategori: X (n barang)" + header tabel.
 * Dipanggil setiap kali kategori berganti (tiap kategori = bagian/tabel sendiri).
 */
function pdfStockCategoryStart(FastPdfTable $p, string $name, int $count): void
{
    $p->gap(7);
    $p->bar('Kategori: ' . $name . '   (' . $count . ' barang)', '#1f4e79');
    $p->header();
}

/** Hitung jumlah baris per kategori: ['none'|id => n]. */
function pdfStockCategoryCounts(array $rows): array
{
    $out = [];
    foreach ($rows as $r) {
        $k = $r['category_id'] ?? 'none';
        $out[$k] = ($out[$k] ?? 0) + 1;
    }
    return $out;
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

    $perGroup = $showPrice ? 4 : 2;
    $akhirC = 4 + 3 * $perGroup;           // kolom pertama grup Saldo Akhir

    // Pengelompokan per KATEGORI (Master Kategori): tiap kategori = batang judul + tabel sendiri,
    // diakhiri subtotal nilai kategori (bila ada harga). Urutan grup sudah diatur model.
    $catCount = pdfStockCategoryCounts($groups);
    $curCat = false;
    $subName = '';
    $sub = ['in' => 0.0, 'out' => 0.0, 'akhir' => 0.0];
    $flushSub = function () use ($p, $showPrice, &$sub, &$subName) {
        if ($showPrice && $subName !== '') {
            $p->row([
                ['c' => 0, 's' => 10, 't' => 'Subtotal Kategori ' . $subName, 'align' => 'r', 'bold' => true, 'wrap' => true],
                ['c' => 10, 's' => 2, 't' => pdfFmtMoney($sub['in']), 'align' => 'r', 'bold' => true],
                ['c' => 14, 's' => 2, 't' => pdfFmtMoney($sub['out']), 'align' => 'r', 'bold' => true],
                ['c' => 18, 's' => 2, 't' => pdfFmtMoney($sub['akhir']), 'align' => 'r', 'bold' => true],
            ], '#e7f1ff');
        }
        $sub = ['in' => 0.0, 'out' => 0.0, 'akhir' => 0.0];
    };
    foreach ($groups as $g) {
        $ck = $g['category_id'] ?? 'none';
        if ($ck !== $curCat) {
            $flushSub();
            $curCat = $ck;
            $subName = (string) ($g['category_name'] ?? 'Belum Dikategorikan');
            pdfStockCategoryStart($p, $subName, $catCount[$ck]);
        }
        $sub['in']    += (float) ($g['in_total_value'] ?? 0);
        $sub['out']   += (float) ($g['out_total_value'] ?? 0);
        $sub['akhir'] += (float) ($g['saldo_akhir_value'] ?? 0);
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
    $flushSub();

    if ($showPrice) {
        $p->gap(4);
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

    $perGroup = $showPrice ? 4 : 2;
    $cIn = 3 + $perGroup;
    $cOut = 3 + 2 * $perGroup;
    $cAkhir = 3 + 3 * $perGroup;

    // Per KATEGORI (Master Kategori): batang judul + tabel sendiri, penomoran mulai 1 di tiap kategori,
    // subtotal nilai per kategori (bila ada harga). TOTAL KESELURUHAN tetap di akhir.
    $catCount = pdfStockCategoryCounts($rows);
    $curCat = false;
    $subName = '';
    $no = 0;
    $sub = ['in' => 0.0, 'out' => 0.0, 'akhir' => 0.0];
    $flushSub = function () use ($p, $showPrice, &$sub, &$subName) {
        if ($showPrice && $subName !== '') {
            $p->row([
                ['c' => 0, 's' => 9, 't' => 'Subtotal Kategori ' . $subName, 'align' => 'r', 'bold' => true, 'wrap' => true],
                ['c' => 9, 's' => 2, 't' => pdfFmtMoney($sub['in']), 'align' => 'r', 'bold' => true],
                ['c' => 13, 's' => 2, 't' => pdfFmtMoney($sub['out']), 'align' => 'r', 'bold' => true],
                ['c' => 17, 's' => 2, 't' => pdfFmtMoney($sub['akhir']), 'align' => 'r', 'bold' => true],
            ], '#e7f1ff');
        }
        $sub = ['in' => 0.0, 'out' => 0.0, 'akhir' => 0.0];
    };
    foreach ($rows as $r) {
        $ck = $r['category_id'] ?? 'none';
        if ($ck !== $curCat) {
            $flushSub();
            $curCat = $ck;
            $subName = (string) ($r['category_name'] ?? 'Belum Dikategorikan');
            $no = 0;
            pdfStockCategoryStart($p, $subName, $catCount[$ck]);
        }
        $no++;
        $sub['in']    += (float) ($r['in_value'] ?? 0);
        $sub['out']   += (float) ($r['out_value'] ?? 0);
        $sub['akhir'] += (float) ($r['saldo_akhir_value'] ?? 0);
        $unit = (string) $r['unit'];
        $priced = ($r['in_unit_price'] ?? null) !== null;
        $akhirPriced = ($r['saldo_akhir_price'] ?? null) !== null;
        $keluar = (float) $r['mutasi_keluar'];
        $cells = [
            ['c' => 0, 't' => (string) $no, 'align' => 'r'],
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
    $flushSub();

    if ($showPrice) {
        $p->gap(4);
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
    // Per KATEGORI (Master Kategori): batang judul + tabel sendiri, penomoran mulai 1 di tiap kategori.
    $catCount = pdfStockCategoryCounts($rows);
    $curCat = false;
    $no = 0;
    foreach ($rows as $r) {
        $ck = $r['category_id'] ?? 'none';
        if ($ck !== $curCat) {
            $curCat = $ck;
            $no = 0;
            pdfStockCategoryStart($p, (string) ($r['category_name'] ?? 'Belum Dikategorikan'), $catCount[$ck]);
        }
        $no++;
        $p->row([
            ['c' => 0, 't' => (string) $no, 'align' => 'c'],
            ['c' => 1, 't' => ($r['item_code'] ?? '') !== '' ? (string) $r['item_code'] : '-'],
            ['c' => 2, 't' => stockReportItemLabel($r), 'wrap' => true],
            ['c' => 3, 't' => pdfFmtQty($r['saldo_akhir']), 'align' => 'r'],
            ['c' => 4, 't' => (string) $r['unit']],
        ]);
    }
    return $p->finish();
}

/** Qty gaya Laporan Kas: bulat tanpa desimal; selain itu maksimal 2 desimal tanpa nol di belakang. */
function pdfFmtQtyKas($v): string
{
    $v = (float) $v;
    return $v == (int) $v ? number_format($v, 0, ',', '.') : rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
}

/** Judul tengah laporan Kas: judul (hitam, tebal), perusahaan & periode (biru). */
function pdfKasTitle(FastPdfTable $p, string $title, string $company, string $period, ?string $note = null): void
{
    $p->text($title, 14, ['bold' => true, 'align' => 'c']);
    $p->text($company, 10.5, ['bold' => true, 'color' => '#0070C0', 'align' => 'c']);
    $p->text($period, 9, ['color' => '#0070C0', 'align' => 'c', 'after' => $note === null ? 6 : 1]);
    if ($note !== null) {
        $p->text($note, 8.5, ['color' => '#6c757d', 'align' => 'c', 'after' => 6]);
    }
}

/**
 * Laporan Kas (buku kas kronologis, Rekap/Rincian; Kas [+Bank]). $ledger dari
 * CashController::combinedLedger(): saldo_awal, rows, saldo_akhir, [groups per akun].
 */
function fastPdfCashReport(array $ledger, string $company, string $periodText, string $reportTitle): string
{
    $p = new FastPdfTable(printedAtLabel() . ', ' . printedByLabel());
    $p->setSize(8, 3.0);
    pdfKasTitle($p, $reportTitle, $company, $periodText);
    $p->setColumns([7, 11.5, 30, 5, 8, 10, 10, 11, 9]);
    $p->setHeader(function (FastPdfTable $p) {
        $h = [];
        foreach (['Tgl', 'No Bukti', 'Uraian', 'Qty', 'Satuan', 'Masuk', 'Keluar', 'Saldo Akhir', 'PIC'] as $i => $t) {
            $h[] = ['c' => $i, 't' => $t, 'fill' => '#f1f3f5', 'align' => $i >= 3 && $i <= 7 ? 'r' : 'c'];
        }
        $p->headerRow($h);
    });
    $p->header();

    $grouped = !empty($ledger['groups']);
    $sections = $grouped ? $ledger['groups'] : [[
        'label' => null, 'saldo_awal' => $ledger['saldo_awal'], 'rows' => $ledger['rows'], 'saldo_akhir' => $ledger['saldo_akhir'],
    ]];
    foreach ($sections as $sec) {
        if ($sec['label'] !== null) {
            $p->row([['c' => 0, 's' => 9, 't' => (string) $sec['label'], 'bold' => true, 'color' => '#0a3a7a', 'wrap' => true]], '#cfe2ff');
        }
        $p->row([
            ['c' => 0, 's' => 7, 't' => 'Saldo Awal', 'align' => 'c', 'bold' => true],
            ['c' => 7, 't' => pdfFmtMoney($sec['saldo_awal']), 'align' => 'r', 'bold' => true],
        ], '#f1f3f5');
        if (empty($sec['rows'])) {
            $p->row([['c' => 0, 's' => 9, 't' => 'Tidak ada transaksi pada periode/filter ini.', 'align' => 'c', 'wrap' => true]]);
        }
        foreach ($sec['rows'] as $row) {
            $isBank = ($row['source'] ?? 'kas') === 'bank';
            $first = !empty($row['is_first']);
            $uraian = (string) $row['uraian'] . ($isBank && !$grouped ? ' (' . $row['kategori'] . ')' : '');
            $p->row([
                ['c' => 0, 't' => $first ? date('j-M-y', strtotime($row['trx_date_full'])) : ''],
                ['c' => 1, 't' => $first ? (string) $row['no_bukti_full'] : '', 'wrap' => true],
                ['c' => 2, 't' => $uraian, 'wrap' => true],
                ['c' => 3, 't' => $isBank ? '-' : pdfFmtQtyKas($row['qty']), 'align' => 'r'],
                ['c' => 4, 't' => $isBank ? '-' : pdfFmtMoney($row['satuan']), 'align' => 'r'],
                ['c' => 5, 't' => $row['masuk'] != 0 ? pdfFmtMoney($row['masuk']) : '', 'align' => 'r'],
                ['c' => 6, 't' => $row['keluar'] != 0 ? pdfFmtMoney($row['keluar']) : '', 'align' => 'r'],
                ['c' => 7, 't' => pdfFmtMoney($row['saldo']), 'align' => 'r'],
                ['c' => 8, 't' => $first ? (string) $row['pic_full'] : '', 'wrap' => true],
            ]);
        }
        if ($grouped) {
            $p->row([
                ['c' => 0, 's' => 5, 't' => 'Saldo Akhir ' . $sec['label'], 'align' => 'r', 'bold' => true, 'wrap' => true],
                ['c' => 5, 't' => pdfFmtMoney($sec['masuk']), 'align' => 'r', 'bold' => true],
                ['c' => 6, 't' => pdfFmtMoney($sec['keluar']), 'align' => 'r', 'bold' => true],
                ['c' => 7, 't' => pdfFmtMoney($sec['saldo_akhir']), 'align' => 'r', 'bold' => true],
            ], '#f1f3f5');
        }
    }
    $p->row([
        ['c' => 0, 's' => 7, 't' => $grouped ? 'Total Saldo Akhir (semua akun)' : 'Saldo Akhir', 'align' => 'r', 'bold' => true],
        ['c' => 7, 't' => pdfFmtMoney($ledger['saldo_akhir']), 'align' => 'r', 'bold' => true],
    ], '#f1f3f5');
    return $p->finish();
}

/**
 * Laporan Kas "Tarik Semua": per PIC (batang judul + tabel + subtotal) lalu GRAND TOTAL.
 * $groups dari CashController::buildGroupedByPic().
 */
function fastPdfCashGrouped(array $groups, float $grandMasuk, float $grandKeluar, string $company, string $periodText, string $reportTitle): string
{
    $p = new FastPdfTable(printedAtLabel() . ', ' . printedByLabel());
    $p->setSize(8, 3.0);
    pdfKasTitle($p, $reportTitle, $company, $periodText, 'Tarik Semua - dikelompokkan per PIC');
    $p->setColumns([9, 14, 17, 36, 12, 12]);
    if (empty($groups)) {
        $p->row([['c' => 0, 's' => 6, 't' => 'Tidak ada transaksi pada periode/filter ini.', 'align' => 'c', 'wrap' => true]]);
        return $p->finish();
    }
    $head = function (FastPdfTable $p) {
        $h = [];
        foreach (['Tanggal', 'No Bukti', 'Project', 'Uraian', 'Masuk', 'Keluar'] as $i => $t) {
            $h[] = ['c' => $i, 't' => $t, 'fill' => '#f1f3f5', 'align' => $i >= 4 ? 'r' : 'c'];
        }
        $p->headerRow($h);
    };
    $p->setHeader($head);
    foreach ($groups as $g) {
        $pic = mb_strtoupper((string) $g['pic']);
        $p->gap(6);
        $p->bar('PIC: ' . $pic, '#212529');
        $p->header();
        foreach ($g['rows'] as $row) {
            $project = (string) ($row['project_name_full'] ?? '');
            $p->row([
                ['c' => 0, 't' => !empty($row['trx_date_full']) ? date('j-M-y', strtotime($row['trx_date_full'])) : ''],
                ['c' => 1, 't' => (string) ($row['no_bukti_full'] ?? ''), 'wrap' => true],
                ['c' => 2, 't' => $project !== '' ? $project : '-', 'wrap' => true],
                ['c' => 3, 't' => (string) $row['uraian'], 'wrap' => true],
                ['c' => 4, 't' => $row['masuk'] != 0 ? pdfFmtMoney($row['masuk']) : '', 'align' => 'r'],
                ['c' => 5, 't' => $row['keluar'] != 0 ? pdfFmtMoney($row['keluar']) : '', 'align' => 'r'],
            ]);
        }
        $p->row([
            ['c' => 0, 's' => 4, 't' => 'Total PIC ' . $pic, 'align' => 'r', 'bold' => true, 'wrap' => true],
            ['c' => 4, 't' => pdfFmtMoney($g['masuk']), 'align' => 'r', 'bold' => true],
            ['c' => 5, 't' => pdfFmtMoney($g['keluar']), 'align' => 'r', 'bold' => true],
        ], '#f8f9fa');
    }

    // GRAND TOTAL
    $p->gap(10);
    $p->setHeader(function (FastPdfTable $p) {
        $p->headerRow([
            ['c' => 0, 's' => 4, 't' => 'GRAND TOTAL', 'fill' => '#212529', 'color' => '#ffffff'],
            ['c' => 4, 't' => 'Masuk', 'fill' => '#212529', 'color' => '#ffffff', 'align' => 'r'],
            ['c' => 5, 't' => 'Keluar', 'fill' => '#212529', 'color' => '#ffffff', 'align' => 'r'],
        ]);
    });
    $p->header();
    $p->row([
        ['c' => 0, 's' => 4, 't' => 'Total Masuk / Keluar / Saldo', 'bold' => true, 'wrap' => true],
        ['c' => 4, 't' => pdfFmtMoney($grandMasuk), 'align' => 'r', 'bold' => true],
        ['c' => 5, 't' => pdfFmtMoney($grandKeluar), 'align' => 'r', 'bold' => true],
    ]);
    $p->row([['c' => 0, 's' => 6, 't' => 'Saldo: ' . pdfFmtMoney($grandMasuk - $grandKeluar), 'align' => 'r', 'bold' => true]]);
    return $p->finish();
}
