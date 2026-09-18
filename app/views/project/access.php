<?php
/** @var array $project @var array $assignedUserIds @var array $assignableUsers */
$assignedSet = array_flip(array_map('intval', $assignedUserIds));
$roleLabels = roleLabelMap();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Akses Project: <?= e($project['project_name']) ?></h4>
        <small class="text-muted">
            Pilih user (Purchase / PIC Project / Admin Project) yang boleh membuka Kas
            project ini lewat verifikasi Project + Password akun sendiri.
        </small>
    </div>
    <a href="<?= BASE_URL ?>/project" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Kembali
    </a>
</div>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    User yang TIDAK dicentang di sini tidak akan bisa membuka data Kas project ini,
    walaupun mereka memanipulasi ID project lewat URL &mdash; validasi dilakukan di server.
</div>

<form method="POST" action="<?= BASE_URL ?>/index.php?module=project&action=accessUpdate">
    <?= csrfField() ?>
    <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:48px;"></th>
                            <th>Nama</th>
                            <th>Username</th>
                            <th>Role</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignableUsers)): ?>
                            <tr><td colspan="4" class="p-0">
                                <div class="empty-state">
                                    <i class="bi bi-people empty-icon"></i>
                                    <div class="empty-title">Belum ada user Purchase/PIC Project/Admin Project</div>
                                    <div class="empty-desc">Tambahkan user dengan role tersebut di User Management dulu.</div>
                                </div>
                            </td></tr>
                        <?php endif; ?>
                        <?php foreach ($assignableUsers as $u): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input" name="user_ids[]"
                                           value="<?= (int) $u['id'] ?>" id="u<?= (int) $u['id'] ?>"
                                           <?= isset($assignedSet[(int) $u['id']]) ? 'checked' : '' ?>>
                                </td>
                                <td><label for="u<?= (int) $u['id'] ?>"><?= e($u['full_name']) ?></label></td>
                                <td class="text-muted"><?= e($u['username']) ?></td>
                                <td><span class="badge bg-light text-dark border"><?= e($roleLabels[$u['role_slug']] ?? $u['role_slug']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if (!empty($assignableUsers)): ?>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan Akses</button>
        <a href="<?= BASE_URL ?>/project" class="btn btn-light border">Batal</a>
    </div>
    <?php endif; ?>
</form>
