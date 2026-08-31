<?php

/**
 * Helper Tutup Bulan (period lock) -- SATU sumber cek untuk seluruh modul
 * transaksi. Jangan bikin logic lock sendiri-sendiri di tiap controller.
 *
 *   isPeriodClosed($module, $date)  -> bool
 *   assertPeriodOpen($module, $date, $redirModule, $redirAction, $params)
 *        -> hard stop (flash + redirect + exit) kalau periode terkunci.
 *   assertPeriodOpenJson($module, $date) -> hard stop JSON 403 (untuk endpoint AJAX).
 *
 * "Terkunci" = ada baris accounting_period_locks status='closed' untuk modul
 * tsb dengan period_end >= $date.
 */

function periodLockModuleLabel(string $module): string
{
    require_once ROOT_PATH . '/app/models/PeriodLock.php';
    return PeriodLock::MODULES[$module] ?? ucfirst(str_replace('_', ' ', $module));
}

/** [module => 'YYYY-MM-DD'] batas terkunci, di-cache 1x per request. */
function periodClosedEnds(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    try {
        require_once ROOT_PATH . '/app/models/PeriodLock.php';
        $cache = (new PeriodLock())->allClosedEnds();
    } catch (Throwable $e) {
        error_log('periodClosedEnds gagal (fallback: tidak ada lock): ' . $e->getMessage());
        $cache = [];
    }
    return $cache;
}

/**
 * Apakah transaksi $module bertanggal $date berada di periode yang sudah ditutup.
 * $date: 'YYYY-MM-DD' (boleh juga 'YYYY-MM-DD HH:MM:SS' -- dipotong ke tanggal).
 */
function isPeriodClosed(string $module, string $date): bool
{
    $date = substr(trim($date), 0, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false; // tanggal tidak valid -> biarkan validasi normal yang menolak
    }
    $maxEnd = periodClosedEnds()[$module] ?? null;
    return $maxEnd !== null && $date <= $maxEnd;
}

/**
 * Gerbang server-side untuk CREATE/EDIT/DELETE. Panggil SETELAH validasi input,
 * SEBELUM menulis ke DB. Kalau terkunci: catat audit, set flash, redirect balik, exit.
 */
function assertPeriodOpen(
    string $module,
    string $date,
    string $redirModule,
    string $redirAction = 'index',
    array $redirParams = []
): void {
    if (!isPeriodClosed($module, $date)) {
        return;
    }

    if (class_exists('ActivityLog')) {
        (new ActivityLog())->log(
            currentUserId(),
            $module,
            'period_locked_reject',
            'Ditolak: transaksi ' . periodLockModuleLabel($module) . ' tanggal ' . $date . ' berada di periode yang sudah ditutup.'
        );
    }

    setFlash(
        'error',
        'Periode ' . periodLockModuleLabel($module) . ' untuk tanggal '
        . (function_exists('formatTanggal') ? formatTanggal($date) : $date)
        . ' sudah DITUTUP oleh Super Admin. Transaksi periode terkunci tidak dapat dibuat, diubah, atau dihapus.'
    );

    header('Location: ' . route($redirModule, $redirAction, $redirParams));
    exit;
}

/** Varian untuk endpoint yang membalas JSON (AJAX). Hard stop 403 JSON. */
function assertPeriodOpenJson(string $module, string $date): void
{
    if (!isPeriodClosed($module, $date)) {
        return;
    }
    if (class_exists('ActivityLog')) {
        (new ActivityLog())->log(
            currentUserId(),
            $module,
            'period_locked_reject',
            'Ditolak (AJAX): transaksi ' . periodLockModuleLabel($module) . ' tanggal ' . $date . ' di periode tertutup.'
        );
    }
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Periode ' . periodLockModuleLabel($module) . ' untuk tanggal ' . $date . ' sudah ditutup.',
    ]);
    exit;
}
