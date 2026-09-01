<?php

/**
 * Helper "Hapus per Rentang Tanggal" -- dipakai action rangeDelete() di tiap
 * controller list transaksi. Fitur ini KHUSUS Super Admin: memindahkan (soft,
 * ke Tempat Sampah) SEMUA record satu modul yang tanggal dokumennya berada di
 * rentang tertentu -- termasuk yang di periode Tutup Bulan / terkait dokumen
 * lain (aman: cuma set deleted_at, tidak melanggar FK). Keterkaitan data baru
 * benar-benar dijaga saat hapus PERMANEN lewat Tempat Sampah > Kosongkan.
 * Hanya record yang error saat proses ('gagal') yang dilewati.
 */

/** Hard stop 403 kalau bukan Super Admin. */
function rangeDeleteGuardSuperAdmin(): void
{
    if (!hasRole([ROLE_SUPER_ADMIN])) {
        denyAccess('Hapus per rentang tanggal hanya untuk Super Admin.');
    }
}

/** [from, to] dari POST (format YYYY-MM-DD dari <input type="date">). */
function rangeDeleteReadDates(): array
{
    return [trim($_POST['range_from'] ?? ''), trim($_POST['range_to'] ?? '')];
}

/** NULL kalau valid; pesan error kalau tidak. */
function rangeDeleteValidate(string $from, string $to): ?string
{
    $re = '/^\d{4}-\d{2}-\d{2}$/';
    if (!preg_match($re, $from) || !preg_match($re, $to)) {
        return 'Rentang tanggal wajib diisi lengkap (Dari & Sampai).';
    }
    if ($from > $to) {
        return 'Tanggal "Dari" tidak boleh lebih besar dari "Sampai".';
    }
    return null;
}

/** Catat ringkasan hasil hapus rentang ke Activity Log. */
function rangeDeleteLog(string $module, string $from, string $to, int $deleted, int $skipped): void
{
    require_once ROOT_PATH . '/app/models/ActivityLog.php';
    (new ActivityLog())->log(
        currentUserId(),
        $module,
        'range_delete',
        "Hapus per rentang tanggal {$from} s/d {$to}: {$deleted} dipindah ke Tempat Sampah, {$skipped} dilewati."
    );
}

/**
 * Set flash hasil hapus rentang. $skipped: [alasan => jumlah] -- normalnya
 * cuma 'gagal' (record error saat diproses).
 */
function rangeDeleteFlash(int $deleted, array $skipped): void
{
    $skippedTotal = array_sum($skipped);

    if ($deleted === 0 && $skippedTotal === 0) {
        setFlash('info', 'Tidak ada data pada rentang tanggal itu.');
        return;
    }

    $msg = "{$deleted} data dipindahkan ke Tempat Sampah.";
    if ($skippedTotal > 0) {
        $parts = [];
        foreach ($skipped as $reason => $count) {
            $parts[] = "{$reason}: {$count}";
        }
        $msg .= " {$skippedTotal} dilewati (" . implode(', ', $parts) . ").";
    }
    setFlash($skippedTotal > 0 ? 'warning' : 'success', $msg);
}
