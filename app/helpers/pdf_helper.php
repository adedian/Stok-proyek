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
