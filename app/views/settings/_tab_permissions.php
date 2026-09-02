<?php
/**
 * Hak Akses -- matrix RBAC yang bisa diedit (2026-09-01).
 * Centang = role boleh; hilangkan centang = tombol & menu terkait langsung
 * hilang untuk user role itu (berlaku di request berikutnya). Server-side
 * gate ikut otomatis lewat Middleware::requirePermission().
 *
 * Modul settings / user / trash DIKUNCI ke Super Admin -- ditampilkan
 * read-only, tidak bisa dicentang.
 */
$labels      = permissionLabelMaps();
$moduleLabel = $labels['modules'];
$actionLabel = $labels['actions'];
$roleLabelAll = roleLabelMap();

$editableRoles = permissionEditableRoleSlugs();
$catalog       = permissionActionCatalog();
?>
<div class="alert alert-light border small mb-3">
    <i class="bi bi-info-circle"></i>
    Centang aksi yang boleh dilakukan tiap role. Perubahan berlaku saat user membuka halaman berikutnya
    (tidak perlu login ulang). <strong>Super Admin</strong> selalu punya semua akses.
    Modul <strong>Pengaturan Sistem</strong>, <strong>User Management</strong>, dan <strong>Tempat Sampah</strong>
    dikunci ke Super Admin dan tidak bisa diubah. Butuh akses lebih spesifik per orang? Pakai panel
    <strong>Hak Akses</strong> saat menambah/mengubah user di User Management.
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=savePermissions">
    <?= csrfField() ?>

    <div class="d-flex justify-content-end mb-2">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save"></i> Simpan Perubahan
        </button>
    </div>

    <p class="perm-matrix-hint">
        <i class="bi bi-arrow-left-right"></i> Geser tabel ke samping untuk melihat semua role. Kolom <strong>Modul</strong> tetap menempel.
    </p>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 perm-matrix" data-no-cards style="min-width: 900px;">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 160px;">Modul</th>
                            <th style="min-width: 120px;">Aksi</th>
                            <th class="text-center" style="width: 90px;" title="Selalu penuh">
                                <?= e($roleLabelAll[ROLE_SUPER_ADMIN] ?? 'Super Admin') ?>
                            </th>
                            <?php foreach ($editableRoles as $slug): ?>
                                <th class="text-center" style="width: 110px;"><?= e($roleLabelAll[$slug] ?? $slug) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($catalog as $module => $actions): ?>
                            <?php $locked = permissionIsLockedModule($module); ?>
                            <?php foreach ($actions as $i => $action): ?>
                                <tr class="<?= $locked ? 'table-light text-muted' : '' ?>">
                                    <?php if ($i === 0): ?>
                                        <td rowspan="<?= count($actions) ?>" class="fw-semibold">
                                            <?= e($moduleLabel[$module] ?? $module) ?>
                                            <?php if ($locked): ?>
                                                <span class="badge bg-secondary ms-1" title="Dikunci ke Super Admin">
                                                    <i class="bi bi-lock-fill"></i> terkunci
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <span class="badge bg-light text-dark border">
                                            <?= e($actionLabel[$action] ?? $action) ?>
                                        </span>
                                    </td>

                                    <td class="text-center">
                                        <i class="bi bi-check-circle-fill text-success" title="Super Admin selalu boleh"></i>
                                    </td>

                                    <?php foreach ($editableRoles as $slug): ?>
                                        <td class="text-center">
                                            <?php if ($locked): ?>
                                                <i class="bi bi-dash text-muted"></i>
                                            <?php else: ?>
                                                <input type="checkbox" class="form-check-input"
                                                       name="perm[<?= e($slug) ?>][<?= e($module . '.' . $action) ?>]"
                                                       value="1"
                                                       <?= roleAllows($slug, $module, $action) ? 'checked' : '' ?>>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mt-3">
        <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-save"></i> Simpan Perubahan
        </button>
    </div>
</form>
