<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?><?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= assetUrl('/assets/img/logo-hme.png') ?>">

    <?php /* ---- PWA (Progressive Web App) ---- */ ?>
    <link rel="manifest" href="<?= BASE_URL ?>/manifest.webmanifest">
    <meta name="theme-color" content="#1E3C72">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HEXA STOK">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/pwa/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/pwa/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/img/pwa/favicon-16.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/variables.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/layout.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/topbar.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/sidebar.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/dashboard.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/cards.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/tables.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/forms.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/buttons.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/badges.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/modals.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/alerts.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/utilities.css') ?>" rel="stylesheet">
    <link href="<?= assetUrl('/assets/css/responsive.css') ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body>
<script>
    // Terapkan state collapse sidebar SEBELUM render pertama, supaya tidak ada
    // "kedipan" sidebar full-width lalu tiba-tiba menyusut.
    (function () {
        try {
            if (localStorage.getItem('sidebarCollapsed') === '1') {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (e) { /* localStorage tidak tersedia -- abaikan */ }
    })();
</script>
<?php
    require_once ROOT_PATH . '/app/models/DashboardStat.php';
    // $activeModuleOverride: lihat catatan di sidebar.php -- breadcrumb ikut modul
    // "pemilik" menu, bukan modul controller yang me-render halaman ini.
    $topbarCurrentModule = $activeModuleOverride ?? ($_GET['module'] ?? 'dashboard');
    $topbarGroup = menuGroupForModule($topbarCurrentModule);
    $topbarPageLabel = $pageTitle ?? (menuLabelForModule($topbarCurrentModule) ?? 'Dashboard');
    $topbarAlerts = isLoggedIn() ? (new DashboardStat())->topbarSummary() : [];

    $initials = '';
    foreach (explode(' ', trim(currentUserName())) as $part) {
        $initials .= mb_substr($part, 0, 1);
    }
    $initials = mb_strtoupper(mb_substr($initials, 0, 2));
    $avatarPhoto = function_exists('currentUserPhoto') ? currentUserPhoto() : null;
?>
<?php
/* Avatar user: foto profil kalau ada, kalau tidak fallback ke inisial. */
if (!function_exists('renderTopbarAvatar')) {
    function renderTopbarAvatar(?string $photo, string $initials): string
    {
        if ($photo) {
            return '<img src="' . BASE_URL . '/' . e($photo) . '" alt="" class="app-user-avatar">';
        }
        return '<span class="app-user-avatar">' . e($initials) . '</span>';
    }
}
?>
<nav class="navbar navbar-dark app-topbar px-3 sticky-top">
    <div class="d-flex align-items-center gap-3 min-w-0">
        <button type="button" id="btnSidebarToggle" class="btn-sidebar-toggle" aria-label="Buka/tutup menu">
            <i class="bi bi-list"></i>
        </button>
        <span class="navbar-brand mb-0 h1 d-none d-md-flex align-items-center gap-2">
            <img src="<?= assetUrl('/assets/img/logo-hme.png') ?>" alt="Logo HME" class="navbar-brand-logo">
            <span class="brand-full"><?= e(APP_NAME) ?></span>
            <span class="brand-short">HEXA</span>
        </span>
        <nav class="app-breadcrumb" aria-label="breadcrumb">
            <?php if ($topbarGroup): ?>
                <span class="crumb-group"><?= e($topbarGroup) ?></span>
                <span class="crumb-sep">/</span>
            <?php endif; ?>
            <span class="crumb-current"><?= e($topbarPageLabel) ?></span>
        </nav>
    </div>

    <div class="d-flex align-items-center text-light gap-1">
        <div class="dropdown">
            <button class="topbar-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi">
                <i class="bi bi-bell"></i>
                <?php if (!empty($topbarAlerts)): ?>
                    <span class="notif-dot"><?= count($topbarAlerts) ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end topbar-dropdown-menu">
                <div class="topbar-dropdown-header">Peringatan &amp; Informasi</div>
                <?php if (empty($topbarAlerts)): ?>
                    <div class="topbar-dropdown-empty">
                        <i class="bi bi-check2-circle fs-4 d-block mb-1"></i>
                        Tidak ada peringatan saat ini.
                    </div>
                <?php else: ?>
                    <?php foreach ($topbarAlerts as $alert): ?>
                        <a href="<?= e($alert['url'] ?? (BASE_URL . '/' . ($alert['module'] ?? 'dashboard'))) ?>" class="topbar-dropdown-item">
                            <span class="item-icon bg-<?= e($alert['variant']) ?>-subtle text-<?= e($alert['variant']) ?>">
                                <i class="bi <?= e($alert['icon']) ?>"></i>
                            </span>
                            <span>
                                <span class="d-block fw-semibold small"><?= e($alert['title']) ?></span>
                                <span class="d-block text-muted small"><?= e($alert['desc']) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="dropdown">
            <button class="btn btn-sm d-flex align-items-center gap-2 text-light border-0 bg-transparent" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <?= renderTopbarAvatar($avatarPhoto, $initials) ?>
                <span class="d-none d-sm-block text-start">
                    <span class="d-block small lh-1"><?= e(currentUserName()) ?></span>
                    <span class="d-block" style="font-size:.68rem; opacity:.75;"><?= e(roleSubtitle(currentUserRole())) ?></span>
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end topbar-dropdown-menu">
                <div class="profile-dropdown-header">
                    <?= renderTopbarAvatar($avatarPhoto, $initials) ?>
                    <span>
                        <span class="d-block fw-semibold"><?= e(currentUserName()) ?></span>
                        <span class="d-block small text-muted"><?= e(roleSubtitle(currentUserRole())) ?></span>
                    </span>
                </div>
                <a class="dropdown-item px-3 py-2" href="<?= BASE_URL ?>/account">
                    <i class="bi bi-person me-2"></i> Profile
                </a>
                <a class="dropdown-item px-3 py-2" href="<?= BASE_URL ?>/account">
                    <i class="bi bi-gear me-2"></i> Pengaturan Akun
                </a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item px-3 py-2 text-danger" href="#" id="topbarLogoutLink">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var logoutLink = document.getElementById('topbarLogoutLink');
        if (!logoutLink) { return; }
        logoutLink.addEventListener('click', function (e) {
            e.preventDefault();
            var url = '<?= route('auth', 'logout') ?>';
            if (window.confirmAction) {
                confirmAction('Yakin ingin logout?', 'Ya, logout').then(function (ok) {
                    if (ok) { window.location.href = url; }
                });
            } else if (confirm('Yakin ingin logout?')) {
                window.location.href = url;
            }
        });
    });
</script>
<div class="d-flex">
