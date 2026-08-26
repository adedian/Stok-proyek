<div class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="POST" action="<?= BASE_URL ?>/index.php?module=settings&action=saveSession">
            <?= csrfField() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Timeout Login / Auto Logout (menit)</label>
                    <input type="number" name="session_timeout_minutes" class="form-control" min="5" max="480"
                           value="<?= e($sessionSettings['session_timeout_minutes'] ?? '30') ?>">
                    <div class="form-text">User otomatis logout kalau tidak ada aktivitas selama sekian menit (5-480).</div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-save"></i> Simpan</button>
        </form>
    </div>
</div>
