<?php
/**
 * bin/make_pwa_icons.php  --  GENERATE IKON PWA dari logo-hme.png (CLI SAJA)
 * =========================================================================
 * Membuat ikon yang dibutuhkan manifest & iOS dari
 *   public/assets/img/logo-hme.png
 * ke folder
 *   public/assets/img/pwa/
 *
 *   icon-192.png            192x192  (purpose "any")   -- logo di atas latar brand
 *   icon-512.png            512x512  (purpose "any")
 *   icon-maskable-512.png   512x512  (purpose "maskable") -- logo diperkecil, ada safe-zone
 *   apple-touch-icon.png    180x180  (iOS, tanpa transparansi)
 *   favicon-32.png / 16     favicon kecil
 *
 * Jalankan ulang kapan pun logo diganti:
 *   php bin/make_pwa_icons.php
 *
 * Butuh ekstensi GD (sudah standar di PHP hosting Linux & XAMPP).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("403 - CLI only\n");
}
if (!extension_loaded('gd')) {
    fwrite(STDERR, "Ekstensi GD tidak aktif. Aktifkan dulu (php-gd).\n");
    exit(1);
}

$root   = dirname(__DIR__);
$srcPng = $root . '/public/assets/img/logo-hme.png';
$outDir = $root . '/public/assets/img/pwa';

if (!is_file($srcPng)) {
    fwrite(STDERR, "Tidak ketemu: $srcPng\n");
    exit(1);
}
if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Gagal buat folder: $outDir\n");
    exit(1);
}

// Warna latar brand (samakan dengan --brand-700 / --brand-900 di variables.css)
$BRAND = [0x1E, 0x3C, 0x72]; // #1E3C72

$src = imagecreatefrompng($srcPng);
if (!$src) {
    fwrite(STDERR, "Gagal baca PNG sumber.\n");
    exit(1);
}
$sw = imagesx($src);
$sh = imagesy($src);

/**
 * Render 1 ikon persegi: latar solid brand + logo di tengah dengan skala tertentu.
 * $scale 1.0 = logo memenuhi sisi; 0.6 = logo 60% (buat maskable safe-zone).
 */
function renderIcon($src, int $sw, int $sh, int $size, float $scale, array $brand, bool $opaque, string $out): void
{
    $im = imagecreatetruecolor($size, $size);
    imagealphablending($im, false);
    imagesavealpha($im, true);

    if ($opaque) {
        $bg = imagecolorallocate($im, $brand[0], $brand[1], $brand[2]);
        imagefilledrectangle($im, 0, 0, $size, $size, $bg);
    } else {
        // transparan penuh dulu
        $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
        imagefilledrectangle($im, 0, 0, $size, $size, $transparent);
        // lalu isi latar brand solid (ikon "any" tetap butuh latar sendiri)
        imagealphablending($im, true);
        $bg = imagecolorallocate($im, $brand[0], $brand[1], $brand[2]);
        imagefilledrectangle($im, 0, 0, $size, $size, $bg);
    }

    imagealphablending($im, true);

    $target = (int) round($size * $scale);
    $ratio  = min($target / $sw, $target / $sh);
    $dw = (int) round($sw * $ratio);
    $dh = (int) round($sh * $ratio);
    $dx = (int) round(($size - $dw) / 2);
    $dy = (int) round(($size - $dh) / 2);

    imagecopyresampled($im, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);

    imagepng($im, $out, 9);
    imagedestroy($im);
    fwrite(STDOUT, "  ok  " . basename($out) . "  ({$size}x{$size})\n");
}

fwrite(STDOUT, "Membuat ikon PWA di public/assets/img/pwa/ ...\n");

renderIcon($src, $sw, $sh, 192, 0.82, $BRAND, false, "$outDir/icon-192.png");
renderIcon($src, $sw, $sh, 512, 0.82, $BRAND, false, "$outDir/icon-512.png");
renderIcon($src, $sw, $sh, 512, 0.60, $BRAND, false, "$outDir/icon-maskable-512.png");
renderIcon($src, $sw, $sh, 180, 0.80, $BRAND, true,  "$outDir/apple-touch-icon.png");
renderIcon($src, $sw, $sh, 32,  0.90, $BRAND, false, "$outDir/favicon-32.png");
renderIcon($src, $sw, $sh, 16,  0.90, $BRAND, false, "$outDir/favicon-16.png");

imagedestroy($src);
fwrite(STDOUT, "Selesai.\n");
