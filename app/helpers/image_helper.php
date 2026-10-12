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
// Target ukuran file MAKSIMUM setelah kompresi. Kalau hasil encode masih
// lebih besar, kualitas JPEG/WebP diturunkan bertahap (85 -> 45), lalu bila
// perlu dimensi dikecilkan bertahap sampai muat atau menyentuh batas bawah.
// Default 300 KB (bukti transfer/foto barang tetap jelas terbaca). Override
// lewat config/local.php: 'img_target_max_kb' => 500
if (!defined('IMG_TARGET_MAX_BYTES')) {
    $__imgKb = (int) (($GLOBALS['__APP_LOCAL']['img_target_max_kb'] ?? 0));
    define('IMG_TARGET_MAX_BYTES', ($__imgKb > 0 ? $__imgKb : 300) * 1024);
}
// Jangan turunkan kualitas / perkecil di bawah ini demi mengejar target.
if (!defined('IMG_MIN_JPEG_QUALITY')) {
    define('IMG_MIN_JPEG_QUALITY', 45);
}
if (!defined('IMG_MIN_DIMENSION')) {
    define('IMG_MIN_DIMENSION', 900);
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

    // --- 4. Dimensi awal: turunkan ke sisi maks IMG_MAX_DIMENSION (tidak pernah memperbesar) ---
    $baseW = $srcW;
    $baseH = $srcH;
    $startScale = min(1.0, IMG_MAX_DIMENSION / max($srcW, $srcH));
    $dstW = max(1, (int) round($srcW * $startScale));
    $dstH = max(1, (int) round($srcH * $startScale));

    // --- 5 & 6. Encode dengan TARGET UKURAN.
    // Ulangi: turunkan kualitas JPEG/WebP (85 -> IMG_MIN_JPEG_QUALITY), lalu bila
    // masih kegedean kecilkan dimensi ~15% (batas bawah IMG_MIN_DIMENSION), sampai
    // file <= IMG_TARGET_MAX_BYTES atau sudah mentok. PNG: tak ada knob kualitas,
    // jadi hanya dikecilkan dimensinya. Gambar yang sudah kecil -> 1x encode saja.
    $quality = ($mime === 'image/webp') ? IMG_WEBP_QUALITY : IMG_JPEG_QUALITY;
    $ok = false;
    $attempt = 0;

    while (true) {
        $attempt++;

        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $baseW, $baseH);

        if ($mime === 'image/jpeg') {
            $flat = imagecreatetruecolor($dstW, $dstH);
            imagefilledrectangle($flat, 0, 0, $dstW, $dstH, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $dst, 0, 0, 0, 0, $dstW, $dstH);
            imagedestroy($dst);
            imageinterlace($flat, true); // progressive JPEG -> render lebih cepat
            $ok = imagejpeg($flat, $destPath, $quality);
            imagedestroy($flat);
        } elseif ($mime === 'image/png') {
            imagesavealpha($dst, true);
            $ok = imagepng($dst, $destPath, IMG_PNG_COMPRESSION);
            imagedestroy($dst);
        } else { // image/webp
            $ok = imagewebp($dst, $destPath, $quality);
            imagedestroy($dst);
        }

        if (!$ok || !is_file($destPath)) {
            imagedestroy($src);
            @unlink($destPath);
            throw new RuntimeException('Gagal menyimpan gambar hasil kompresi.');
        }

        $bytes = (int) filesize($destPath);
        if ($bytes <= IMG_TARGET_MAX_BYTES || $attempt >= 20) {
            break;
        }

        $isLossy = ($mime === 'image/jpeg' || $mime === 'image/webp');

        // Langkah 1: turunkan kualitas dulu (85 -> 45) tanpa menyentuh dimensi.
        if ($isLossy && $quality > IMG_MIN_JPEG_QUALITY) {
            $quality = max(IMG_MIN_JPEG_QUALITY, $quality - 10);
            continue;
        }
        // Langkah 2: kualitas sudah mentok -> kecilkan dimensi 20%, patok kualitas
        // di 58 (kompromi wajar), ulangi sampai <= target atau dimensi minimum.
        if (max($dstW, $dstH) > IMG_MIN_DIMENSION) {
            $dstW = max(1, (int) round($dstW * 0.8));
            $dstH = max(1, (int) round($dstH * 0.8));
            if ($isLossy) {
                $quality = 58;
            }
            continue;
        }
        break; // dimensi & kualitas sudah minimum -- terima apa adanya
    }

    imagedestroy($src);

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
