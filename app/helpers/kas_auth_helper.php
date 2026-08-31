<?php

/**
 * SECOND-LEVEL AUTH untuk modul Kas.
 *
 * Login aplikasi TIDAK otomatis memberi akses ke data Kas. Untuk role
 * NON-EXEMPT, membuka menu Kas meminta verifikasi PIC + Password Kas
 * (kredensial terpisah, tersimpan ter-hash di `user_pic_assignments`).
 *
 * Exempt (tanpa login Kas tambahan, tetap dicek role/permission backend):
 *   super_admin, accounting, project_manager.
 *   - super_admin / accounting : lihat SEMUA divisi Kas.
 *   - project_manager          : VIEW ONLY, HANYA divisi 'project'.
 *
 * Session Kas (`$_SESSION['kas_auth']`) BEDA dari session login aplikasi:
 * expired-nya session Kas TIDAK melogout user dari aplikasi utama.
 *
 * File ini di-load dari public/index.php (butuh getPDO() dari
 * config/database.php yang sudah lebih dulu di-include).
 */

/** Role yang tidak perlu verifikasi PIC Kas. */
function kasExemptRoles(): array
{
    return [ROLE_SUPER_ADMIN, ROLE_ACCOUNTING, ROLE_PROJECT_MANAGER];
}

function kasIsExemptRole(?string $roleSlug): bool
{
    return $roleSlug !== null && in_array($roleSlug, kasExemptRoles(), true);
}

/**
 * Timeout auto-lock session Kas (detik). Dibaca dari
 * system_settings.kas_session_timeout_minutes, fallback 20 menit, minimal 5.
 */
function kasSessionTimeout(): int
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $cached = 1200;
    try {
        $stmt = getPDO()->prepare(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'kas_session_timeout_minutes' LIMIT 1"
        );
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row && is_numeric($row['setting_value'])) {
            $cached = max(300, (int) $row['setting_value'] * 60);
        }
    } catch (Throwable $e) {
        // diamkan -- pakai fallback
    }
    return $cached;
}

/**
 * Divisi Kas dari sebuah role_slug (dipakai saat menyimpan transaksi &
 * menentukan cakupan lihat). Tetap konsisten dgn backfill migration.
 */
function kasDivisionForRole(?string $roleSlug): string
{
    switch ($roleSlug) {
        case ROLE_PIC_PROJECT:
        case ROLE_ADMIN_PROJECT:
        case ROLE_PROJECT_MANAGER:
        case ROLE_GUDANG:
            return 'project';
        case ROLE_ACCOUNTING:
            return 'accounting';
        case ROLE_PURCHASE:
            return 'purchase';
        default:
            return 'umum';
    }
}

/**
 * Cakupan divisi yang boleh DILIHAT user saat ini.
 *   null  = semua divisi (super_admin, accounting).
 *   array = daftar divisi (project_manager -> ['project']).
 * Untuk role ber-PIC (purchase/pic_project/admin_project) pembatasan utama
 * tetap lewat nama PIC (kasScopePicNames()), divisi tidak dibatasi lagi.
 */
function kasDivisionScope(): ?array
{
    $role = currentUserRole();
    if ($role === ROLE_PROJECT_MANAGER) {
        return ['project'];
    }
    return null;
}

/** Sudah lewat verifikasi PIC Kas? (exempt selalu true). */
function kasAuthenticated(): bool
{
    if (kasIsExemptRole(currentUserRole())) {
        return true;
    }
    if (empty($_SESSION['kas_auth']['ok'])) {
        return false;
    }
    if ((int) ($_SESSION['kas_auth']['account_id'] ?? 0) !== (int) currentUserId()) {
        return false; // session Kas milik akun lain -- jangan dipercaya
    }
    return true;
}

/**
 * Auto-lock: kalau session Kas idle melebihi timeout, buang HANYA session
 * Kas (bukan logout aplikasi). Return true kalau barusan expired.
 */
function kasCheckTimeout(): bool
{
    if (empty($_SESSION['kas_auth']['ok'])) {
        return false;
    }
    $last = (int) ($_SESSION['kas_auth']['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > kasSessionTimeout()) {
        $picName = $_SESSION['kas_auth']['pic_name'] ?? '-';
        unset($_SESSION['kas_auth']);
        if (class_exists('ActivityLog')) {
            (new ActivityLog())->log(currentUserId(), 'cash', 'kas_session_expired', "Session Kas PIC '{$picName}' kedaluwarsa (auto-lock)");
        }
        return true;
    }
    return false;
}

/** Perpanjang masa aktif session Kas (dipanggil tiap request Kas yang lolos). */
function kasTouch(): void
{
    if (!empty($_SESSION['kas_auth']['ok'])) {
        $_SESSION['kas_auth']['last_activity'] = time();
    }
}

function kasPicId(): ?int
{
    return isset($_SESSION['kas_auth']['pic_id']) ? (int) $_SESSION['kas_auth']['pic_id'] : null;
}

function kasPicName(): ?string
{
    return $_SESSION['kas_auth']['pic_name'] ?? null;
}

/**
 * Nama-nama PIC yang menjadi cakupan baris Kas user saat ini.
 *   null  = tak dibatasi PIC (super_admin, accounting, project_manager).
 *   array = tepat 1 nama PIC yang barusan diverifikasi (role ber-PIC).
 *   []    = role ber-PIC tapi belum verifikasi (harusnya dicegah gate).
 */
function kasScopePicNames(): ?array
{
    if (kasIsExemptRole(currentUserRole())) {
        return null;
    }
    $name = kasPicName();
    return $name !== null && $name !== '' ? [$name] : [];
}

// ---------------- Rate limiting login Kas (per session) ----------------

const KAS_LOGIN_MAX_FAILS   = 5;
const KAS_LOGIN_LOCK_SECONDS = 900; // 15 menit

/** Epoch batas akhir lock, atau null kalau tidak sedang terkunci. */
function kasLoginLockedUntil(): ?int
{
    $until = (int) ($_SESSION['kas_login_lock_until'] ?? 0);
    if ($until > time()) {
        return $until;
    }
    if ($until > 0) {
        unset($_SESSION['kas_login_lock_until'], $_SESSION['kas_login_fails']);
    }
    return null;
}

function kasRegisterFailedLogin(): void
{
    $n = (int) ($_SESSION['kas_login_fails'] ?? 0) + 1;
    $_SESSION['kas_login_fails'] = $n;
    if ($n >= KAS_LOGIN_MAX_FAILS) {
        $_SESSION['kas_login_lock_until'] = time() + KAS_LOGIN_LOCK_SECONDS;
    }
}

function kasClearFailedLogin(): void
{
    unset($_SESSION['kas_login_fails'], $_SESSION['kas_login_lock_until']);
}

function kasFailsRemaining(): int
{
    return max(0, KAS_LOGIN_MAX_FAILS - (int) ($_SESSION['kas_login_fails'] ?? 0));
}
