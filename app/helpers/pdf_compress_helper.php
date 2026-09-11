<?php

/**
 * Kompres PDF hasil upload memakai Ghostscript -- dipakai HANYA oleh
 * app/helpers/upload_helper.php setelah file lolos SEMUA validasi keamanan
 * (MIME asli 'application/pdf', magic byte '%PDF-', nama file aman).
 *
 * Filosofi: JANGAN PERNAH menolak/merusak PDF pengguna gara-gara kompresi.
 * Kalau salah satu dari ini terjadi:
 *   - Ghostscript tidak terpasang di server,
 *   - proc_open dimatikan hosting,
 *   - proses gs gagal / menghasilkan file tidak valid,
 *   - hasilnya TIDAK lebih kecil dari aslinya,
 * maka PDF ASLI disalin apa adanya ke tujuan. Ghostscript hanya men-downsample
 * gambar di dalam PDF (default preset /ebook ~150 dpi) + membuang objek duplikat;
 * teks & struktur dokumen tetap utuh dan terbaca.
 */

/**
 * Simpan PDF ke $destPath, dikompres bila memungkinkan.
 *
 * @param string $srcPath   PDF sumber (biasanya $_FILES tmp_name yang sudah lolos validasi)
 * @param string $destPath  path file final (harus berakhiran .pdf)
 * @return array{compressed:bool, from:int, to:int}
 * @throws RuntimeException  HANYA kalau menyalin file asli pun gagal (disk penuh / permission)
 */
function compressPdfFile(string $srcPath, string $destPath): array
{
    $srcBytes = (int) @filesize($srcPath);

    $storeOriginal = static function () use ($srcPath, $destPath, $srcBytes): array {
        // $srcPath sudah dipastikan is_uploaded_file() oleh pemanggil -> copy() aman.
        if (!@copy($srcPath, $destPath)) {
            throw new RuntimeException('Gagal menyimpan file PDF ke server.');
        }
        @chmod($destPath, 0644);
        return ['compressed' => false, 'from' => $srcBytes, 'to' => $srcBytes];
    };

    if ($srcBytes <= 0 || !pdfCompressionAvailable()) {
        return $storeOriginal();
    }

    $tmpOut = $destPath . '.gs';
    @unlink($tmpOut);

    $args = [
        GHOSTSCRIPT_PATH,
        '-sDEVICE=pdfwrite',
        '-dCompatibilityLevel=1.4',
        '-dPDFSETTINGS=' . PDF_COMPRESS_PRESET,
        '-dNOPAUSE',
        '-dBATCH',
        '-dQUIET',
        '-dSAFER',
        '-dDetectDuplicateImages=true',
        '-sOutputFile=' . $tmpOut,
        $srcPath,
    ];
    $cmd = implode(' ', array_map('escapeshellarg', $args));

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $descriptors, $pipes);

    $exit = -1;
    if (is_resource($proc)) {
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
    }

    $valid = ($exit === 0)
        && is_file($tmpOut)
        && filesize($tmpOut) > 0
        && strncmp((string) file_get_contents($tmpOut, false, null, 0, 5), '%PDF-', 5) === 0;

    if ($valid) {
        $outBytes = (int) filesize($tmpOut);
        // Pakai hasil kompres HANYA kalau benar-benar lebih kecil (hemat >= 5%).
        // PDF berbasis teks yang sudah optimal sering malah membesar setelah
        // diproses ulang -> lebih baik simpan yang asli.
        if ($outBytes > 0 && $outBytes < (int) ($srcBytes * 0.95)) {
            @unlink($destPath);
            if (@rename($tmpOut, $destPath) || @copy($tmpOut, $destPath)) {
                @unlink($tmpOut);
                @chmod($destPath, 0644);
                return ['compressed' => true, 'from' => $srcBytes, 'to' => $outBytes];
            }
        }
    }

    @unlink($tmpOut);
    return $storeOriginal();
}

/**
 * Apakah Ghostscript benar-benar bisa dipanggil di server ini?
 * Hasilnya di-cache selama request (probe `gs --version` cuma sekali).
 */
function pdfCompressionAvailable(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    $ok = false;

    // function_exists() sudah otomatis false untuk fungsi yang dimatikan lewat
    // disable_functions (dicoba di produksi: proc_open TERSEDIA tapi
    // escapeshellarg DIMATIKAN hosting -- kalau cuma proc_open yang dicek,
    // baris escapeshellarg() di bawah fatal error "Call to undefined function"
    // dan meng-crash SETIAP upload PDF di seluruh app).
    if (!function_exists('proc_open') || !function_exists('escapeshellarg')) {
        return $ok;
    }

    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open(escapeshellarg(GHOSTSCRIPT_PATH) . ' --version', $descriptors, $pipes);
    if (!is_resource($proc)) {
        return $ok;
    }
    fclose($pipes[0]);
    $out = (string) stream_get_contents($pipes[1]);
    stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    // Output normal: "10.02.1\n" -- diawali angka versi.
    $ok = ($exit === 0) && (preg_match('/^\s*\d+\.\d+/', $out) === 1);
    return $ok;
}
