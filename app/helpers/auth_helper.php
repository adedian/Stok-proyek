<?php

/**
 * Helper terkait user yang sedang login
 */

function isLoggedIn(): bool
{
    return !empty($_SESSION['user_id']);
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentUserName(): string
{
    return $_SESSION['full_name'] ?? 'Guest';
}

/**
 * Path relatif foto profil user yang login (mis. "storage/uploads/profile_photos/x.jpg"),
 * atau null kalau tidak ada. Dipakai avatar topbar.
 *
 * Sesi yang dibuat SEBELUM fitur ini ada belum menyimpan 'profile_photo' -- ambil
 * sekali dari DB lalu cache di sesi supaya tidak perlu logout dulu.
 */
function currentUserPhoto(): ?string
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    if (!array_key_exists('profile_photo', $_SESSION)) {
        $_SESSION['profile_photo'] = null;
        try {
            require_once ROOT_PATH . '/app/models/User.php';
            $u = (new User())->find((int) $_SESSION['user_id']);
            if ($u && !empty($u['profile_photo'])) {
                $_SESSION['profile_photo'] = $u['profile_photo'];
            }
        } catch (Throwable $e) {
            // biarkan null -- avatar fallback ke inisial
        }
    }
    $p = $_SESSION['profile_photo'] ?? null;
    return ($p !== null && $p !== '') ? $p : null;
}

/**
 * Role Finance SAMA PERSIS dengan Accounting (permintaan 2026-10-05): semua cek hak akses,
 * cakupan data, menu, dan aturan Kas/Laporan memakai role EFEKTIF ini, jadi perubahan hak
 * Accounting otomatis ikut Finance tanpa menyalin aturan. Role asli tetap 'finance' di DB/sesi
 * (untuk tampilan & audit) -- lihat currentUserRealRole().
 */
function roleAlias(?string $slug): ?string
{
    return $slug === ROLE_FINANCE ? ROLE_ACCOUNTING : $slug;
}

/** Role EFEKTIF untuk pengecekan hak akses (Finance -> Accounting). */
function currentUserRole(): ?string
{
    return roleAlias($_SESSION['role_slug'] ?? null);
}

/** Role ASLI dari akun (mis. 'finance') -- hanya untuk tampilan label/audit, bukan untuk cek izin. */
function currentUserRealRole(): ?string
{
    return $_SESSION['role_slug'] ?? null;
}

function hasRole($roles): bool
{
    $roles = is_array($roles) ? $roles : [$roles];
    return in_array(currentUserRole(), $roles, true);
}
