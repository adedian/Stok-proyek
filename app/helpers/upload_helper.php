<?php

/**
 * Helper upload file generik.
 * Dipakai di modul Pembayaran (bukti transfer), Penerimaan Barang (foto),
 * Invoice, Pembelian Offline, dst -- supaya aturan validasi konsisten.
 */

/**
 * @param string $inputName   nama field <input type="file"> di form
 * @param string $subFolder   sub-folder di dalam public/uploads/, misal 'payments'
 * @param array  $allowedExt  ekstensi yang diizinkan (lowercase, tanpa titik)
 * @param int    $maxSizeMB   ukuran maksimum file dalam MB
 * @return string|null        path relatif tersimpan (mis: 'uploads/payments/xxx.jpg') atau null jika tidak ada file diupload
 * @throws RuntimeException   jika file ada tapi tidak valid
 */
function handleFileUpload(
    string $inputName,
    string $subFolder,
    array $allowedExt = ['jpg', 'jpeg', 'png', 'pdf'],
    int $maxSizeMB = 5
): ?string {
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // upload bersifat opsional, tidak error kalau kosong
    }

    $file = $_FILES[$inputName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Terjadi kesalahan saat upload file (kode: ' . $file['error'] . ').');
    }

    if ($file['size'] > $maxSizeMB * 1024 * 1024) {
        throw new RuntimeException("Ukuran file maksimal {$maxSizeMB}MB.");
    }

    $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($originalExt, $allowedExt, true)) {
        throw new RuntimeException('Tipe file tidak diizinkan. Hanya: ' . implode(', ', $allowedExt));
    }

    // Validasi MIME type sesungguhnya (bukan cuma dari ekstensi nama file)
    // Catatan: sejak PHP 8.5, finfo adalah objek (bukan resource) dan dibersihkan
    // otomatis oleh garbage collector, jadi finfo_close() tidak dipanggil lagi
    // (memanggilnya akan memicu deprecation notice).
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    unset($finfo);

    $allowedMime = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'pdf' => 'application/pdf',
    ];
    $expectedMime = $allowedMime[$originalExt] ?? null;
    if ($expectedMime && $mime !== $expectedMime) {
        throw new RuntimeException('Isi file tidak sesuai dengan ekstensinya.');
    }

    $destDir = UPLOAD_PATH . '/' . trim($subFolder, '/');
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }

    // Nama file acak -- cegah overwrite & path traversal, nama asli tidak dipakai sama sekali
    $newFileName = bin2hex(random_bytes(16)) . '.' . $originalExt;
    $destPath = $destDir . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Gagal menyimpan file ke server.');
    }

    return 'uploads/' . trim($subFolder, '/') . '/' . $newFileName;
}
