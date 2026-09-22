<?php
/**
 * Master Kode > Bank Masuk/Keluar -- BEDA dari group.php (5 kelompok
 * Barang/Supplier/Client/Gudang/Project): satu prefix aktif per arah (tanpa
 * "Tambah Prefix"), format No Bukti "PREFIX-NOMOR" (bukan titik), dan Nomor
 * Berikutnya dibaca live dari cash_number_counters -- lihat
 * MasterKodeController::groupBank().
 * $entityType, $entityMeta, $config (baris code_configs atau null), $prefix, $nextNumber
 */
$nextExample = $prefix . '-' . str_pad((string) max(1, $nextNumber), 4, '0', STR_PAD_LEFT);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-0">Master Kode &raquo; <?= e($entityMeta['label']) ?></h4>
        <small class="text-muted">
            Transaksi Bank dikelola di <a href="<?= BASE_URL ?>/cash">Kas/Bank</a> &mdash;
            di sini hanya prefix No Bukti-nya: <code>PREFIX-NOMOR</code> (mis. <?= e($prefix) ?>-0001).
        </small>
    </div>
    <a href="<?= BASE_URL ?>/master_kode" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Master Kode
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Prefix Aktif</h6>
                <form method="POST" action="<?= BASE_URL ?>/master_kode/renameBankPrefix" class="row g-2 align-items-end">
                    <?= csrfField() ?>
                    <input type="hidden" name="entity_type" value="<?= e($entityType) ?>">
                    <div class="col-8">
                        <label class="form-label small mb-1">Prefix</label>
                        <input type="text" name="prefix" class="form-control text-uppercase"
                               value="<?= e($prefix) ?>" maxlength="6" required>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save"></i> Simpan</button>
                    </div>
                </form>
                <div class="form-text mt-2">
                    2-6 huruf/angka, diawali huruf. Tidak boleh sama dengan prefix
                    <?= $entityType === 'bank_masuk' ? 'Bank Keluar' : 'Bank Masuk' ?> atau Prefix Kas PIC manapun.
                </div>
                <div class="alert alert-warning small mt-3 mb-0">
                    <i class="bi bi-exclamation-triangle"></i>
                    Rename TIDAK mengubah nomor transaksi Bank yang sudah ada -- transaksi lama
                    tetap memakai nomor lamanya. Sequence (nomor urut) lanjut, tidak reset ke 1.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="mb-3">Status Nomor</h6>
                <div class="text-muted small mb-1">Nomor berikutnya</div>
                <div class="fs-4 fw-semibold mb-3"><?= e($nextExample) ?></div>
                <div class="text-muted small">
                    Dibuat otomatis &amp; atomic saat transaksi Bank dengan Mutasi
                    <strong><?= $entityType === 'bank_masuk' ? 'Masuk' : 'Keluar' ?></strong> disimpan
                    (lihat <a href="<?= BASE_URL ?>/bank/create">Tambah Bank</a>) -- angka ini cuma pratinjau,
                    nomor resmi tetap dijamin server-side & aman dari request bersamaan.
                </div>
            </div>
        </div>
    </div>
</div>
