<?php

/**
 * Helper export PDF (modul Laporan) -- membungkus Dompdf supaya controller
 * cukup lempar HTML tabel yang sama dipakai untuk tampilan/cetak browser.
 */

/**
 * Render HTML jadi PDF dan langsung stream ke browser.
 *
 * @param string $html      HTML lengkap (boleh sertakan <style> sendiri, Dompdf tidak baca CDN eksternal)
 * @param string $filename  nama file unduhan, tanpa ekstensi
 */
function streamPdf(string $html, string $filename): void
{
    $dompdf = new Dompdf\Dompdf([
        'isRemoteEnabled' => false,
    ]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
    exit;
}

/**
 * Render PDF BERTAHAP (potongan demi potongan) ke SATU dokumen, lalu stream.
 *
 * Kenapa: Dompdf membangun seluruh pohon frame dokumen di memori sebelum menggambar,
 * dan tabel besar sangat boros (~30 KB per sel). Laporan Stok "Cetak Detail" dengan
 * semua data (ratusan barang x puluhan transaksi x 20 kolom) bisa menembus
 * memory_limit hosting / timeout -> unduhan gagal ("tidak bisa dipakai"). Di sini tiap
 * potongan dirender oleh instance Dompdf sendiri (pohon frame dibuang setelah selesai,
 * jadi puncak memori = ukuran SATU potongan), sedangkan kanvas CPDF dipakai bersama
 * supaya semua halaman masuk ke satu file PDF. Setiap potongan mulai di halaman baru.
 *
 * @param int      $chunkCount   jumlah potongan (>= 1)
 * @param callable $htmlForChunk fn(int $index): string -- HTML lengkap potongan ke-$index
 * @param string   $filename     nama file unduhan, tanpa ekstensi
 */
function streamPdfChunked(int $chunkCount, callable $htmlForChunk, string $filename): void
{
    // Laporan besar boleh lebih lama dari batas default 30 dtk (best effort: sebagian
    // hosting melarang set_time_limit -> diabaikan saja).
    if (function_exists('set_time_limit')) {
        @set_time_limit(300);
    }

    $dompdf = null;
    $shared = null;
    for ($i = 0; $i < max(1, $chunkCount); $i++) {
        $html = $htmlForChunk($i);

        $dompdf = new Dompdf\Dompdf(['isRemoteEnabled' => false]);
        $dompdf->setPaper('A4', 'landscape');
        if ($shared !== null) {
            $shared->new_page();            // potongan berikutnya mulai di halaman baru
            $dompdf->setCanvas($shared);
            $dompdf->getFontMetrics()->setCanvas($shared);
        }
        $dompdf->loadHtml($html);
        $dompdf->render();
        $shared = $dompdf->getCanvas();

        unset($html);
        if ($i < $chunkCount - 1) {
            // Buang pohon frame potongan ini sebelum potongan berikut (referensi melingkar).
            $dompdf = null;
            gc_collect_cycles();
        }
    }

    $dompdf->stream($filename . '.pdf', ['Attachment' => true]);
    exit;
}

/**
 * Bagi daftar $items menjadi potongan berurutan; tiap item punya "bobot" (mis. jumlah
 * baris tabel yang dihasilkannya) dan satu potongan diisi sampai ~$budget. Item tidak
 * pernah dipecah (satu barang selalu utuh di satu potongan).
 *
 * @return array<int, array{items: array, offset: int}> offset = jumlah item sebelum potongan
 */
function splitIntoPdfChunks(array $items, callable $weightOf, int $budget): array
{
    $chunks = [];
    $cur = [];
    $curWeight = 0;
    $offset = 0;
    $consumed = 0;
    foreach ($items as $item) {
        $w = max(1, (int) $weightOf($item));
        if (!empty($cur) && $curWeight + $w > $budget) {
            $chunks[] = ['items' => $cur, 'offset' => $offset];
            $offset = $consumed;
            $cur = [];
            $curWeight = 0;
        }
        $cur[] = $item;
        $curWeight += $w;
        $consumed++;
    }
    if (!empty($cur) || empty($chunks)) {
        $chunks[] = ['items' => $cur, 'offset' => $offset];
    }
    return $chunks;
}
