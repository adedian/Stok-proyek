<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <h6 class="mb-3">Tambah Rekening</h6>
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=bankAccountStore" class="row g-3">
            <?= csrfField() ?>
            <div class="col-md-4">
                <label class="form-label">Nama &amp; Cabang Bank</label>
                <input type="text" name="bank_name" class="form-control" placeholder="Bank Central Asia, KCU. HR. Muhammad – Surabaya" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">No. Rekening</label>
                <input type="text" name="account_number" class="form-control" placeholder="829-2187296" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Atas Nama</label>
                <input type="text" name="account_holder_name" class="form-control" placeholder="PT Hexa Multi Energi" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Tambah Rekening</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Bank</th>
                        <th>No. Rekening</th>
                        <th>Atas Nama</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 220px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bankAccounts)): ?>
                        <tr><td colspan="5" class="p-0">
                            <div class="empty-state">
                                <i class="bi bi-bank empty-icon"></i>
                                <div class="empty-title">Belum ada rekening</div>
                                <div class="empty-desc mb-0">Tambahkan rekening supaya muncul di cetak Invoice Keluar.</div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($bankAccounts as $b): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold" id="bankName<?= (int) $b['id'] ?>"><?= e($b['bank_name']) ?></div>
                            </td>
                            <td id="bankNumber<?= (int) $b['id'] ?>"><?= e($b['account_number']) ?></td>
                            <td id="bankHolder<?= (int) $b['id'] ?>"><?= e($b['account_holder_name']) ?></td>
                            <td class="text-center">
                                <?php if ((int) $b['is_active'] === 1): ?>
                                    <span class="badge bg-success">Aktif dipakai Invoice</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Nonaktif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    <?php if ((int) $b['is_active'] !== 1): ?>
                                        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=bankAccountActivate">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Jadikan rekening aktif">
                                                <i class="bi bi-check-circle"></i> Jadikan Aktif
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal" data-bs-target="#modalEditBank<?= (int) $b['id'] ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=bankAccountDelete"
                                          class="js-confirm-delete" data-message="Hapus rekening <?= e($b['bank_name']) ?>?">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <div class="modal fade" id="modalEditBank<?= (int) $b['id'] ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=bankAccountUpdate">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Rekening</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                                        </div>
                                        <div class="modal-body">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                                            <div class="mb-3">
                                                <label class="form-label">Nama &amp; Cabang Bank</label>
                                                <input type="text" name="bank_name" class="form-control" value="<?= e($b['bank_name']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">No. Rekening</label>
                                                <input type="text" name="account_number" class="form-control" value="<?= e($b['account_number']) ?>" required>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Atas Nama</label>
                                                <input type="text" name="account_holder_name" class="form-control" value="<?= e($b['account_holder_name']) ?>" required>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
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
