<?php /** @var array $assignments @var array $users */ ?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">PIC Kas</h4>
        <small class="text-muted">Kaitkan akun user ke nama PIC &mdash; menentukan transaksi Kas mana yang boleh dilihat user tersebut</small>
    </div>
    <a href="<?= BASE_URL ?>/master_data" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Master Data
    </a>
</div>

<div class="alert alert-light border small">
    <i class="bi bi-info-circle"></i>
    Role <strong>Purchase</strong>, <strong>PIC Project</strong>, dan <strong>Admin Project</strong> hanya melihat
    transaksi Kas dengan PIC yang terdaftar di sini untuk akun mereka, dan wajib
    <strong>verifikasi PIC + Password Kas</strong> saat membuka modul Kas. Role
    <strong>Super Admin</strong>, <strong>Accounting</strong>, dan <strong>Project Manager</strong> melihat seluruh Kas
    tanpa verifikasi tambahan. Atur username &amp; password/PIN Kas tiap PIC lewat tombol
    <i class="bi bi-key"></i> di bawah.
</div>

<?php if (can('user_pic', 'create')): ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=user_pic&action=store" class="row g-2 align-items-end">
            <?= csrfField() ?>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">User</label>
                <select name="user_id" class="form-select form-select-sm" required>
                    <option value="">-- Pilih User --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?> (<?= e($u['username']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small text-muted mb-1">Nama PIC</label>
                <input type="text" name="pic_name" class="form-control form-control-sm" placeholder="mis. Andi" required>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-plus-circle"></i> Tambah</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>PIC</th>
                        <th>Username Kas</th>
                        <th>Login Kas</th>
                        <th class="text-center no-print">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($assignments)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-person-badge empty-icon"></i>
                                <div class="empty-title">Belum ada mapping PIC</div>
                                <div class="empty-desc">Tambahkan mapping agar role ber-scope bisa melihat Kas terkait.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($assignments as $a): ?>
                        <?php
                            $hasCred = !empty($a['pic_password']);
                            $isActive = (int) ($a['is_active'] ?? 1) === 1;
                        ?>
                        <tr>
                            <td><?= e($a['full_name']) ?> <span class="text-muted small">(<?= e($a['username']) ?>)</span></td>
                            <td><span class="badge bg-light text-dark border"><?= e($a['role_name']) ?></span></td>
                            <td><?= e($a['pic_name']) ?></td>
                            <td><?= $a['pic_username'] !== null && $a['pic_username'] !== '' ? e($a['pic_username']) : '<span class="text-muted">&mdash;</span>' ?></td>
                            <td>
                                <?php if (!$hasCred): ?>
                                    <span class="badge bg-light text-dark border">Belum di-set</span>
                                <?php elseif ($isActive): ?>
                                    <span class="badge text-bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center no-print">
                                <?php if (can('user_pic', 'edit')): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#cred<?= (int) $a['id'] ?>" title="Set / reset password Kas">
                                    <i class="bi bi-key"></i>
                                </button>
                                <?php endif; ?>
                                <?php if (can('user_pic', 'delete')): ?>
                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=user_pic&action=delete"
                                      class="js-confirm-delete d-inline" data-message="Hapus mapping <?= e($a['username']) ?> &rarr; <?= e($a['pic_name']) ?>?">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (can('user_pic', 'edit')): ?>
                        <tr class="collapse no-print" id="cred<?= (int) $a['id'] ?>">
                            <td colspan="6" class="bg-light">
                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=user_pic&action=setCredential" class="row g-2 align-items-end">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Username Kas (opsional)</label>
                                        <input type="text" name="pic_username" class="form-control form-control-sm" value="<?= e($a['pic_username'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Password Kas <?= $hasCred ? '(kosongkan = tetap)' : '(wajib)' ?></label>
                                        <input type="password" name="kas_password" class="form-control form-control-sm" minlength="6" autocomplete="new-password" <?= $hasCred ? '' : 'required' ?>>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small text-muted mb-1">Konfirmasi Password</label>
                                        <input type="password" name="kas_password_confirm" class="form-control form-control-sm" minlength="6" autocomplete="new-password">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small text-muted mb-1">Status</label>
                                        <select name="is_active" class="form-select form-select-sm">
                                            <option value="1" <?= $isActive ? 'selected' : '' ?>>Aktif</option>
                                            <option value="0" <?= !$isActive ? 'selected' : '' ?>>Nonaktif</option>
                                        </select>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-save"></i></button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js-confirm-delete').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            confirmAction(form.dataset.message, 'Ya, hapus').then(function (ok) {
                if (ok) form.submit();
            });
        });
    });
});
</script>
