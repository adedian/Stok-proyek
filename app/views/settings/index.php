<?php
$tabs = [
    'company'      => 'Profil Perusahaan',
    'bank'         => 'Rekening Bank',
    'numbering'    => 'Penomoran Dokumen',
    'session'      => 'Session',
    'notification' => 'Notifikasi',
    'permissions'  => 'Hak Akses',
    'backup'       => 'Backup Database',
];
?>
<div class="mb-3">
    <h4 class="mb-0">Pengaturan Sistem</h4>
    <small class="text-muted">Konfigurasi umum aplikasi -- khusus Super Admin</small>
</div>

<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => $label): ?>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === $key ? 'active' : '' ?>"
               href="<?= BASE_URL ?>/index.php?module=settings&tab=<?= e($key) ?>">
                <?= e($label) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php
$tabFile = ROOT_PATH . '/app/views/settings/_tab_' . preg_replace('/[^a-z_]/', '', $activeTab) . '.php';
if (!array_key_exists($activeTab, $tabs) || !file_exists($tabFile)) {
    $tabFile = ROOT_PATH . '/app/views/settings/_tab_company.php';
}
require $tabFile;
?>
