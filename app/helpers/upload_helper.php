<?php

/**
 * Helper upload file generik + HARDENING + AUTO-KOMPRES GAMBAR.
 * Dipakai di modul Pembayaran (bukti transfer), Penerimaan Barang (foto/invoice),
 * Pembelian Offline, Tanda Tangan, Logo Perusahaan, Foto Profil, dst -- supaya
 * aturan validasi & pemrosesan konsisten di SATU tempat.
 *
 * Lapisan validasi (urut, gagal di lapisan manapun -> RuntimeException):
 *   1. Ada file? (opsional -> null)
 *   2. Kode error upload PHP
 *   3. is_uploaded_file() -- benar-benar dari HTTP upload, bukan path palsu
 *   4. Ukuran <= $maxSizeMB (SEBELUM diproses)
 *   5. Nama file tidak mengandung pola berbahaya (.php/.phtml/.phar/.svg/..)  -> tolak
 *   6. Ekstensi (dari nama) termasuk whitelist
 *   7. MIME asli (finfo_file, bukan $_FILES['type']) termasuk whitelist & cocok ekstensi
 *   8. GAMBAR: getimagesize() valid + tipe cocok MIME + batas dimensi (anti-bomb)
 *      PDF   : magic byte "%PDF-"
 *   9. Nama file final = 32 hex acak + ekstensi DARI MIME (bukan dari input user)
 *  10. GAMBAR -> di-resize/kompres/normalisasi EXIF/strip metadata (image_helper.php)
 *      PDF    -> disimpan apa adanya (TIDAK dikompres)
 *
 * FORMAT GAMBAR YANG DIIZINKAN: jpg, jpeg, png, webp (saja).
 * Format dokumen (pdf) hanya diterima jika 'pdf' eksplisit ada di $allowedExt.
 */

/**
 * @param string $inputName   nama field <input type="file"> di form
 * @param string $subFolder   sub-folder di dalam public/uploads/, misal 'payments'
 * @param array  $allowedExt  ekstensi yang diizinkan (lowercase, tanpa titik).
 *                             Hanya jpg/jpeg/png/webp/pdf yang dikenal; lainnya diabaikan.
 * @param int    $maxSizeMB   ukuran maksimum file SEBELUM kompresi, dalam MB
 * @return string|null        path relatif tersimpan (mis: 'uploads/payments/xxx.jpg') atau null jika tidak ada file
 * @throws RuntimeException   jika file ada tapi tidak valid
 */
function handleFileUpload(
    string $inputName,
    string $subFolder,
    array $allowedExt = ['jpg', 'jpeg', 'png', 'webp'],
    int $maxSizeMB = 5
): ?string {
    if (empty($_FILES[$inputName]) || !isset($_FILES[$inputName]['error'])) {
        return null;
    }

    $file = $_FILES[$inputName];

    // Field file yang dikirim sebagai array (multi-file) tidak didukung fungsi ini.
    if (is_array($file['error'])) {
        throw new RuntimeException('Field upload tidak valid.');
    }

    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // upload bersifat opsional
    }

    // --- 2. Error dari PHP saat menerima upload ---
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(uploadErrorMessage((int) $file['error']));
    }

    // --- 3. Pastikan benar-benar file hasil HTTP upload (cegah spoofing path) ---
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Sumber file tidak sah.');
    }

    // --- 4. Ukuran maksimum (sebelum diproses) ---
    $maxBytes = $maxSizeMB * 1024 * 1024;
    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        throw new RuntimeException("Ukuran file harus antara 1 byte dan {$maxSizeMB}MB.");
    }

    // --- 5. Nama file: tolak pola berbahaya & path traversal ---
    $originalName = (string) $file['name'];
    if ($originalName === '' || strpbrk($originalName, "/\\\0") !== false) {
        throw new RuntimeException('Nama file tidak valid.');
    }
    // Ekstensi/token berbahaya di MANA PUN dalam nama (mis. shell.php.jpg, foto.phtml,
    // arsip.jpg.php, gambar.svg) -- langsung tolak walau ekstensi terakhir "aman".
    if (preg_match('/\.(php\d?|phtml|phtm|pht|phps|phar|phhtml|inc|cgi|pl|py|rb|sh|bash|exe|bat|cmd|com|scr|jar|js|mjs|htm|html|xhtml|shtml|svg|svgz|xml|htaccess|htpasswd|bmp|dib|tif|tiff|gif|ico|heic|heif|jsp|asp|aspx)(\.|$)/i', $originalName)) {
        throw new RuntimeException('Nama atau tipe file mengandung pola yang tidak diizinkan.');
    }

    // --- 6. Ekstensi (dari nama) harus di whitelist yang dikenal ---
    $knownExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $allowedExt = array_values(array_intersect(array_map('strtolower', $allowedExt), $knownExt));
    if (empty($allowedExt)) {
        throw new RuntimeException('Konfigurasi tipe file tidak valid.');
    }
    $originalExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($originalExt === 'jpe') {
        $originalExt = 'jpeg';
    }
    if (!in_array($originalExt, $allowedExt, true)) {
        throw new RuntimeException('Tipe file tidak diizinkan. Hanya: ' . implode(', ', $allowedExt) . '.');
    }

    // --- 7. MIME asli isi file (server-side, bukan dari browser) ---
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
    unset($finfo);
    if (!$mime) {
        throw new RuntimeException('Tidak bisa memverifikasi tipe file.');
    }
    $mime = strtolower(trim($mime));

    // Peta MIME -> ekstensi kanonik. HANYA ini yang boleh lolos.
    $mimeToExt = [
        'image/jpeg'      => 'jpg',
        'image/pjpeg'     => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
        'application/pdf' => 'pdf',
    ];
    if (!isset($mimeToExt[$mime])) {
        throw new RuntimeException('Isi file bukan gambar (JPG/PNG/WEBP) atau PDF yang sah.');
    }
    $canonExt = $mimeToExt[$mime];

    // MIME harus sejalan dengan ekstensi nama (jpg<->jpeg dianggap sama).
    $extFamily = ($originalExt === 'jpeg') ? 'jpg' : $originalExt;
    if ($canonExt !== $extFamily) {
        throw new RuntimeException('Isi file tidak cocok dengan ekstensinya.');
    }
    // Dan tipe kanonik itu memang diizinkan pemanggil.
    $canonAllowed = in_array($canonExt, $allowedExt, true)
        || ($canonExt === 'jpg' && in_array('jpeg', $allowedExt, true));
    if (!$canonAllowed) {
        throw new RuntimeException('Tipe file tidak diizinkan di form ini.');
    }

    $isImage = in_array($mime, imageHelperSupportedMimes(), true);

    // --- 8. Validasi struktur nyata ---
    if ($isImage) {
        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo === false) {
            throw new RuntimeException('File gambar rusak atau tidak valid.');
        }
        $typeToMime = [
            IMAGETYPE_JPEG => 'image/jpeg',
            IMAGETYPE_PNG  => 'image/png',
            IMAGETYPE_WEBP => 'image/webp',
        ];
        if (($typeToMime[$imgInfo[2]] ?? null) !== $mime) {
            throw new RuntimeException('Struktur gambar tidak sesuai dengan tipenya.');
        }
    } else {
        // PDF: cek magic byte
        $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 5);
        if (strncmp($head, '%PDF-', 5) !== 0) {
            throw new RuntimeException('File PDF tidak valid.');
        }
    }

    // --- 9. Siapkan folder tujuan & nama acak (ekstensi dari MIME, bukan input user) ---
    $safeSub = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($subFolder, '/')));
    if ($safeSub === '') {
        throw new RuntimeException('Folder tujuan tidak valid.');
    }
    $destDir = UPLOAD_PATH . '/' . $safeSub;
    if (!is_dir($destDir) && !@mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        throw new RuntimeException('Gagal menyiapkan folder penyimpanan.');
    }

    $newFileName = bin2hex(random_bytes(16)) . '.' . $canonExt;
    $destPath = $destDir . '/' . $newFileName;

    // --- 10. Simpan: gambar -> kompres; PDF -> apa adanya ---
    if ($isImage) {
        // compressImageFile() menulis langsung ke $destPath dari tmp upload.
        compressImageFile($file['tmp_name'], $mime, $destPath);
        // tmp file dibersihkan otomatis oleh PHP di akhir request.
    } else {
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Gagal menyimpan file ke server.');
        }
        @chmod($destPath, 0644);
    }

    return 'uploads/' . $safeSub . '/' . $newFileName;
}

/**
 * Pesan ramah untuk kode error upload PHP (tanpa membocorkan detail server).
 */
function uploadErrorMessage(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Ukuran file melebihi batas yang diizinkan server.';
        case UPLOAD_ERR_PARTIAL:
            return 'File hanya terkirim sebagian, silakan ulangi.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'Server gagal memproses upload. Hubungi administrator.';
        default:
            return 'Terjadi kesalahan saat upload file.';
    }
}
