<?php

/**
 * Helper pemrosesan gambar (auto-kompres + hardening) -- dipakai HANYA oleh
 * app/helpers/upload_helper.php setelah file lolos semua validasi keamanan.
 *
 * Tujuan:
 *  - Ukuran file lebih kecil tanpa merusak kualitas visual (JPEG/WEBP q=85, PNG lossless).
 *  - Resize gambar terlalu besar ke sisi maksimum IMG_MAX_DIMENSION (tidak pernah memperbesar).
 *  - Normalisasi orientasi EXIF (foto smartphone tidak lagi miring/terbalik).
 *  - Buang seluruh metadata (EXIF/GPS) -- re-encode GD otomatis tidak menyalin metadata.
 *  - Proteksi decompression bomb: tolak sebelum alokasi memori kalau dimensi/piksel ekstrem.
 *
 * Library: GD (bundled) -- sudah tersedia di server ini dengan dukungan JPEG/PNG/WEBP.
 * Imagick TIDAK terpasang, jadi tidak dipakai.
 */

// Sisi terpanjang maksimum untuk gambar yang disimpan aplikasi. 1920px cukup
// untuk semua kebutuhan (bukti transfer, foto barang, logo) dan tetap tajam.
if (!defined('IMG_MAX_DIMENSION')) {
    define('IMG_MAX_DIMENSION', 1920);
}
// Batas aman jumlah piksel & sisi -- lebih dari ini ditolak (image/decompression bomb).
// 40 MP masih memuat foto kamera 24 MP (6000x4000) dengan lega.
if (!defined('IMG_MAX_PIXELS')) {
    define('IMG_MAX_PIXELS', 40000000);
}
if (!defined('IMG_MAX_SIDE')) {
    define('IMG_MAX_SIDE', 25000);
}
if (!defined('IMG_JPEG_QUALITY')) {
    define('IMG_JPEG_QUALITY', 85);
}
if (!defined('IMG_WEBP_QUALITY')) {
    define('IMG_WEBP_QUALITY', 85);
}
// PNG: 0-9 level kompresi DEFLATE (lossless, tidak merusak gambar). 6 = seimbang.
if (!defined('IMG_PNG_COMPRESSION')) {
    define('IMG_PNG_COMPRESSION', 6);
}

/**
 * MIME gambar yang bisa diproses helper ini.
 */
function imageHelperSupportedMimes(): array
{
    return ['image/jpeg', 'image/png', 'image/webp'];
}

/**
 * Proses & kompres satu file gambar.
 *
 * @param string $srcTmpPath  path file sumber (biasanya $_FILES tmp_name yang sudah lolos validasi)
 * @param string $mime        MIME hasil deteksi server-side (finfo), salah satu dari imageHelperSupportedMimes()
 * @param string $destPath    path tujuan file final (ekstensi harus cocok dengan $mime)
 * @return array{width:int,height:int,bytes:int}
 * @throws RuntimeException   kalau gambar rusak / terlalu besar / gagal diproses
 */
function compressImageFile(string $srcTmpPath, string $mime, string $destPath): array
{
    if (!function_exists('gd_info')) {
        throw new RuntimeException('Ekstensi GD tidak aktif di server, gambar tidak bisa diproses.');
    }
    if (!in_array($mime, imageHelperSupportedMimes(), true)) {
        throw new RuntimeException('Format gambar tidak didukung untuk kompresi.');
    }

    // --- 1. Baca dimensi TANPA mengalokasi bitmap penuh (proteksi bomb) ---
    $info = @getimagesize($srcTmpPath);
    if ($info === false) {
        throw new RuntimeException('File bukan gambar yang valid.');
    }
    [$srcW, $srcH] = $info;
    $srcW = (int) $srcW;
    $srcH = (int) $srcH;
    if ($srcW < 1 || $srcH < 1) {
        throw new RuntimeException('Dimensi gambar tidak valid.');
    }
    if ($srcW > IMG_MAX_SIDE || $srcH > IMG_MAX_SIDE || ($srcW * $srcH) > IMG_MAX_PIXELS) {
        throw new RuntimeException('Resolusi gambar terlalu besar. Maksimum ' . IMG_MAX_PIXELS . ' piksel.');
    }

    // Perkiraan kebutuhan memori (4 byte/piksel + overhead GD). Tolak lebih awal
    // supaya tidak memicu fatal "Allowed memory size exhausted".
    $estBytes = $srcW * $srcH * 4 * 2;
    $memLimit = imageHelperMemoryLimitBytes();
    if ($memLimit > 0 && $estBytes > ($memLimit * 0.7)) {
        throw new RuntimeException('Gambar terlalu besar untuk diproses server. Perkecil resolusi lalu coba lagi.');
    }

    // --- 2. Decode ---
    $src = imageHelperCreateFrom($srcTmpPath, $mime);
    if (!$src) {
        throw new RuntimeException('Gambar rusak atau tidak bisa dibaca.');
    }

    // --- 3. Normalisasi orientasi EXIF (khusus JPEG) ---
    if ($mime === 'image/jpeg') {
        $src = imageHelperApplyExifOrientation($src, $srcTmpPath);
        $srcW = imagesx($src);
        $srcH = imagesy($src);
    }

    // --- 4. Hitung target (resize hanya kalau lebih besar dari batas, tidak pernah memperbesar) ---
    $scale = min(1.0, IMG_MAX_DIMENSION / max($srcW, $srcH));
    $dstW = max(1, (int) round($srcW * $scale));
    $dstH = max(1, (int) round($srcH * $scale));

    // --- 5. Resample berkualitas tinggi ---
    $dst = imagecreatetruecolor($dstW, $dstH);
    if ($mime === 'image/png' || $mime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);

    // --- 6. Encode ke file final (metadata TIDAK ikut -- GD tidak menyalinnya) ---
    $ok = false;
    switch ($mime) {
        case 'image/jpeg':
            $flat = imagecreatetruecolor($dstW, $dstH);
            imagefilledrectangle($flat, 0, 0, $dstW, $dstH, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $dst, 0, 0, 0, 0, $dstW, $dstH);
            imagedestroy($dst);
            imageinterlace($flat, true); // progressive JPEG -> render lebih cepat
            $ok = imagejpeg($flat, $destPath, IMG_JPEG_QUALITY);
            imagedestroy($flat);
            break;
        case 'image/png':
            imagesavealpha($dst, true);
            $ok = imagepng($dst, $destPath, IMG_PNG_COMPRESSION);
            imagedestroy($dst);
            break;
        case 'image/webp':
            $ok = imagewebp($dst, $destPath, IMG_WEBP_QUALITY);
            imagedestroy($dst);
            break;
    }

    if (!$ok || !is_file($destPath)) {
        @unlink($destPath);
        throw new RuntimeException('Gagal menyimpan gambar hasil kompresi.');
    }

    return [
        'width'  => $dstW,
        'height' => $dstH,
        'bytes'  => (int) filesize($destPath),
    ];
}

/**
 * Decode file jadi resource GD sesuai MIME. Warning GD di-suppress supaya
 * file rusak jatuh ke pesan error kita sendiri, bukan notice mentah.
 */
function imageHelperCreateFrom(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            return @imagecreatefromjpeg($path);
        case 'image/png':
            return @imagecreatefrompng($path);
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    }
    return false;
}

/**
 * Putar/flip gambar sesuai tag EXIF Orientation supaya foto smartphone tidak
 * miring/terbalik setelah tersimpan. Setelah ini tidak ada lagi metadata rotasi.
 */
function imageHelperApplyExifOrientation($image, string $path)
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($path);
    if (!$exif || empty($exif['Orientation'])) {
        return $image;
    }

    $orientation = (int) $exif['Orientation'];
    switch ($orientation) {
        case 2:
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            $image = imagerotate($image, 180, 0);
            break;
        case 4:
            imageflip($image, IMG_FLIP_VERTICAL);
            break;
        case 5:
            $image = imagerotate($image, -90, 0);
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;
        case 6:
            $image = imagerotate($image, -90, 0);
            break;
        case 7:
            $image = imagerotate($image, 90, 0);
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;
        case 8:
            $image = imagerotate($image, 90, 0);
            break;
    }
    return $image;
}

/**
 * memory_limit server dalam byte (-1 / 0 = tak terbatas).
 */
function imageHelperMemoryLimitBytes(): int
{
    $raw = trim((string) ini_get('memory_limit'));
    if ($raw === '' || $raw === '-1') {
        return 0;
    }
    $unit = strtolower(substr($raw, -1));
    $num = (int) $raw;
    switch ($unit) {
        case 'g':
            return $num * 1024 * 1024 * 1024;
        case 'm':
            return $num * 1024 * 1024;
        case 'k':
            return $num * 1024;
        default:
            return $num;
    }
}
