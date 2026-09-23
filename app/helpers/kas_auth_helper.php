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

/** Label manusiawi untuk sebuah divisi Kas. */
function kasDivisionLabel(string $division): string
{
    return [
        'accounting' => 'Accounting',
        'purchase'   => 'Purchase',
        'project'    => 'Project',
        'umum'       => 'Umum',
    ][$division] ?? ucfirst($division);
}

// ===================== VALIDASI KAS =====================
// Routing validator berdasarkan divisi transaksi:
//   accounting -> role accounting ; purchase -> role purchase ;
//   project    -> role project_manager ; umum -> hanya super_admin.
// super_admin selalu boleh memvalidasi semua.

/** Daftar divisi yang boleh divalidasi oleh sebuah role. */
function kasValidatableDivisions(?string $roleSlug): array
{
    if ($roleSlug === ROLE_SUPER_ADMIN) {
        return ['accounting', 'purchase', 'project', 'umum'];
    }
    switch ($roleSlug) {
        case ROLE_ACCOUNTING:      return ['accounting'];
        case ROLE_PURCHASE:        return ['purchase'];
        case ROLE_PROJECT_MANAGER: return ['project'];
        default:                   return [];
    }
}

/** Boleh-tidaknya role ini memvalidasi transaksi dengan divisi tertentu. */
function kasCanValidateDivision(?string $roleSlug, string $division): bool
{
    return in_array($division, kasValidatableDivisions($roleSlug), true);
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

// =========================================================================
// GERBANG PASSWORD AKUN SENDIRI, LALU FILTER PROJECT (Revisi Kas 23 Sep 2026)
//
// Role purchase / pic_project / admin_project TIDAK LAGI memakai gerbang PIC
// Kas di atas (kasLogin/kasAuthenticate) -- dialihkan ke gerbang ini.
//
// ATURAN FINAL (revisi 23 Sep 2026, menggantikan revisi 20 Sep 2026 di bawah):
// user TIDAK LAGI memilih Project SEBELUM login Kas -- gerbang HANYA minta
// PASSWORD LOGIN AKUN SENDIRI (bukan password terpisah). Setelah lolos,
// Project menjadi FILTER di dalam halaman Kas, diambil dari daftar akses
// (`project_user_access`, diatur Super Admin lewat Project > Akses) --
// BUKAN lagi 1 project yang "dikunci" di session. Lihat kasProjectScopeIds()
// di bawah: dipanggil ULANG dari DB tiap request (bukan dibaca dari session)
// supaya perubahan akses oleh Super Admin langsung berlaku tanpa perlu
// logout/login ulang.
//
// Purchase (BEDA dari pic_project/admin_project): selain project yang
// diberikan akses, SELALU ikut melihat "Kas Purchase" (division='purchase')
// company-wide, terlepas dari akses project apa pun -- lihat
// kasOwnDivisionBucket(). Kode gerbang PIC lama TETAP ADA di file & controller
// ini (tidak dihapus), sekadar tidak lagi dipanggil untuk 3 role tsb --
// kolom `pic` & No Bukti per-prefix tetap dipakai apa adanya saat MEMBUAT
// transaksi (dropdown PIC di form Kas, tidak berubah -- itu atribusi/
// penomoran, konsep terpisah dari Project yang sekarang menjadi kontrol akses).
//
// Session Kas-Project (`$_SESSION['kas_project_auth']`) TERPISAH dari
// `$_SESSION['kas_auth']` di atas -- keduanya tidak akan pernah terisi
// bersamaan untuk 1 akun (role menentukan gerbang mana yang berlaku). Session
// ini HANYA menandai "sudah verifikasi password", TIDAK lagi menyimpan
// project_id/project_name apa pun.
// =========================================================================

/** Role yang memakai gerbang Project+Password (bukan gerbang PIC+Password lama). */
function kasProjectGateRoles(): array
{
    return [ROLE_PURCHASE, ROLE_PIC_PROJECT, ROLE_ADMIN_PROJECT];
}

function kasIsProjectGateRole(?string $roleSlug): bool
{
    return $roleSlug !== null && in_array($roleSlug, kasProjectGateRoles(), true);
}

/** Sudah lewat verifikasi Project+Password? (role di luar gerbang ini selalu true). */
function kasProjectAuthenticated(): bool
{
    if (!kasIsProjectGateRole(currentUserRole())) {
        return true;
    }
    if (empty($_SESSION['kas_project_auth']['ok'])) {
        return false;
    }
    if ((int) ($_SESSION['kas_project_auth']['account_id'] ?? 0) !== (int) currentUserId()) {
        return false; // session milik akun lain -- jangan dipercaya
    }
    return true;
}

/**
 * Auto-lock idle (pakai timeout yang sama dengan gerbang PIC lama --
 * system_settings.kas_session_timeout_minutes). Return true kalau barusan expired.
 */
function kasProjectCheckTimeout(): bool
{
    if (empty($_SESSION['kas_project_auth']['ok'])) {
        return false;
    }
    $last = (int) ($_SESSION['kas_project_auth']['last_activity'] ?? 0);
    if ($last > 0 && (time() - $last) > kasSessionTimeout()) {
        unset($_SESSION['kas_project_auth']);
        if (class_exists('ActivityLog')) {
            (new ActivityLog())->log(currentUserId(), 'cash', 'kas_session_expired', 'Session Kas kedaluwarsa (auto-lock)');
        }
        return true;
    }
    return false;
}

function kasProjectTouch(): void
{
    if (!empty($_SESSION['kas_project_auth']['ok'])) {
        $_SESSION['kas_project_auth']['last_activity'] = time();
    }
}

/**
 * Project id yang diberikan akses ke user ini (dari `project_user_access`,
 * diatur Super Admin lewat Project > Akses) -- SELALU dibaca langsung dari
 * DB (bukan session) supaya perubahan akses langsung berlaku. Query mandiri
 * (bukan lewat model) supaya helper ini tetap berdiri sendiri seperti fungsi
 * lain di file ini.
 */
function kasAllowedProjectIds(int $userId): array
{
    $rows = getPDO()->prepare(
        "SELECT pua.project_id FROM project_user_access pua
           JOIN projects p ON p.id = pua.project_id AND p.deleted_at IS NULL
          WHERE pua.user_id = :uid AND pua.is_active = 1"
    );
    $rows->execute(['uid' => $userId]);
    return array_map('intval', array_column($rows->fetchAll(), 'project_id'));
}

/**
 * Cakupan project_id transaksi Kas yang boleh DILIHAT user saat ini lewat
 * gerbang ini (batas akses, BUKAN filter pilihan bebas -- lihat CashController
 * untuk bagaimana ini digabung dengan filter Project opsional & bucket divisi
 * Purchase). null = tidak dibatasi lewat gerbang ini (role di luar
 * kasProjectGateRoles(), scoping-nya tetap lewat kasScopePicNames() seperti
 * sebelumnya). array = daftar project_id yang diberikan akses (bisa kosong
 * kalau belum di-assign Super Admin sama sekali).
 */
function kasProjectScopeIds(): ?array
{
    if (!kasIsProjectGateRole(currentUserRole())) {
        return null;
    }
    return kasAllowedProjectIds((int) currentUserId());
}

/**
 * Bucket divisi yang SELALU ikut terlihat lepas dari akses Project (khusus
 * Purchase -- "Kas Purchase" company-wide). null untuk role gerbang Project
 * lain (pic_project/admin_project HANYA Kas Project, tanpa bucket ini).
 */
function kasOwnDivisionBucket(): ?string
{
    return currentUserRole() === ROLE_PURCHASE ? kasDivisionForRole(ROLE_PURCHASE) : null;
}

// ---------------- Rate limiting login Kas-Project (per session, namespace terpisah) ----------------

const KAS_PROJECT_LOGIN_MAX_FAILS    = 5;
const KAS_PROJECT_LOGIN_LOCK_SECONDS = 900; // 15 menit

function kasProjectLoginLockedUntil(): ?int
{
    $until = (int) ($_SESSION['kas_project_login_lock_until'] ?? 0);
    if ($until > time()) {
        return $until;
    }
    if ($until > 0) {
        unset($_SESSION['kas_project_login_lock_until'], $_SESSION['kas_project_login_fails']);
    }
    return null;
}

function kasProjectRegisterFailedLogin(): void
{
    $n = (int) ($_SESSION['kas_project_login_fails'] ?? 0) + 1;
    $_SESSION['kas_project_login_fails'] = $n;
    if ($n >= KAS_PROJECT_LOGIN_MAX_FAILS) {
        $_SESSION['kas_project_login_lock_until'] = time() + KAS_PROJECT_LOGIN_LOCK_SECONDS;
    }
}

function kasProjectClearFailedLogin(): void
{
    unset($_SESSION['kas_project_login_fails'], $_SESSION['kas_project_login_lock_until']);
}

function kasProjectFailsRemaining(): int
{
    return max(0, KAS_PROJECT_LOGIN_MAX_FAILS - (int) ($_SESSION['kas_project_login_fails'] ?? 0));
}
