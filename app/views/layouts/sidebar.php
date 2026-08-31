<?php
// $activeModuleOverride: halaman yang di-serve controller lain tapi secara menu
// "milik" modul lain (mis. Laporan Kas di CashController -> highlight "Laporan").
$currentModule = $activeModuleOverride ?? ($_GET['module'] ?? 'dashboard');
$menus = appMenus();
?>
<div class="offcanvas-lg offcanvas-start sidebar bg-white border-end" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
    <div class="offcanvas-header d-lg-none border-bottom">
        <h5 class="offcanvas-title" id="appSidebarLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-3 pt-lg-3">
        <a href="<?= BASE_URL ?>/dashboard" class="sidebar-brand text-decoration-none d-none d-lg-flex">
            <span class="sidebar-brand-icon"><img src="<?= assetUrl('/assets/img/logo-hme.png') ?>" alt="Logo HME"></span>
            <span class="sidebar-brand-text">
                <span class="title d-block">STOK PROYEK</span>
                <span class="subtitle d-block">Sistem Kontrol Stok</span>
            </span>
        </a>
        <ul class="nav nav-pills flex-column gap-1">
            <?php $lastGroup = null; ?>
            <?php foreach ($menus as $menu): ?>
                <?php
                    // can() = matrix role yang bisa diedit admin + override per-user.
                    if (!can($menu['module'], 'view')) {
                        continue; // sembunyikan menu yang usernya tidak berhak buka
                    }
                    $isCurrent = $currentModule === $menu['module'];
                ?>
                <?php if ($menu['group'] !== $lastGroup): ?>
                    <?php $lastGroup = $menu['group']; ?>
                    <?php if ($lastGroup !== null): ?>
                        <li class="sidebar-group-label"><?= e($lastGroup) ?></li>
                    <?php endif; ?>
                <?php endif; ?>
                <li class="nav-item">
                    <?php if ($menu['active']): ?>
                        <a href="<?= BASE_URL ?>/<?= e($menu['module']) ?>"
                           class="nav-link <?= $isCurrent ? 'active' : 'text-dark' ?>"
                           title="<?= e($menu['label']) ?>">
                            <i class="bi <?= e($menu['icon']) ?>"></i> <span class="menu-label"><?= e($menu['label']) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="nav-link text-muted d-flex justify-content-between align-items-center"
                              style="cursor: not-allowed;" title="Modul akan dibangun di phase berikutnya">
                            <span><i class="bi <?= e($menu['icon']) ?>"></i> <span class="menu-label"><?= e($menu['label']) ?></span></span>
                            <span class="badge bg-light text-secondary border menu-label">soon</span>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
