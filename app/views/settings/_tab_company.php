<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveCompanyProfile" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Nama Perusahaan</label>
                    <input type="text" name="company_name" class="form-control" value="<?= e($company['company_name'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Logo Perusahaan</label>
                    <input type="file" name="company_logo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                    <?php if (!empty($company['company_logo'])): ?>
                        <div class="form-text">
                            Logo saat ini: <a href="<?= BASE_URL ?>/<?= e($company['company_logo']) ?>" target="_blank">lihat logo</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">No. Telepon</label>
                    <input type="text" name="company_phone" class="form-control" value="<?= e($company['company_phone'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="company_email" class="form-control" value="<?= e($company['company_email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">NPWP</label>
                    <input type="text" name="company_npwp" class="form-control" value="<?= e($company['company_npwp'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Alamat</label>
                    <textarea name="company_address" class="form-control" rows="2"><?= e($company['company_address'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan Profil Perusahaan</button>
        </form>
    </div>
</div>
<div class="alert alert-light border mt-3 small">
    <i class="bi bi-info-circle"></i> Data ini dipakai di kop laporan PDF dan tersedia untuk modul lain ke depannya.
    Rekening bank untuk Invoice sekarang diatur di tab <strong>Rekening Bank</strong>.
</div>
