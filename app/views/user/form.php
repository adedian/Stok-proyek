<?php
$isEdit = $mode === 'edit';
$actionUrl = $isEdit ? 'update' : 'store';
$moduleLabel = $permLabels['modules'];
$actionLabel = $permLabels['actions'];
$hasOverrides = !empty($permOverrides);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Tambah' ?> User</h4>
    </div>
    <a href="<?= BASE_URL ?>/user" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=user&action=<?= $actionUrl ?>">
    <?= csrfField() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" class="form-control"
                           value="<?= e($user['full_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Role <span class="text-danger">*</span></label>
                    <select name="role_id" id="roleSelect" class="form-select" required>
                        <option value="">-- Pilih Role --</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id'] ?>"
                                <?= $isEdit && (int) $user['role_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                                <?= e($r['role_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$isEdit): ?>
                        <div class="form-text">
                            Role <strong>Purchase</strong>, <strong>PIC Project</strong>, <strong>Admin Project</strong>:
                            PIC Kas otomatis dibuat saat user disimpan (Password Kas awal = password login;
                            bisa diganti di <em>Master Data &rsaquo; PIC Kas</em>).
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control"
                           value="<?= e($user['username'] ?? '') ?>" autocomplete="off" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control"
                           value="<?= e($user['email'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Password <?= $isEdit ? '' : '<span class="text-danger">*</span>' ?></label>
                    <input type="password" name="password" class="form-control" autocomplete="new-password"
                           placeholder="<?= $isEdit ? 'Kosongkan jika tidak ingin mengubah' : 'Minimal 6 karakter' ?>"
                           <?= $isEdit ? '' : 'required minlength="6"' ?>>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h6 class="mb-1"><i class="bi bi-shield-lock"></i> Hak Akses</h6>
                    <p class="text-muted small mb-0">
                        Secara bawaan user mengikuti hak akses role-nya. Aktifkan penyesuaian untuk
                        memberi / mencabut akses tertentu khusus user ini.
                    </p>
                </div>
                <div class="form-check form-switch mt-1">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="customize_permissions" id="customizePerms" value="1"
                           <?= $hasOverrides ? 'checked' : '' ?>>
                    <label class="form-check-label" for="customizePerms">Sesuaikan akses untuk user ini</label>
                </div>
            </div>

            <div id="permSAWarn" class="alert alert-info small mt-3 mb-0" style="display:none;">
                <i class="bi bi-info-circle"></i> Role <strong>Super Admin</strong> selalu memiliki seluruh akses &mdash; penyesuaian tidak berlaku.
            </div>

            <div id="permPanel" class="mt-3" style="display:none;">
                <div class="alert alert-light border small">
                    Baris <span class="badge bg-warning text-dark">kuning</span> = berbeda dari bawaan role.
                    Modul Pengaturan Sistem, User Management, dan Tempat Sampah tidak bisa disesuaikan (khusus Super Admin).
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" style="min-width: 520px;">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 170px;">Modul</th>
                                <th style="min-width: 130px;">Aksi</th>
                                <th class="text-center" style="width: 90px;">Boleh?</th>
                            </tr>
                        </thead>
                        <tbody id="permBody">
                            <?php foreach ($permCatalog as $module => $actions): ?>
                                <?php foreach ($actions as $i => $action): ?>
                                    <tr>
                                        <?php if ($i === 0): ?>
                                            <td rowspan="<?= count($actions) ?>" class="fw-semibold">
                                                <?= e($moduleLabel[$module] ?? $module) ?>
                                            </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= e($actionLabel[$action] ?? $action) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input"
                                                   name="uperm[<?= e($module . '.' . $action) ?>]" value="1"
                                                   data-key="<?= e($module . '.' . $action) ?>" disabled>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-save"></i> Simpan
        </button>
        <a href="<?= BASE_URL ?>/user" class="btn btn-light border">Batal</a>
    </div>
</form>

<script>
(function () {
    const ROLE_MATRIX     = <?= json_encode($permRoleMatrix, JSON_UNESCAPED_SLASHES) ?>;
    const ROLE_SLUG_BY_ID = <?= json_encode($permRoleSlugById, JSON_UNESCAPED_SLASHES) ?>;
    const INITIAL_OVERRIDES = <?= json_encode((object) $permOverrides, JSON_UNESCAPED_SLASHES) ?>;

    const roleSelect = document.getElementById('roleSelect');
    const toggle     = document.getElementById('customizePerms');
    const panel      = document.getElementById('permPanel');
    const saWarn     = document.getElementById('permSAWarn');
    const body       = document.getElementById('permBody');

    let overrides = Object.assign({}, INITIAL_OVERRIDES);

    function selectedSlug() {
        return ROLE_SLUG_BY_ID[roleSelect.value] || '';
    }

    function baseAllows(slug, key) {
        return !!(ROLE_MATRIX[slug] && ROLE_MATRIX[slug][key]);
    }

    function refresh(resetOverrides) {
        const slug = selectedSlug();
        const isSA = slug === 'super_admin';
        if (resetOverrides) overrides = {};

        toggle.disabled = isSA;
        saWarn.style.display = isSA ? '' : 'none';
        const on = toggle.checked && !isSA;
        panel.style.display = on ? '' : 'none';

        body.querySelectorAll('input[type=checkbox]').forEach(function (cb) {
            const key = cb.dataset.key;
            const base = baseAllows(slug, key);
            const ov = overrides[key];
            const eff = ov ? ov === 'allow' : base;
            cb.checked = eff;
            cb.disabled = !on;
            cb.closest('tr').classList.toggle('table-warning', on && eff !== base);
        });
    }

    roleSelect.addEventListener('change', function () { refresh(true); });
    toggle.addEventListener('change', function () { refresh(false); });
    body.addEventListener('change', function (e) {
        if (!e.target.matches('input[type=checkbox]')) return;
        const key = e.target.dataset.key;
        const base = baseAllows(selectedSlug(), key);
        if (e.target.checked === base) {
            delete overrides[key];
        } else {
            overrides[key] = e.target.checked ? 'allow' : 'deny';
        }
        e.target.closest('tr').classList.toggle('table-warning', e.target.checked !== base);
    });

    refresh(false);
})();
</script>
