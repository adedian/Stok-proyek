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

// =========================================================================
// KOMPRESI DENGAN BATAS UKURAN KETAT (dipakai Pusat Informasi)
// =========================================================================
// Beda dengan compressImageFile() di atas (yang menerima hasil di atas target kalau
// target tidak tercapai), fungsi di bawah MENJAMIN file hasil < $maxBytes atau
// melempar RuntimeException -- tidak pernah menyimpan file yang melanggar batas.
//
// Strategi: dimensi awal <= IMG_MAX_DIMENSION (tak pernah memperbesar) + orientasi EXIF
// dinormalisasi + metadata dibuang. Lalu per putaran: turunkan kualitas (JPEG/WebP) dari
// tinggi ke rendah; kalau masih di atas batas, kecilkan dimensi bertahap (rasio aspek
// dipertahankan; langkah ditaksir dari ukuran terakhir) dan ulangi, sampai muat atau
// menyentuh dimensi minimum. Gambar kecil yang sudah muat selesai di percobaan pertama
// (kualitas tinggi, tanpa pengecilan).
//   JPEG -> JPEG.   WebP -> WebP.
//   PNG tanpa transparansi -> tetap PNG bila muat, kalau tidak dikonversi ke JPEG.
//   PNG transparan         -> tetap PNG (dikecilkan dimensinya, transparansi utuh); bila belum
//                             muat dan GD mendukung WebP -> WebP (transparansi tetap utuh).
//                             Tidak pernah diratakan ke latar putih.

/** Dimensi sisi terpanjang terkecil yang masih diterima demi mengejar batas (px). */
if (!defined('IMG_STRICT_MIN_SIDE')) {
    define('IMG_STRICT_MIN_SIDE', 600);
}
/** Kualitas terendah JPEG/WebP pada jalur ketat. */
if (!defined('IMG_STRICT_MIN_QUALITY')) {
    define('IMG_STRICT_MIN_QUALITY', 40);
}

/**
 * Kompres $srcPath ke file di $destBase + ekstensi hasil (jpg/png/webp), ukuran < $maxBytes.
 *
 * @param string $srcPath   file sumber yang SUDAH lolos validasi MIME/struktur (uploadInspectFile)
 * @param string $mime      image/jpeg | image/png | image/webp
 * @param string $destBase  path tujuan TANPA ekstensi
 * @return array{path:string,ext:string,mime:string,width:int,height:int,bytes:int,converted:bool,has_alpha:bool}
 * @throws RuntimeException kalau gambar rusak/terlalu besar, GD tak tersedia, atau batas tak bisa dicapai
 */
function compressImageToLimit(string $srcPath, string $mime, string $destBase, int $maxBytes): array
{
    if (!function_exists('gd_info')) {
        throw new RuntimeException('Ekstensi GD tidak aktif di server, gambar tidak bisa dikompres. Hubungi administrator.');
    }
    if (!in_array($mime, imageHelperSupportedMimes(), true)) {
        throw new RuntimeException('Format gambar tidak didukung untuk kompresi.');
    }
    if ($mime === 'image/webp' && !function_exists('imagecreatefromwebp')) {
        throw new RuntimeException('Server belum mendukung gambar WebP. Gunakan JPG atau PNG.');
    }

    // --- Proteksi decompression bomb (sebelum alokasi bitmap) ---
    $info = @getimagesize($srcPath);
    if ($info === false) {
        throw new RuntimeException('File bukan gambar yang valid.');
    }
    $srcW = (int) $info[0];
    $srcH = (int) $info[1];
    if ($srcW < 1 || $srcH < 1) {
        throw new RuntimeException('Dimensi gambar tidak valid.');
    }
    if ($srcW > IMG_MAX_SIDE || $srcH > IMG_MAX_SIDE || ($srcW * $srcH) > IMG_MAX_PIXELS) {
        throw new RuntimeException('Resolusi gambar terlalu besar (maksimum ' . number_format(IMG_MAX_PIXELS / 1000000, 0) . ' megapiksel). Perkecil gambar lalu coba lagi.');
    }
    $memLimit = imageHelperMemoryLimitBytes();
    if ($memLimit > 0 && ($srcW * $srcH * 4 * 2) > ($memLimit * 0.7)) {
        throw new RuntimeException('Gambar terlalu besar untuk diproses server. Perkecil resolusi lalu coba lagi.');
    }

    if ($mime === 'image/jpeg' && !imageHelperJpegIsComplete($srcPath)) {
        // GD diam-diam menerima JPEG terpotong (bagian bawah jadi abu-abu) -- tolak sebagai rusak.
        throw new RuntimeException('Gambar rusak atau tidak lengkap (terpotong). Silakan unggah ulang file aslinya.');
    }
    $src = imageHelperCreateFrom($srcPath, $mime);
    if (!$src) {
        throw new RuntimeException('Gambar rusak atau tidak bisa dibaca.');
    }

    $tmpPath = $destBase . '.part';
    try {
        if ($mime === 'image/jpeg') {
            $rotated = imageHelperApplyExifOrientation($src, $srcPath);
            if (!$rotated) {
                throw new RuntimeException('Gambar tidak bisa diputar sesuai orientasinya.');
            }
            $src = $rotated;
        }
        $hasAlpha = false;
        if ($mime === 'image/png' || $mime === 'image/webp') {
            if (!imageistruecolor($src)) {
                imagepalettetotruecolor($src);
            }
            imagealphablending($src, false);
            imagesavealpha($src, true);
            $hasAlpha = imageHelperHasAlpha($src);
        }
        $w = imagesx($src);
        $h = imagesy($src);

        // Urutan percobaan: [format, ekstensi, sisi minimum, maks putaran pengecilan].
        $webpOk = function_exists('imagewebp');
        if ($mime === 'image/jpeg') {
            $plans = [['jpeg', 'jpg', IMG_STRICT_MIN_SIDE, 12]];
        } elseif ($mime === 'image/webp') {
            $plans = $webpOk
                ? [['webp', 'webp', IMG_STRICT_MIN_SIDE, 12]]
                : ($hasAlpha ? [['png', 'png', IMG_STRICT_MIN_SIDE, 12]] : [['jpeg', 'jpg', IMG_STRICT_MIN_SIDE, 12]]);
        } elseif ($hasAlpha) { // PNG transparan
            $plans = [['png', 'png', 1000, 12]];
            if ($webpOk) {
                $plans[] = ['webp', 'webp', IMG_STRICT_MIN_SIDE, 12];
            } else {
                $plans = [['png', 'png', IMG_STRICT_MIN_SIDE, 12]];
            }
        } else { // PNG opaque
            $plans = [['png', 'png', 0, 0], ['jpeg', 'jpg', IMG_STRICT_MIN_SIDE, 12]];
        }

        foreach ($plans as [$format, $ext, $minSide, $maxRounds]) {
            $res = imageHelperFitFormat($src, $w, $h, $format, $maxBytes, $tmpPath, $minSide, $maxRounds);
            if ($res === null) {
                continue;
            }
            $final = $destBase . '.' . $ext;
            if (!@rename($tmpPath, $final)) {
                throw new RuntimeException('Gagal menyimpan gambar hasil kompresi.');
            }
            clearstatcache(true, $final);
            $bytes = (int) filesize($final);
            if ($bytes <= 0 || $bytes >= $maxBytes) { // sabuk pengaman: ukuran dibaca ulang dari disk
                @unlink($final);
                throw new RuntimeException('Gagal menyimpan gambar hasil kompresi.');
            }
            return [
                'path'      => $final,
                'ext'       => $ext,
                'mime'      => ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext],
                'width'     => $res['width'],
                'height'    => $res['height'],
                'bytes'     => $bytes,
                'converted' => ($ext !== ($mime === 'image/jpeg' ? 'jpg' : ($mime === 'image/png' ? 'png' : 'webp'))),
                'has_alpha' => $hasAlpha,
            ];
        }
        throw new RuntimeException('Gambar tidak dapat dikompres hingga di bawah ' . imageHelperFormatKb($maxBytes)
            . ' tanpa membuatnya terlalu kecil/buram. Gunakan gambar yang lebih sederhana atau beresolusi lebih rendah.');
    } finally {
        if (is_resource($src) || $src instanceof GdImage) {
            imagedestroy($src);
        }
        if (is_file($tmpPath)) {
            @unlink($tmpPath);
        }
    }
}

/**
 * true kalau JPEG punya struktur segmen utuh sampai penanda EOI (FFD9) setelah data gambar.
 * Data tambahan SETELAH EOI (mis. foto bergerak/MPF dari kamera HP) tidak dipersoalkan.
 */
function imageHelperJpegIsComplete(string $path): bool
{
    $data = @file_get_contents($path);
    if ($data === false || strlen($data) < 4 || strncmp($data, "\xFF\xD8", 2) !== 0) {
        return false;
    }
    $len = strlen($data);
    $off = 2;
    while ($off + 4 <= $len) {
        if ($data[$off] !== "\xFF") {
            return false;
        }
        $marker = ord($data[$off + 1]);
        if ($marker === 0xFF) { // byte pengisi
            $off++;
            continue;
        }
        if ($marker === 0xDA) { // SOS -> mulai data gambar
            $segLen = unpack('n', substr($data, $off + 2, 2))[1] ?? 0;
            return strpos($data, "\xFF\xD9", $off + 2 + $segLen) !== false;
        }
        if ($marker === 0xD8 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
            $off += 2; // penanda tanpa panjang
            continue;
        }
        $segLen = unpack('n', substr($data, $off + 2, 2))[1] ?? 0;
        if ($segLen < 2) {
            return false;
        }
        $off += 2 + $segLen;
    }
    return false;
}

/** "299 KB" (desimal, 1 KB = 1000 byte) untuk pesan ke pengguna. */
function imageHelperFormatKb(int $bytes): string
{
    return number_format($bytes / 1000, 0, ',', '.') . ' KB';
}

/**
 * true kalau ada piksel yang tidak sepenuhnya buram. Diperiksa pada salinan kecil (<=512 px)
 * supaya cepat; hasil resample mencampur alpha, jadi piksel transparan sekecil apa pun
 * tetap menghasilkan alpha > 0 pada salinan itu.
 */
function imageHelperHasAlpha($img): bool
{
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1.0, 512 / max($w, $h));
    $tw = max(1, (int) round($w * $scale));
    $th = max(1, (int) round($h * $scale));
    $thumb = imagecreatetruecolor($tw, $th);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
    imagecopyresampled($thumb, $img, 0, 0, 0, 0, $tw, $th, $w, $h);
    $found = false;
    for ($y = 0; $y < $th && !$found; $y++) {
        for ($x = 0; $x < $tw; $x++) {
            if (((imagecolorat($thumb, $x, $y) >> 24) & 0x7F) > 0) {
                $found = true;
                break;
            }
        }
    }
    imagedestroy($thumb);
    return $found;
}

/**
 * Cari kombinasi (dimensi, kualitas) untuk SATU format sampai file < $maxBytes.
 * Hasil tertulis di $tmpPath. Return ['width','height'] atau null kalau tak tercapai.
 * $maxRounds = jumlah pengecilan dimensi tambahan yang boleh dicoba (0 = hanya ukuran awal).
 */
function imageHelperFitFormat($src, int $srcW, int $srcH, string $format, int $maxBytes, string $tmpPath, int $minSide, int $maxRounds): ?array
{
    $longest = max($srcW, $srcH);
    $side = min($longest, IMG_MAX_DIMENSION);
    $lossy = ($format === 'jpeg' || $format === 'webp');

    for ($round = 0; $round <= $maxRounds; $round++) {
        $scale = $side / $longest;
        $tw = max(1, (int) round($srcW * $scale));
        $th = max(1, (int) round($srcH * $scale));

        $scaled = imageHelperScaled($src, $srcW, $srcH, $tw, $th, $format);
        $qualities = !$lossy ? [null] : ($round === 0 ? [IMG_JPEG_QUALITY, 75, 65, 55, 46, IMG_STRICT_MIN_QUALITY]
                                                      : [80, 68, 56, 46, IMG_STRICT_MIN_QUALITY]);
        $lastBytes = 0;
        foreach ($qualities as $q) {
            @unlink($tmpPath);
            if ($format === 'jpeg') {
                imageinterlace($scaled, true);
                $ok = imagejpeg($scaled, $tmpPath, $q);
            } elseif ($format === 'webp') {
                $ok = imagewebp($scaled, $tmpPath, $q);
            } else {
                $ok = imagepng($scaled, $tmpPath, 9);
            }
            if (!$ok || !is_file($tmpPath)) {
                imagedestroy($scaled);
                throw new RuntimeException('Gagal menyimpan gambar hasil kompresi.');
            }
            clearstatcache(true, $tmpPath);
            $lastBytes = (int) filesize($tmpPath);
            if ($lastBytes > 0 && $lastBytes < $maxBytes) {
                imagedestroy($scaled);
                return ['width' => $tw, 'height' => $th];
            }
        }
        imagedestroy($scaled);

        if ($side <= $minSide || $round === $maxRounds) {
            break;
        }
        // Ukuran file kira-kira sebanding luas piksel -> taksir sisi berikutnya, langkah 50-85%.
        $ratio = sqrt(($maxBytes * 0.9) / max(1, $lastBytes));
        $ratio = max(0.5, min(0.85, $ratio));
        $side = max($minSide, (int) floor($side * $ratio));
    }
    @unlink($tmpPath);
    return null;
}

/** Salinan terskala $tw x $th. JPEG: latar putih (tak ada alpha). PNG/WebP: alpha dipertahankan. */
function imageHelperScaled($src, int $srcW, int $srcH, int $tw, int $th, string $format)
{
    $dst = imagecreatetruecolor($tw, $th);
    if ($format === 'jpeg') {
        imagefilledrectangle($dst, 0, 0, $tw, $th, imagecolorallocate($dst, 255, 255, 255));
        imagealphablending($dst, true);
    } else {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $srcW, $srcH);
    if ($format !== 'jpeg') {
        imagesavealpha($dst, true);
    }
    return $dst;
}
