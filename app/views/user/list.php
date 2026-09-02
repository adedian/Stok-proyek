<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">User Management</h4>
        <small class="text-muted">Kelola akun & role pengguna sistem</small>
    </div>
    <a href="<?= BASE_URL ?>/user/create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Tambah User
    </a>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="6" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-people empty-icon"></i>
                                <div class="empty-title">Belum ada user</div>
                                <div class="empty-desc">Tambahkan akun user untuk anggota tim yang perlu mengakses sistem.</div>
                                <a href="<?= BASE_URL ?>/user/create" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-circle"></i> Tambah User
                                </a>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= e($u['full_name']) ?></td>
                            <td><?= e($u['username']) ?></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge bg-secondary"><?= e($u['role_name']) ?></span></td>
                            <td class="text-center">
                                <?php if ($u['status'] === 'active'): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ((int) $u['id'] === currentUserId()): ?>
                                    <span class="text-muted small">Anda</span>
                                <?php else: ?>
                                    <div class="dropdown row-actions">
                                        <button type="button" class="btn btn-row-actions" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/user/edit/<?= (int) $u['id'] ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                            </li>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=user&action=toggleStatus"
                                                      onsubmit="return confirm('<?= $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?> user <?= e($u['username']) ?>?');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                    <button type="submit" class="dropdown-item <?= $u['status'] === 'active' ? 'text-danger' : 'text-success' ?>">
                                                        <i class="bi bi-<?= $u['status'] === 'active' ? 'slash-circle' : 'check-circle' ?>"></i>
                                                        <?= $u['status'] === 'active' ? 'Nonaktifkan' : 'Aktifkan' ?>
                                                    </button>
                                                </form>
                                            </li>
                                            <?php if (can('user', 'delete')): ?>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form method="POST" action="<?= BASE_URL ?>/index.php?module=user&action=delete"
                                                      onsubmit="return confirm('HAPUS akun <?= e($u['username']) ?> secara permanen dari daftar? Akun tidak bisa login lagi. Data riwayat tetap tersimpan.');">
                                                    <?= csrfField() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash"></i> Hapus Akun
                                                    </button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
